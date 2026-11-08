<?php

use App\Jobs\AnalyzePhoto;
use App\Models\MonitoringPoint;
use App\Models\PhotoAnalysis;
use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    Http::preventStrayRequests();
    Sleep::fake();
    config(['services.roboflow.api_key' => 'test-key-not-real', 'services.roboflow.model_id' => 'concrete-crack-ver.1/2']);

    $this->record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($this->record->image_path, UploadedFile::fake()->image('wall.jpg', 1200, 900)->getContent());
});

// Shaped like Roboflow's instance-segmentation response; the values are synthetic and only used in tests
function roboflowResponse(array $predictions): array
{
    return [
        'inference_id' => 'test-inference-1',
        'time' => 0.2,
        'image' => ['width' => 1200, 'height' => 900],
        'resolved_model' => ['model_id' => 'concrete-crack-ver.1/2'],
        'predictions' => $predictions,
    ];
}

function crackPrediction(array $overrides = []): array
{
    return array_merge([
        'x' => 600, 'y' => 450, 'width' => 240, 'height' => 90, 'confidence' => 0.87, 'class' => 'crack', 'class_id' => 1,
        'detection_id' => 'detection-1',
        'points' => [['x' => 480, 'y' => 405], ['x' => 720, 'y' => 420], ['x' => 715, 'y' => 495], ['x' => 485, 'y' => 480]],
    ], $overrides);
}

test('saving a photo never sends it for analysis', function () {
    Queue::fake();
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('crack.jpg', 800, 600),
    ])->assertSessionHasNoErrors();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
    expect(PhotoAnalysis::count())->toBe(0);
});

test('asking for analysis queues it for the worker', function () {
    Queue::fake();

    $this->actingAs($this->record->user)
        ->post(route('records.analysis.store', $this->record))
        ->assertRedirect(route('records.analysis.show', $this->record));

    Queue::assertPushed(AnalyzePhoto::class);
    expect(PhotoAnalysis::sole()->status)->toBe(PhotoAnalysis::PENDING);
});

test('the owner sees the model’s marked areas with the required wording', function () {
    Http::fake(['serverless.roboflow.com/*' => Http::response(roboflowResponse([crackPrediction()]))]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    $analysis = PhotoAnalysis::sole();

    expect($analysis->status)->toBe(PhotoAnalysis::COMPLETED)
        ->and($analysis->region_count)->toBe(1)
        ->and($analysis->model_version)->toBe('concrete-crack-ver.1/2')
        ->and($analysis->provider_request_id)->toBe('test-inference-1')
        ->and($analysis->regions()[0]['box'])->toBe([0.4, 0.45, 0.2, 0.1])
        ->and($analysis->regions()[0]['polygon'][0])->toBe([0.4, 0.45])
        ->and($analysis->toJson())->not->toContain('test-key-not-real');

    $this->get(route('records.analysis.show', $this->record))
        ->assertOk()
        ->assertSee('The model marked possible crack-like areas.')
        ->assertSee('Possible crack-like area detected by AI')
        ->assertSee('AI image results can be wrong.')
        ->assertSee('Model score')
        ->assertSee('<polygon', false)
        ->assertDontSee('test-key-not-real');
});

test('the request sends a location-free copy and asks the provider not to keep it for training', function () {
    Http::fake(['serverless.roboflow.com/*' => Http::response(roboflowResponse([]))]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $image = base64_decode($request->body(), true);

        return str_starts_with($request->url(), 'https://serverless.roboflow.com/concrete-crack-ver.1/2?')
            && $query['api_key'] === 'test-key-not-real'
            && $query['disable_active_learning'] === 'true'
            && $query['response_mask_format'] === 'polygon'
            && str_starts_with($image, "\xFF\xD8\xFF")
            && @exif_read_data('data://image/jpeg;base64,'.base64_encode($image), 'GPS') === false;
    });
});

test('no marked areas are reported without claiming there is no crack', function () {
    Http::fake(['serverless.roboflow.com/*' => Http::response(roboflowResponse([]))]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    expect(PhotoAnalysis::sole()->region_count)->toBe(0);
    $this->get(route('records.analysis.show', $this->record))
        ->assertSee('The model did not mark an area in this photo. It can still miss cracks.')
        ->assertDontSee('No crack')
        ->assertDontSee('<polygon', false);
});

test('missing credentials give an honest not-configured state and send nothing', function () {
    config(['services.roboflow.api_key' => null]);

    $this->actingAs($this->record->user)->get(route('records.analysis.show', $this->record))
        ->assertSee('AI analysis isn’t set up')
        ->assertDontSee('Analyze with AI</button>', false);
    $this->post(route('records.analysis.store', $this->record));

    Http::assertNothingSent();
    expect(PhotoAnalysis::sole()->status)->toBe(PhotoAnalysis::NOT_CONFIGURED);
});

test('provider failures are stored as safe codes and the photo stays available', function (Closure $fake, string $code, int $attempts) {
    Http::fake(['serverless.roboflow.com/*' => $fake()]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    $analysis = PhotoAnalysis::sole();

    expect($analysis->status)->toBe(PhotoAnalysis::FAILED)
        ->and($analysis->error_code)->toBe($code)
        ->and($analysis->toJson())->not->toContain('test-key-not-real');
    Http::assertSentCount($attempts);

    $this->get(route('records.analysis.show', $this->record))->assertSee('The analysis didn’t finish')->assertSee('Try again');
    $this->get(route('records.photo', $this->record))->assertOk();
})->with([
    'invalid key, not retried' => [fn () => Http::response(['message' => 'Unauthorized'], 401), 'invalid_credentials', 1],
    'unknown model, not retried' => [fn () => Http::response([], 404), 'model_not_found', 1],
    'provider down, retried' => [fn () => Http::response([], 503), 'provider_unavailable', 3],
    'connection dropped, retried' => [fn () => Http::failedConnection(), 'provider_unreachable', 3],
    'unreadable response' => [fn () => Http::response(['unexpected' => true]), 'invalid_response', 1],
]);

test('a rate-limited request is retried before it succeeds', function () {
    Http::fake(['serverless.roboflow.com/*' => Http::sequence()->push([], 429)->push(roboflowResponse([crackPrediction()]))]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    Http::assertSentCount(2);
    expect(PhotoAnalysis::sole()->status)->toBe(PhotoAnalysis::COMPLETED);
});

test('provider output is validated and escaped before it is stored or shown', function () {
    Http::fake(['serverless.roboflow.com/*' => Http::response(roboflowResponse([
        crackPrediction(['class' => '<script>alert(1)</script>', 'confidence' => 7, 'x' => -50, 'points' => [['x' => 'nope', 'y' => 1]]]),
        ['x' => 'not a number'],
        'not a prediction',
    ]))]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    $region = PhotoAnalysis::sole()->regions()[0];

    expect(PhotoAnalysis::sole()->region_count)->toBe(1)
        ->and($region['label'])->not->toContain('<')
        ->and($region['score'])->toEqual(1.0)
        ->and($region['polygon'])->toBeNull()
        ->and(collect($region['box'])->every(fn ($value) => $value >= 0 && $value <= 1))->toBeTrue();
    $this->get(route('records.analysis.show', $this->record))->assertDontSee('<script>alert(1)</script>', false)->assertSee('box only');
});

test('a failed analysis can be retried as a new attempt', function () {
    PhotoAnalysis::factory()->for($this->record)->create(['status' => PhotoAnalysis::FAILED, 'error_code' => 'rate_limited']);
    Http::fake(['serverless.roboflow.com/*' => Http::response(roboflowResponse([crackPrediction()]))]);

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    expect(PhotoAnalysis::count())->toBe(2)
        ->and($this->record->fresh()->latestAnalysis->status)->toBe(PhotoAnalysis::COMPLETED);
});

test('an analysis that is waiting is not requested twice', function () {
    Queue::fake();
    PhotoAnalysis::factory()->for($this->record)->create();

    $this->actingAs($this->record->user)->post(route('records.analysis.store', $this->record));

    Queue::assertNothingPushed();
    expect(PhotoAnalysis::count())->toBe(1);
    $this->get(route('records.analysis.show', $this->record))->assertSee('Analyzing photo…');
    $this->getJson(route('records.analysis.status', $this->record))->assertExactJson(['status' => PhotoAnalysis::PENDING]);
});

test('other residents cannot see, request or check an analysis', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('records.analysis.show', $this->record))->assertNotFound();
    $this->post(route('records.analysis.store', $this->record))->assertNotFound();
    $this->getJson(route('records.analysis.status', $this->record))->assertNotFound();

    Http::assertNothingSent();
    expect(PhotoAnalysis::count())->toBe(0);
});

test('deleting a photo deletes its analyses', function () {
    PhotoAnalysis::factory()->for($this->record)->create(['status' => PhotoAnalysis::COMPLETED, 'region_count' => 0, 'result' => ['regions' => []]]);

    $this->actingAs($this->record->user)->delete(route('records.destroy', $this->record));

    expect(PhotoAnalysis::count())->toBe(0);
});
