<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Services\Photos\PhotoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
});

// A synthetic JPEG whose EXIF block carries a GPS section, built in memory
function jpegWithLocation(): string
{
    $image = imagecreatetruecolor(640, 480);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 190, 180));
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();

    // Little-endian TIFF: IFD0 points to a GPS IFD holding GPSVersionID and GPSLatitudeRef
    $tiff = "II*\0".pack('V', 8)
        .pack('v', 1).pack('vvVV', 0x8825, 4, 1, 26).pack('V', 0)
        .pack('v', 2).pack('vvV', 0x0000, 1, 4)."\x02\x02\x00\x00".pack('vvV', 0x0001, 2, 2)."N\0\0\0".pack('V', 0);
    $exif = "Exif\0\0".$tiff;

    return "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
}

function sampleHeic(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/sample.heic'), 'IMG_0001.HEIC', 'image/heic', null, true);
}

test('a HEIC photo is kept privately and shown as a JPEG copy', function () {
    if (! app(PhotoProcessor::class)->supportsHeic()) {
        $this->markTestSkipped('Imagick cannot decode HEIC on this machine.');
    }

    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)
        ->post(route('records.store'), ['monitoring_point_id' => $point->id, 'photo' => sampleHeic()])
        ->assertSessionHasNoErrors();

    $record = PhotoRecord::sole();

    expect($record->image_mime)->toBe('image/heic')
        ->and($record->image_path)->toEndWith('.heic')
        ->and($record->display_mime)->toBe('image/jpeg')
        ->and($record->display_reason)->toBe('converted_from_heic')
        ->and([$record->display_width, $record->display_height])->toBe([640, 480]);
    Storage::disk('local')->assertExists([$record->image_path, $record->display_path]);

    $served = $this->get(route('records.photo', $record))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->streamedContent();

    expect(substr($served, 0, 3))->toBe("\xFF\xD8\xFF");
    $this->get(route('points.show', $point))->assertSee('Converted from HEIC to JPEG');
});

test('HEIC photos are refused with export advice where they cannot be converted', function () {
    config(['rise.heic_conversion' => false]);
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)
        ->post(route('records.store'), ['monitoring_point_id' => $point->id, 'photo' => sampleHeic()])
        ->assertSessionHasErrors('photo');

    expect(session('errors')->first('photo'))->toContain('Export 1 Photo')
        ->and(PhotoRecord::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('location details stored in a photo file are never shown', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->createWithContent('IMG_0002.jpg', jpegWithLocation()),
    ])->assertSessionHasNoErrors();

    $record = PhotoRecord::sole();
    $served = $this->get(route('records.photo', $record))->assertOk()->streamedContent();

    expect($record->display_reason)->toBe('location_removed')
        ->and(@exif_read_data('data://image/jpeg;base64,'.base64_encode($served), 'GPS'))->toBeFalse()
        // The original is preserved privately, exactly as uploaded
        ->and(@exif_read_data(Storage::disk('local')->path($record->image_path), 'GPS'))->not->toBeFalse();
});

test('photos without location details are shown exactly as uploaded', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('plain.jpg', 800, 600),
    ]);

    $record = PhotoRecord::sole();

    expect($record->display_path)->toBeNull()
        ->and($record->display_reason)->toBeNull()
        ->and($this->get(route('records.photo', $record))->streamedContent())
        ->toBe(Storage::disk('local')->get($record->image_path));
});

test('deleting a photo removes the original and its JPEG copy', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->createWithContent('IMG_0003.jpg', jpegWithLocation()),
    ]);

    $record = PhotoRecord::sole();
    $this->delete(route('records.destroy', $record))->assertRedirect();

    Storage::disk('local')->assertMissing([$record->image_path, $record->display_path]);
});
