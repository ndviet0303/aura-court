<?php

namespace Tests\Feature\Module02;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-44; design D13 (monitoring). UC-2.24, UC-2.25. */
class PlatformVenueMonitoringTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    public function test_fr_44_cross_organization_list_and_detail(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $venue->forceFill(['phone' => '0281234567', 'email' => 'san@example.test'])->save();
        $owner = $this->ownerOf($venue->organization);
        $other = $this->openVenue(['name' => 'Sân Bình Thạnh']);
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->postJson("/api/admin/venues/{$other->id}/suspensions", ['reason' => 'Vi phạm quy định an toàn'])->assertCreated();

        $ids = fn (string $query) => collect($this->actingAs($admin)->getJson('/api/admin/monitoring/venues'.$query)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($venue->id, $ids(''));
        $this->assertContains($other->id, $ids(''));
        $this->assertSame([$other->id], $ids('?suspended=1'));
        $this->assertContains($venue->id, $ids('?suspended=0'));
        $this->assertNotContains($other->id, $ids('?suspended=0'));
        $this->actingAs($admin)->getJson("/api/admin/monitoring/venues?organization_id={$other->organization_id}")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/admin/monitoring/venues?keyword=Bình Thạnh')->assertOk()->assertJsonPath('data.0.id', $other->id);
        $this->actingAs($admin)->getJson('/api/admin/monitoring/venues?status=paused')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($admin)->getJson("/api/admin/monitoring/venues/{$venue->id}")->assertOk()
            ->assertJsonPath('data.organization.id', $venue->organization_id)
            ->assertJsonPath('data.owners.0.id', $owner->id)
            ->assertJsonPath('data.phone', '0281234567')
            ->assertJsonPath('data.email', 'san@example.test')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.active_suspension', null)
            ->assertJsonPath('data.courts.0.code', $court->code)
            ->assertJsonPath('data.courts.0.schedule_inherited', true)
            ->assertJsonStructure(['data' => ['address', 'operating_hours', 'suspensions', 'courts' => [['sport_type', 'description', 'images', 'service_schedule', 'status']]]]);
        $this->actingAs($admin)->getJson("/api/admin/monitoring/venues/{$other->id}")->assertOk()
            ->assertJsonPath('data.active_suspension.reason', 'Vi phạm quy định an toàn')
            ->assertJsonPath('data.suspensions.0.suspended_by.id', $admin->id);
    }

    public function test_fr_44_every_view_is_audited(): void
    {
        $venue = $this->openVenue();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->getJson('/api/admin/monitoring/venues?keyword=abc')->assertOk();
        $this->actingAs($admin)->getJson("/api/admin/monitoring/venues/{$venue->id}")->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', 'venue.monitoring.listed')->where('user_id', $admin->id)->count());
        $viewed = AuditLog::query()->where('action', 'venue.monitoring.viewed')->sole();
        $this->assertSame([$admin->id, $venue->id], [(int) $viewed->user_id, (int) $viewed->auditable_id]);
    }

    public function test_fr_44_no_editing_through_monitoring(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->putJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}", ['name' => 'Đổi tên'])->assertForbidden();
        $this->actingAs($admin)->putJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/operating-schedule", [])->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/courts/{$court->id}/pause", ['reason' => 'Sự cố mất điện cần sửa', 'kind' => 'maintenance'])->assertForbidden();
        $this->assertContains($this->actingAs($admin)->putJson("/api/admin/monitoring/venues/{$venue->id}", ['name' => 'x'])->status(), [404, 405]);
        $this->assertNotSame('Đổi tên', $venue->fresh()->name);
    }

    public function test_fr_44_non_admin_access(): void
    {
        $venue = $this->openVenue();

        foreach ([$this->ownerOf($venue->organization), $this->staffOf($venue), User::factory()->create()] as $user) {
            $this->actingAs($user)->getJson('/api/admin/monitoring/venues')->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/monitoring/venues/{$venue->id}")->assertForbidden();
        }
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'venue.monitoring.%')->count());
    }
}
