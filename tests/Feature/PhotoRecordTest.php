<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
});

test('a resident can add a photo record to their monitoring point', function () {
    $point = MonitoringPoint::factory()->create();

    $response = $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('crack.jpg', 1200, 900),
        'note' => 'Looks longer near the top',
    ]);

    $record = PhotoRecord::sole();

    $response->assertRedirect(route('points.show', $point).'#record-'.$record->id);
    expect($record->user_id)->toBe($point->user_id)
        ->and($record->monitoring_point_id)->toBe($point->id)
        ->and($record->note)->toBe('Looks longer near the top')
        ->and($record->image_mime)->toBe('image/jpeg')
        ->and([$record->image_width, $record->image_height])->toBe([1200, 900])
        ->and($record->photo_taken_at)->toBeNull() // the generated test image has no EXIF date
        ->and($record->submitted_at)->not->toBeNull()
        ->and($record->image_path)->toStartWith("photo-records/{$point->user_id}/{$point->id}/");
    Storage::disk('local')->assertExists($record->image_path);

    $this->get(route('points.show', $point))->assertOk()->assertSee('Looks longer near the top');
});

test('the photo form script gets JSON responses', function () {
    $point = MonitoringPoint::factory()->create();
    $this->actingAs($point->user);

    $this->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('crack.png', 800, 600),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonStructure(['redirect']);

    $this->post(route('records.store'), ['monitoring_point_id' => $point->id], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo' => 'Take or choose a photo to save.']);
});

test('a date sent by the browser is ignored rather than stored as when the photo was taken', function (string $sentDate) {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('crack.jpg', 800, 600),
        'captured_at' => $sentDate,
        'photo_taken_at' => $sentDate,
        'file_modified_at' => $sentDate,
    ])->assertSessionHasNoErrors();

    $record = PhotoRecord::sole();
    expect($record->photo_taken_at)->toBeNull()
        ->and($record->photo_taken_offset)->toBeNull()
        ->and($record->file_modified_at)->toBeNull();
})->with(['not a date', '2026-10-01T09:00:00Z']);

test('the date a photo was taken is read from its EXIF details as unverified camera time', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => new UploadedFile(base_path('tests/Fixtures/photo-taken.jpg'), 'photo-taken.jpg', 'image/jpeg', null, true),
    ])->assertSessionHasNoErrors();

    $record = PhotoRecord::sole();
    expect($record->photo_taken_at->format('Y-m-d H:i:s'))->toBe('2026-10-31 08:15:00')
        ->and($record->photo_taken_offset)->toBe('+08:00')
        ->and($record->file_modified_at)->toBeNull();

    $this->get(route('points.show', $point))
        ->assertSee('Photo taken')
        ->assertSee('31 Oct 2026, 08:15')
        ->assertSee('UTC+08:00');
});

test('bad uploads are rejected with a clear message and nothing is stored', function (Closure $photo, string $message) {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => $photo(),
    ])->assertSessionHasErrors('photo');

    expect(session('errors')->first('photo'))->toContain($message)
        ->and(PhotoRecord::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    'missing' => [fn () => null, 'Take or choose a photo to save.'],
    'not an image' => [fn () => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'), 'Use a JPEG, PNG'],
    'too large' => [fn () => UploadedFile::fake()->image('big.jpg', 1200, 900)->size(\App\Support\PhotoUploads::maxKilobytes() + 1), 'This photo is larger than'],
    'too small to compare' => [fn () => UploadedFile::fake()->image('tiny.jpg', 120, 120), 'This photo is too small to compare.'],
]);

test('a photo cannot be added to another resident\'s monitoring point', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('crack.jpg', 800, 600),
    ])->assertSessionHasErrors(['monitoring_point_id' => 'Choose one of your spots.']);

    expect(PhotoRecord::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('the add photo page only offers the resident\'s own points', function () {
    $mine = MonitoringPoint::factory()->create(['label' => 'My kitchen wall']);
    $theirs = MonitoringPoint::factory()->create(['label' => 'Someone else\'s wall']);

    $this->actingAs($mine->user)
        ->get(route('records.create', ['point' => $theirs->id]))
        ->assertOk()
        ->assertSee('My kitchen wall')
        ->assertDontSee('Someone else');
});

test('residents without a monitoring point are asked to create one first', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('records.create'))
        ->assertRedirect(route('points.create'));
});

test('the owner can view a private photo', function () {
    $record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($record->image_path, 'image-bytes');

    $response = $this->actingAs($record->user)->get(route('records.photo', $record));

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->streamedContent())->toBe('image-bytes');
});

test('photos are hidden from guests and other residents', function () {
    $record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($record->image_path, 'image-bytes');

    $this->get(route('records.photo', $record))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('records.photo', $record))->assertNotFound();
});

test('stored photos have no public URL', function () {
    $record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($record->image_path, 'image-bytes');

    expect($this->actingAs($record->user)->get('/storage/'.$record->image_path)->status())->toBeIn([403, 404])
        ->and(file_exists(public_path('storage/'.$record->image_path)))->toBeFalse();
});

test('the owner can delete a photo record and its file', function () {
    $record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($record->image_path, 'image-bytes');

    $this->actingAs($record->user)
        ->delete(route('records.destroy', $record))
        ->assertRedirect(route('points.show', $record->monitoring_point_id));

    expect(PhotoRecord::count())->toBe(0);
    Storage::disk('local')->assertMissing($record->image_path);
});

test('other residents cannot delete a photo record', function () {
    $record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($record->image_path, 'image-bytes');

    $this->actingAs(User::factory()->create())->delete(route('records.destroy', $record))->assertNotFound();

    expect(PhotoRecord::count())->toBe(1);
    Storage::disk('local')->assertExists($record->image_path);
});
