<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoAnalysis;
use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

test('a point lists its baseline first, then numbered follow-ups in date order', function () {
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(20), 'note' => 'First look']);
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now(), 'note' => 'Third look']);
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(10), 'note' => 'Second look']);

    $this->actingAs($point->user)->get(route('points.show', $point))
        ->assertOk()
        ->assertSee('Observation passport')
        ->assertSee('Add follow-up photo')
        ->assertSeeInOrder(['Baseline', 'First look', 'Follow-up 1', 'Second look', 'Follow-up 2', 'Third look']);
});

test('a point without photos asks for a baseline and has no report link', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->get(route('points.show', $point))
        ->assertOk()
        ->assertSee('Add baseline photo')
        ->assertDontSee(route('points.report', $point));
});

test('a point with only its baseline shows the first follow-up as one compact step, with nothing to compare yet', function () {
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->for($point)->create();

    $this->actingAs($point->user)->get(route('points.show', $point))
        ->assertOk()
        ->assertSeeInOrder(['Baseline photo', 'Follow-up 1', 'not taken yet', 'Add follow-up photo', 'Observation passport'])
        // No way to compare yet (RISE Help's built-in answers name the button on every page, so the link is checked)
        ->assertDontSee(route('points.compare', $point));
});

test('photo dates are labelled by source: saved to RISE, photo taken (unverified) and, for older uploads, file modified', function () {
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(3), 'file_modified_at' => now()->subDays(4)]);
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(2), 'photo_taken_at' => '2026-10-20 14:05:00', 'photo_taken_offset' => '+08:00']);
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDay()]);

    $this->actingAs($point->user)->get(route('points.show', $point))
        ->assertSee('Saved to RISE')
        ->assertSeeInOrder(['Baseline', 'File modified', 'Unverified', 'Follow-up 1', 'Photo taken', '20 Oct 2026, 14:05', 'UTC+08:00', 'Unverified', 'Follow-up 2', 'Photo taken', 'Not provided'])
        ->assertDontSee('Device time');
});

test('a follow-up gets the framing checklist and the previous photo overlay', function () {
    $point = MonitoringPoint::factory()->create();
    $previous = PhotoRecord::factory()->for($point)->create();

    $this->actingAs($point->user)->get(route('records.create', ['point' => $point]))
        ->assertOk()
        ->assertSee('Add follow-up photo')
        ->assertSee('Return to the same spot.')
        ->assertSee('Previous photo overlay')
        ->assertSee('Follow-up 1')
        ->assertSee(route('records.photo', $previous));
});

test('the first photo of a point is taken as its baseline', function () {
    $point = MonitoringPoint::factory()->create();

    $this->actingAs($point->user)->get(route('records.create', ['point' => $point]))
        ->assertOk()
        ->assertSee('Add baseline photo');
});

test('saving a photo goes to the page of the history that holds it', function () {
    Storage::fake('local');
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->count(12)->for($point)->create(['submitted_at' => now()->subDay()]);

    $response = $this->actingAs($point->user)->post(route('records.store'), [
        'monitoring_point_id' => $point->id,
        'photo' => UploadedFile::fake()->image('follow-up.jpg', 1200, 900),
    ]);

    $record = PhotoRecord::latest('submitted_at')->latest('id')->first();
    $response->assertRedirect(route('points.show', [$point, 'page' => 2]).'#record-'.$record->id);
});

test('the report shows the owner all photos in date order with notes and the documentation statement', function () {
    $point = MonitoringPoint::factory()->create(['label' => 'Stair wall by the landing', 'surface_description' => 'Painted concrete']);
    $later = PhotoRecord::factory()->for($point)->create(['submitted_at' => now(), 'note' => 'Looks the same']);
    $earlier = PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subWeek(), 'note' => 'Hairline near the step', 'photo_taken_at' => now()->subWeek()->subHour()]);

    $this->actingAs($point->user)->get(route('points.report', $point))
        ->assertOk()
        ->assertSee('Stair wall by the landing')
        ->assertSee('Painted concrete')
        ->assertSee('All 2 photos of this spot')
        ->assertSeeInOrder([route('records.photo', $earlier), route('records.photo', $later)])
        ->assertSeeTextInOrder(['Baseline', 'Photo taken', 'Unverified', 'Hairline near the step', 'Follow-up 1', 'Not provided', 'Looks the same'])
        ->assertSee('not a structural diagnosis or a safety certification')
        ->assertDontSee('Part 1 of')
        ->assertDontSee('AI image check');
});

test('a long history prints in labelled parts that say which photos they hold', function () {
    $point = MonitoringPoint::factory()->create();
    foreach (range(1, 21) as $day) {
        PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(30 - $day), 'note' => "Observation note {$day}"]);
    }

    $this->actingAs($point->user)->get(route('points.report', $point))
        ->assertOk()
        ->assertSee('Part 1 of 2: photos 1–20 of 21')
        ->assertSee('Observation note 20')
        ->assertDontSee('Observation note 21')
        ->assertDontSee('All 21 photos');

    $this->get(route('points.report', [$point, 'part' => 2]))
        ->assertOk()
        ->assertSee('Part 2 of 2: photo 21 of 21')
        ->assertSee('Follow-up 20')
        ->assertSee('Observation note 21')
        ->assertDontSee('Observation note 20');

    $this->get(route('points.report', [$point, 'part' => 9]))->assertRedirect(route('points.report', [$point, 'part' => 2]));
});

test('the report includes AI output only from a finished analysis', function () {
    $point = MonitoringPoint::factory()->create();
    $analysed = PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDay()]);
    $failed = PhotoRecord::factory()->for($point)->create();
    PhotoAnalysis::factory()->for($analysed, 'photoRecord')->create([
        'status' => PhotoAnalysis::COMPLETED, 'region_count' => 2, 'completed_at' => now(), 'result' => ['regions' => []],
    ]);
    PhotoAnalysis::factory()->for($failed, 'photoRecord')->create(['status' => PhotoAnalysis::FAILED, 'error_code' => 'rate_limited']);

    $this->actingAs($point->user)->get(route('points.report', $point))
        ->assertOk()
        ->assertSee('The model marked 2 possible crack-like areas for the resident to review.')
        ->assertSee('AI image results can be wrong.')
        ->assertSeeTextInOrder(['Baseline', 'AI image check', 'Follow-up 1'])
        ->assertDontSee('rate_limited');
});

test('another resident cannot open a point’s report, and guests are sent to log in', function () {
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->for($point)->create(['note' => 'Private observation']);

    $this->get(route('points.report', $point))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('points.report', $point))
        ->assertNotFound()
        ->assertDontSee('Private observation');
});
