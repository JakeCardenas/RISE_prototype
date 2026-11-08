<?php

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
});

// A point with one photo saved three weeks ago, planned for a follow-up on the given date
function plannedPoint(User $user, string $label, ?string $followUpOn): MonitoringPoint
{
    $point = MonitoringPoint::factory()->for($user)->create(['label' => $label, 'follow_up_on' => $followUpOn]);
    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subWeeks(3)]);

    return $point;
}

test('a point can be filed under a listed room or an area the resident names', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('points.store'), ['label' => 'Wall by the bed', 'area' => 'Bedroom'])->assertRedirect();
    $this->post(route('points.store'), ['label' => 'Wall by the washer', 'area' => 'other', 'area_other' => 'Laundry area'])->assertRedirect();
    $this->post(route('points.store'), ['label' => 'Ceiling corner', 'area' => 'other', 'area_other' => ' kitchen '])->assertRedirect();
    $this->post(route('points.store'), ['label' => 'Gate post', 'area' => ''])->assertRedirect();

    expect(MonitoringPoint::orderBy('label')->pluck('area', 'label')->all())->toBe([
        'Ceiling corner' => 'Kitchen',
        'Gate post' => null,
        'Wall by the bed' => 'Bedroom',
        'Wall by the washer' => 'Laundry area',
    ]);
});

test('an area is one of the listed rooms or a short name of the resident\'s own', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('points.store'), ['label' => 'Spot', 'area' => 'Unit 4B'])->assertSessionHasErrors('area');
    $this->post(route('points.store'), ['label' => 'Spot', 'area' => 'other'])
        ->assertSessionHasErrors(['area_other' => 'Name the area, such as “Laundry area” or “Bedroom 2”.']);
    $this->post(route('points.store'), ['label' => 'Spot', 'area' => 'other', 'area_other' => str_repeat('a', 41)])->assertSessionHasErrors('area_other');
    $this->post(route('points.store'), ['label' => 'Spot', 'follow_up_on' => 'next week'])->assertSessionHasErrors('follow_up_on');

    expect(MonitoringPoint::count())->toBe(0);
});

test('editing a point shows and changes its area and follow-up date', function () {
    $point = MonitoringPoint::factory()->create(['area' => 'Laundry area', 'follow_up_on' => '2026-12-01']);

    $this->actingAs($point->user)->get(route('points.edit', $point))
        ->assertOk()
        ->assertSee('value="Laundry area"', false)
        ->assertSee('value="2026-12-01"', false);

    $this->put(route('points.update', $point), ['label' => $point->label, 'area' => 'Hallway', 'follow_up_on' => ''])
        ->assertRedirect(route('points.show', $point));

    expect($point->fresh())->area->toBe('Hallway')->follow_up_on->toBeNull();
});

test('a follow-up date can be planned in one tap, picked from the calendar, or cleared', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00', 'UTC'));
    $point = plannedPoint(User::factory()->create(), 'Hallway ceiling', null);
    $this->actingAs($point->user);

    $this->patch(route('points.follow-up.update', $point), ['in_days' => 30])
        ->assertRedirect(route('points.show', $point).'#follow-up')
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Wednesday 9 December 2026'));
    expect($point->fresh()->follow_up_on->toDateString())->toBe('2026-12-09');

    $this->patch(route('points.follow-up.update', $point), ['follow_up_on' => '2027-01-15']);
    expect($point->fresh()->follow_up_on->toDateString())->toBe('2027-01-15');

    $this->patch(route('points.follow-up.update', $point), ['in_days' => 3])->assertSessionHasErrors('in_days');
    $this->patch(route('points.follow-up.update', $point), ['follow_up_on' => 'soon'])->assertSessionHasErrors('follow_up_on');

    $this->delete(route('points.follow-up.destroy', $point))->assertRedirect(route('points.show', $point).'#follow-up');
    expect($point->fresh()->follow_up_on)->toBeNull();

    $this->get(route('points.show', $point))->assertOk()->assertSee('No follow-up date planned')->assertSee('In 1 month');
});

test('other residents cannot plan or clear a follow-up', function () {
    $point = MonitoringPoint::factory()->create(['follow_up_on' => '2026-12-01']);

    $this->actingAs(User::factory()->create());

    $this->patch(route('points.follow-up.update', $point), ['in_days' => 7])->assertNotFound();
    $this->delete(route('points.follow-up.destroy', $point))->assertNotFound();

    expect($point->fresh()->follow_up_on->toDateString())->toBe('2026-12-01');
});

test('the overview suggests the next step from the resident\'s own records', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('overview'))->assertSee('Start with one spot')->assertSee(route('points.create'));

    $point = MonitoringPoint::factory()->for($user)->create(['label' => 'Bedroom wall']);
    $this->get(route('overview'))->assertSee('Add a baseline photo of Bedroom wall');

    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDays(10)]);
    $this->get(route('overview'))->assertSee('Take the first follow-up of Bedroom wall')->assertSee('Plan a follow-up date');

    PhotoRecord::factory()->for($point)->create(['submitted_at' => now()->subDay()]);
    $this->get(route('overview'))->assertSee('Compare Bedroom wall')->assertSee('1 week 2 days after the baseline');

    $point->update(['follow_up_on' => now()->toDateString()]);
    $this->get(route('overview'))->assertSee('Follow-up for Bedroom wall')->assertSee('Due today');
});

test('follow-ups appear only for dates the resident chose, labelled by how near they are', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00', 'UTC'));
    $user = User::factory()->create();
    plannedPoint($user, 'Past spot', '2026-11-02');
    plannedPoint($user, 'Today spot', '2026-11-09');
    plannedPoint($user, 'Soon spot', '2026-11-13');
    plannedPoint($user, 'Later spot', '2026-12-20');
    plannedPoint($user, 'Unplanned spot', null);
    MonitoringPoint::factory()->create(['label' => 'Stranger spot', 'follow_up_on' => '2026-11-09']);

    $response = $this->actingAs($user)->get(route('overview'))->assertOk()->assertDontSee('Stranger spot');

    // The earliest missed date is the next step; the other near dates are listed below it, earliest first
    $response->assertSeeInOrder(['Next step', 'Follow-up for Past spot', 'Past due', 'Planned follow-ups', 'Due today', 'Today spot', 'Due in 4 days', 'Soon spot']);
    expect(Str::between($response->getContent(), 'Planned follow-ups', 'Recent observations'))
        ->not->toContain('Later spot')
        ->not->toContain('Unplanned spot');
});

test('"due today" follows the resident\'s own time zone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 18:00', 'UTC')); // already 10 November in Manila
    $point = plannedPoint(User::factory()->create(), 'Stair wall', '2026-11-10');
    $this->actingAs($point->user);

    $this->get(route('overview'))->assertSee('Due tomorrow')->assertDontSee('Due today');
    $this->withUnencryptedCookie('rise_tz', 'Asia/Manila')->get(route('overview'))->assertSee('Due today');
    $this->withUnencryptedCookie('rise_tz', 'Not/A_Zone')->get(route('overview'))->assertSee('Due tomorrow');
});

test('saving a photo completes a follow-up once its day has arrived, but keeps a date still ahead', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00', 'UTC'));
    $due = MonitoringPoint::factory()->create(['follow_up_on' => '2026-11-06']);
    $soon = MonitoringPoint::factory()->for($due->user)->create(['follow_up_on' => '2026-11-12']);
    $this->actingAs($due->user);

    $this->get(route('records.create', ['point' => $due]))->assertSee('Saving it completes the follow-up you planned for Fri 6 Nov.');
    $this->get(route('records.create', ['point' => $soon]))->assertDontSee('planned for Thu 12 Nov');

    $this->post(route('records.store'), ['monitoring_point_id' => $due->id, 'photo' => UploadedFile::fake()->image('a.jpg', 800, 600)])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'completes the follow-up you planned for 6 November'));
    // A baseline taken today leaves next week's plan in place
    $this->post(route('records.store'), ['monitoring_point_id' => $soon->id, 'photo' => UploadedFile::fake()->image('b.jpg', 800, 600)]);

    expect($due->fresh()->follow_up_on)->toBeNull()
        ->and($soon->fresh()->follow_up_on->toDateString())->toBe('2026-11-12');
});

test('the overview groups points by area, listed rooms first, with a link to each point', function () {
    $user = User::factory()->create();
    $gate = MonitoringPoint::factory()->for($user)->create(['label' => 'Gate post', 'area' => 'Exterior']);
    MonitoringPoint::factory()->for($user)->create(['label' => 'Washer wall', 'area' => 'Laundry area']);
    MonitoringPoint::factory()->for($user)->create(['label' => 'Bed wall', 'area' => 'Bedroom']);
    MonitoringPoint::factory()->for($user)->create(['label' => 'Loose spot']);

    $this->actingAs($user)->get(route('overview'))
        ->assertOk()
        ->assertSee('href="#area-bedroom"', false)
        ->assertSee(route('points.show', $gate))
        ->assertSee(route('points.create', ['area' => 'Laundry area']))
        ->assertSeeInOrder(['My home areas', 'id="area-bedroom"', 'Bed wall', 'id="area-exterior"', 'Gate post', 'id="area-laundry-area"', 'Washer wall', 'id="area-no-area-yet"', 'Loose spot'], false);
});
