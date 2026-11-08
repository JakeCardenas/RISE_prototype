<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

test('the owner can compare two photos of a point, earlier one first', function () {
    $point = MonitoringPoint::factory()->create();
    $older = PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(30)]);
    $newer = PhotoRecord::factory()->for($point)->create(['submitted_at' => now()]);

    // Chosen in reverse order, still shown earlier first
    $this->actingAs($point->user)
        ->get(route('points.compare', [$point, 'first' => $newer->id, 'second' => $older->id]))
        ->assertOk()
        ->assertSeeInOrder([route('records.photo', $older), route('records.photo', $newer)])
        ->assertSee('visual, side-by-side view only')
        ->assertDontSee('severity');
});

test('comparing only accepts photos from the same point', function () {
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->count(2)->for($point)->create();
    $elsewhere = PhotoRecord::factory()->for(MonitoringPoint::factory()->for($point->user))->create();

    $this->actingAs($point->user)
        ->get(route('points.compare', [$point, 'first' => $elsewhere->id]))
        ->assertNotFound();
});

test('choosing the same photo twice asks for two different photos', function () {
    $point = MonitoringPoint::factory()->create();
    $record = PhotoRecord::factory()->count(2)->for($point)->create()->first();

    $this->actingAs($point->user)
        ->get(route('points.compare', [$point, 'first' => $record->id, 'second' => $record->id]))
        ->assertOk()
        ->assertSee('Choose two different photos to compare.');
});

test('comparison needs at least two photos', function () {
    $point = MonitoringPoint::factory()->create();
    PhotoRecord::factory()->for($point)->create();

    $this->actingAs($point->user)
        ->get(route('points.compare', $point))
        ->assertOk()
        ->assertSee('Add another photo to compare');
});

test('other residents cannot open a comparison', function () {
    $point = MonitoringPoint::factory()->create();
    $records = PhotoRecord::factory()->count(2)->for($point)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('points.compare', [$point, 'first' => $records[0]->id, 'second' => $records[1]->id]))
        ->assertNotFound();
});
