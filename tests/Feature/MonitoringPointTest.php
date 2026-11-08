<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
});

test('a resident can create a monitoring point and is taken to add its first photo', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('points.store'), [
        'label' => 'Bedroom wall beside the window',
        'surface_description' => 'Painted plaster',
    ]);

    $point = MonitoringPoint::sole();

    $response->assertRedirect(route('records.create', ['point' => $point]));
    expect($point->user_id)->toBe($user->id)
        ->and($point->label)->toBe('Bedroom wall beside the window');
});

test('a monitoring point needs a short label', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('points.store'), ['label' => ''])->assertSessionHasErrors('label');
    $this->post(route('points.store'), ['label' => str_repeat('a', 81)])->assertSessionHasErrors('label');

    expect(MonitoringPoint::count())->toBe(0);
});

test('the owner can view, edit and update a monitoring point', function () {
    $point = MonitoringPoint::factory()->create(['label' => 'Hallway ceiling corner']);

    $this->actingAs($point->user)->get(route('points.show', $point))->assertOk()->assertSee('Hallway ceiling corner');
    $this->get(route('points.edit', $point))->assertOk();
    $this->put(route('points.update', $point), ['label' => 'Hallway ceiling, left corner'])
        ->assertRedirect(route('points.show', $point));

    expect($point->fresh()->label)->toBe('Hallway ceiling, left corner');
});

test('other residents cannot see or change a monitoring point', function () {
    $point = MonitoringPoint::factory()->create(['label' => 'Private spot']);

    $this->actingAs(User::factory()->create());

    $this->get(route('points.show', $point))->assertNotFound();
    $this->get(route('points.edit', $point))->assertNotFound();
    $this->get(route('points.compare', $point))->assertNotFound();
    $this->put(route('points.update', $point), ['label' => 'Changed'])->assertNotFound();
    $this->delete(route('points.destroy', $point))->assertNotFound();
    $this->get(route('overview'))->assertOk()->assertDontSee('Private spot');

    expect($point->fresh()->label)->toBe('Private spot');
});

test('deleting a monitoring point removes its records and photo files', function () {
    $record = PhotoRecord::factory()->create();
    Storage::disk('local')->put($record->image_path, 'image-bytes');
    $point = $record->monitoringPoint;

    $this->actingAs($point->user)->delete(route('points.destroy', $point))->assertRedirect(route('overview'));

    expect(MonitoringPoint::count())->toBe(0)
        ->and(PhotoRecord::count())->toBe(0);
    Storage::disk('local')->assertMissing($record->image_path);
});

test('the overview lists only the resident\'s own points', function () {
    $user = User::factory()->create();
    $photographed = MonitoringPoint::factory()->for($user)->create(['label' => 'Kitchen wall above the sink']);
    PhotoRecord::factory()->for($photographed)->create();
    MonitoringPoint::factory()->for($user)->create(['label' => 'Garage floor near the drain']);
    MonitoringPoint::factory()->create(['label' => 'A stranger\'s hallway']);

    $this->actingAs($user)->get(route('overview'))
        ->assertOk()
        ->assertSee('Kitchen wall above the sink')
        ->assertSee('1 photo')
        ->assertSee('Garage floor near the drain')
        ->assertSee('No photos yet')
        ->assertDontSee('A stranger');
});

test('the overview explains how to start when there are no points', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('overview'))
        ->assertOk()
        ->assertSee('Start with one spot')
        ->assertSee('Add photo');
});
