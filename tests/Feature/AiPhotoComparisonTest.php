<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoComparison;
use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    Http::preventStrayRequests();
    Sleep::fake();

    $this->point = MonitoringPoint::factory()->create(['label' => 'Private kitchen wall']);
    $this->earlier = PhotoRecord::factory()->for($this->point)->create(['submitted_at' => now()->subWeeks(2), 'note' => 'My own first note']);
    $this->later = PhotoRecord::factory()->for($this->point)->create(['submitted_at' => now()->subDay(), 'note' => 'My own later note']);

    foreach ([$this->earlier, $this->later] as $record) {
        Storage::disk('local')->put($record->image_path, UploadedFile::fake()->image('wall.jpg', 1600, 1200)->getContent());
    }

    $this->compareUrl = route('points.compare', [$this->point, 'first' => $this->earlier->id, 'second' => $this->later->id]);
});

function turnOnComparison(): void
{
    config(['services.photo_comparison' => [
        'driver' => 'openai_compatible',
        'provider_name' => 'Test Vision Service',
        'api_url' => 'https://vision.example.test/v1',
        'api_key' => 'test-key-not-real',
        'model' => 'vision-test-model',
        'timeout' => 30,
        'max_image_side' => 1024,
    ]]);
}

// Shaped like an OpenAI-compatible chat completion; the wording is synthetic and only used in tests
function visionReply(array $visible, array $photo): array
{
    return [
        'id' => 'test-completion-1',
        'model' => 'vision-test-model-2026',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['visible_differences' => $visible, 'photo_differences' => $photo])]]],
    ];
}

function askForComparison($test)
{
    return $test->actingAs($test->point->user)->post(route('points.ai-comparisons.store', $test->point), [
        'earlier' => $test->earlier->id,
        'later' => $test->later->id,
    ]);
}

test('AI comparison stays off until it is set up, and nothing is sent', function () {
    $this->actingAs($this->point->user)->get($this->compareUrl)
        ->assertOk()
        ->assertSee('AI comparison isn’t set up on this RISE server')
        ->assertDontSee('Compare with AI');

    askForComparison($this)->assertRedirect($this->compareUrl.'#ai-comparison')->assertSessionHas('info');

    expect(PhotoComparison::count())->toBe(0);
    Http::assertNothingSent();
});

test('before anything is sent, the resident sees who gets the photos and what is sent', function () {
    turnOnComparison();

    $this->actingAs($this->point->user)->get($this->compareUrl)
        ->assertOk()
        ->assertSee('Test Vision Service')
        ->assertSee('Smaller copies of these two photos, with location and other hidden details removed')
        ->assertSee('Your notes, your spot’s name or your account details')
        ->assertSee('Compare with AI');

    Http::assertNothingSent();
});

test('it sends two location-free copies and no notes or names, and shows cautious wording', function () {
    turnOnComparison();
    Http::fake(['vision.example.test/*' => Http::response(visionReply(
        ['The line near the window appears a little more visible in photo B.'],
        ['Photo B seems to be taken from slightly further left.', 'The light is warmer in photo B.'],
    ))]);

    askForComparison($this)->assertRedirect($this->compareUrl.'#ai-comparison');

    Http::assertSent(function (Request $request) {
        $content = $request['messages'][1]['content'];
        $images = array_values(array_filter($content, fn ($part) => $part['type'] === 'image_url'));
        $jpegs = array_map(fn ($part) => base64_decode(substr($part['image_url']['url'], strlen('data:image/jpeg;base64,')), true), $images);

        return $request->url() === 'https://vision.example.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key-not-real')
            && $request['model'] === 'vision-test-model'
            && count($jpegs) === 2
            && collect($jpegs)->every(fn ($jpeg) => str_starts_with($jpeg, "\xFF\xD8\xFF")
                && max(getimagesizefromstring($jpeg)[0], getimagesizefromstring($jpeg)[1]) <= 1024
                && @exif_read_data('data://image/jpeg;base64,'.base64_encode($jpeg), 'GPS') === false)
            && ! str_contains($request->body(), 'Private kitchen wall')
            && ! str_contains($request->body(), 'My own')
            && ! str_contains($request->body(), $this->point->user->email);
    });

    $comparison = PhotoComparison::sole();
    expect($comparison->status)->toBe(PhotoComparison::COMPLETED)
        ->and($comparison->provider)->toBe('Test Vision Service')
        ->and($comparison->visibleDifferences())->toBe(['The line near the window appears a little more visible in photo B.']);

    $this->get($this->compareUrl)
        ->assertSee('Possible visible differences')
        ->assertSee('The light is warmer in photo B.')
        ->assertSee('AI can be wrong or miss things. It can’t measure a crack, tell what caused it, or say whether your home is safe.');
});

test('claims about size, cause, severity, repairs or safety are never shown', function () {
    turnOnComparison();
    Http::fake(['vision.example.test/*' => Http::response(visionReply([
        'The crack appears about 2 mm wider.',
        'This is likely caused by settlement of the foundation.',
        'The damage looks severe and the wall may be unsafe.',
        'It should be repaired soon.',
        'A short new mark appears near the corner.',
    ], ['The second photo is darker.']))]);

    askForComparison($this);

    expect(PhotoComparison::sole()->visibleDifferences())->toBe(['A short new mark appears near the corner.']);

    $this->get($this->compareUrl)
        ->assertDontSee('2 mm')
        ->assertDontSee('settlement')
        ->assertDontSee('unsafe')
        ->assertDontSee('repaired');
});

test('the AI wording is never saved as a note by itself; the resident can edit it into their own note', function () {
    turnOnComparison();
    Http::fake(['vision.example.test/*' => Http::response(visionReply(['A thin line appears longer in photo B.'], []))]);

    askForComparison($this);

    expect($this->earlier->fresh()->note)->toBe('My own first note')
        ->and($this->later->fresh()->note)->toBe('My own later note');

    $this->get($this->compareUrl)
        ->assertSee('Edit into my note for Follow-up 1')
        ->assertSee('Nothing is saved until you press Save note.');

    $this->patch(route('records.note.update', $this->later), ['note' => 'My own later note. The line looks longer to me too.'])
        ->assertSessionHas('success', 'Note saved.');

    expect($this->later->fresh()->note)->toBe('My own later note. The line looks longer to me too.');
});

test('a result can be dismissed, and run again later', function () {
    turnOnComparison();
    Http::fake(['vision.example.test/*' => Http::response(visionReply(['A thin line appears longer in photo B.'], []))]);
    askForComparison($this);
    $comparison = PhotoComparison::sole();

    $this->patch(route('ai-comparisons.dismiss', $comparison))->assertRedirect($this->compareUrl.'#ai-comparison');

    expect($comparison->fresh()->dismissed_at)->not->toBeNull();
    $this->get($this->compareUrl)->assertDontSee('A thin line appears longer')->assertSee('Compare with AI');
});

test('provider failures are stored as safe codes and the photos stay as they were', function () {
    turnOnComparison();
    Http::fake(['vision.example.test/*' => Http::response(['error' => ['message' => 'secret internal detail']], 500)]);

    askForComparison($this);

    $comparison = PhotoComparison::sole();
    expect($comparison->status)->toBe(PhotoComparison::FAILED)
        ->and($comparison->error_code)->toBe('provider_unavailable');

    $this->get($this->compareUrl)
        ->assertSee('The AI service couldn’t be reached or didn’t answer in time.')
        ->assertSee('Try again')
        ->assertDontSee('secret internal detail');
    Storage::disk('local')->assertExists([$this->earlier->image_path, $this->later->image_path]);
});

test('other residents cannot run, check or dismiss a comparison, or edit a note', function () {
    turnOnComparison();
    Http::fake(['vision.example.test/*' => Http::response(visionReply([], []))]);
    askForComparison($this);
    $comparison = PhotoComparison::sole();

    $this->actingAs(User::factory()->create());

    $this->post(route('points.ai-comparisons.store', $this->point), ['earlier' => $this->earlier->id, 'later' => $this->later->id])->assertNotFound();
    $this->get(route('ai-comparisons.status', $comparison))->assertNotFound();
    $this->patch(route('ai-comparisons.dismiss', $comparison))->assertNotFound();
    $this->patch(route('records.note.update', $this->later), ['note' => 'Changed'])->assertNotFound();

    expect(PhotoComparison::count())->toBe(1)
        ->and($comparison->fresh()->dismissed_at)->toBeNull()
        ->and($this->later->fresh()->note)->toBe('My own later note');
});

test('only two different photos of the same spot can be compared', function () {
    turnOnComparison();
    $elsewhere = PhotoRecord::factory()->create();
    $this->actingAs($this->point->user);

    $this->post(route('points.ai-comparisons.store', $this->point), ['earlier' => $this->earlier->id, 'later' => $this->earlier->id])
        ->assertSessionHasErrors('later');
    $this->post(route('points.ai-comparisons.store', $this->point), ['earlier' => $this->earlier->id, 'later' => $elsewhere->id])
        ->assertNotFound();

    expect(PhotoComparison::count())->toBe(0);
    Http::assertNothingSent();
});
