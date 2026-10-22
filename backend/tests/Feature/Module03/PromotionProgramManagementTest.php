<?php

namespace Tests\Feature\Module03;

use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\User;
use App\Models\Venue;
use App\Models\VoucherCode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\OffersVouchers;
use Tests\TestCase;

/**
 * The owner's promotion programs: issuing public and private vouchers, their list and detail, updates and stopping
 * (FR-59..FR-63, BR-49, BR-58..BR-60, BR-62, BR-71; UC-3.8..UC-3.13).
 */
class PromotionProgramManagementTest extends TestCase
{
    use BuildsVenues;
    use OffersVouchers;
    use RefreshDatabase;

    private Venue $venue;

    private User $owner;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->owner = $this->ownerOf($this->venue->organization);
        $this->court = $this->activeCourt($this->venue);
        $this->priceCourt($this->court);
    }

    public function test_fr_60_uc_3_10_owner_issues_a_public_percent_voucher_with_optional_cap_and_minimum(): void
    {
        $this->store($this->terms(['type' => 'public', 'discount_kind' => 'percent', 'percent' => 12.5, 'max_discount' => 50000, 'min_rental' => 200000, 'max_uses' => 10]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'public')
            ->assertJsonPath('data.percent_bp', 1250)
            ->assertJsonPath('data.percent', 12.5)
            ->assertJsonPath('data.max_discount', 50000)
            ->assertJsonPath('data.min_rental', 200000)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.usage', ['held' => 0, 'used' => 0, 'remaining' => 10])
            ->assertJsonPath('data.starts_at', '2026-10-19T01:00:00+00:00')
            ->assertJsonPath('data.courts.0.id', $this->court->id)
            ->assertJsonPath('data.courts.0.name', $this->court->code);

        // Minimum and cap may be left out; a fixed amount is the other discount kind.
        $this->store($this->terms(['type' => 'public', 'discount_kind' => 'fixed', 'amount' => 30000, 'max_uses' => 5]))
            ->assertCreated()
            ->assertJsonPath('data.amount', 30000)
            ->assertJsonPath('data.min_rental', null)
            ->assertJsonPath('data.max_discount', null);
    }

    public function test_fr_61_br_62_uc_3_11_a_private_program_issues_exactly_its_count_of_unique_codes_with_qr_links(): void
    {
        $id = $this->store($this->terms(['type' => 'private', 'discount_kind' => 'fixed', 'amount' => 20000, 'code_count' => 25]))
            ->assertCreated()
            ->assertJsonPath('data.max_uses', null)
            ->assertJsonPath('data.code_count', 25)
            ->assertJsonPath('data.usage.remaining', 25)
            ->json('data.id');

        $codes = $this->actingAs($this->owner)->getJson("/api/admin/promotion-programs/{$id}/codes")->assertOk()->json('data');
        $this->assertCount(25, $codes['codes']);
        $this->assertCount(25, array_unique(array_column($codes['codes'], 'code')));
        $this->assertSame(25, VoucherCode::where('program_id', $id)->count());
        $this->assertSame(['id' => $this->court->id, 'name' => $this->court->code], $codes['court']);
        $first = $codes['codes'][0];
        $this->assertSame('available', $first['state']);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{10}$/', $first['code']);
        // The QR encodes the court's booking page with the code: the same voucher as typing it.
        $this->assertSame("http://localhost:5173/courts/{$this->court->id}/schedule?voucher={$first['code']}", $first['deep_link_url']);
    }

    public function test_br_59_br_60_terms_are_validated(): void
    {
        $this->store([])->assertUnprocessable()->assertJsonValidationErrors(['type', 'discount_kind', 'name', 'starts_at', 'ends_at', 'court_ids']);

        $cases = [
            [['percent' => 0] + $this->percentPublic(), ['percent' => 'Phần trăm giảm phải lớn hơn 0.']],
            [['percent' => 100.01] + $this->percentPublic(), ['percent' => 'Phần trăm giảm không được vượt quá 100.']],
            [['percent' => 12.345] + $this->percentPublic(), ['percent' => 'Phần trăm giảm có tối đa hai chữ số thập phân.']],
            [['max_discount' => 0] + $this->percentPublic(), ['max_discount' => 'Mức giảm tối đa phải lớn hơn 0.']],
            [['min_rental' => 1000.5] + $this->percentPublic(), ['min_rental' => 'Tiền thuê tối thiểu phải là số nguyên VND.']],
            [['max_uses' => 0] + $this->percentPublic(), ['max_uses' => 'Tổng lượt sử dụng phải lớn hơn 0.']],
            [['ends_at' => '2026-10-19T08:00:00+07:00'] + $this->percentPublic(), ['ends_at' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.']],
            [['type' => 'public', 'discount_kind' => 'fixed', 'amount' => 0, 'max_uses' => 1], ['amount' => 'Số tiền giảm phải lớn hơn 0.']],
            [['type' => 'public', 'discount_kind' => 'fixed', 'amount' => 10000, 'max_discount' => 5000, 'max_uses' => 1], ['max_discount' => 'Mức giảm tối đa chỉ dùng cho chương trình giảm theo phần trăm.']],
            [['type' => 'private', 'discount_kind' => 'fixed', 'amount' => 10000, 'code_count' => 0], ['code_count' => 'Số mã phải lớn hơn 0.']],
            [['type' => 'private', 'discount_kind' => 'fixed', 'amount' => 10000], ['code_count' => 'Vui lòng nhập số mã cần phát hành.']],
            [['type' => 'public', 'discount_kind' => 'fixed', 'amount' => 10000], ['max_uses' => 'Vui lòng nhập tổng lượt sử dụng.']],
        ];
        foreach ($cases as [$body, $errors]) {
            $this->store($this->terms($body))->assertUnprocessable()->assertJsonValidationErrors($errors);
        }

        $this->assertSame(0, PromotionProgram::count());
    }

    public function test_br_58_courts_must_belong_to_the_programs_venue(): void
    {
        $elsewhere = $this->activeCourt($this->openVenue(['organization_id' => $this->venue->organization_id]));

        $this->store($this->terms($this->percentPublic(), ['court_ids' => [$this->court->id, $elsewhere->id]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['court_ids' => ['Sân được chọn không thuộc cơ sở của chương trình.']]);
        $this->assertSame(0, PromotionProgram::count());
    }

    public function test_br_49_staff_and_another_organizations_owner_cannot_manage_programs(): void
    {
        $program = $this->publicProgram($this->court);

        foreach ([$this->staffOf($this->venue), $this->ownerOf($this->openVenue()->organization)] as $user) {
            $this->actingAs($user)->getJson("/api/admin/venues/{$this->venue->id}/promotion-programs")->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/venues/{$this->venue->id}/promotion-programs", $this->terms($this->percentPublic()))->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/promotion-programs/{$program->id}")->assertForbidden();
            $this->actingAs($user)->patchJson("/api/admin/promotion-programs/{$program->id}", ['name' => 'Đổi'])->assertForbidden();
            $this->actingAs($user)->postJson("/api/admin/promotion-programs/{$program->id}/stop")->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/promotion-programs/{$program->id}/codes")->assertForbidden();
            $this->actingAs($user)->getJson("/api/admin/promotion-programs/{$program->id}/usage")->assertForbidden();
        }

        $this->assertSame(1, PromotionProgram::count());
        $this->assertNull($program->fresh()->stopped_at);
        $this->assertSame('Ưu đãi 1', $program->fresh()->name);
    }

    public function test_fr_59_uc_3_8_uc_3_9_list_and_detail_show_terms_status_and_usage(): void
    {
        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/promotion-programs")->assertOk()->assertExactJson(['data' => []]);
        $upcoming = $this->publicProgram($this->court, ['starts_at' => now()->addDay(), 'max_uses' => 3]);
        $private = $this->privateProgram($this->court, ['AAAAAAAAAA', 'BBBBBBBBBB'], ['amount' => 20000]);

        $this->actingAs($this->owner)->getJson("/api/admin/venues/{$this->venue->id}/promotion-programs")
            ->assertOk()
            ->assertJsonPath('data.0.id', $private->id)
            ->assertJsonPath('data.0.type', 'private')
            ->assertJsonPath('data.0.discount_kind', 'fixed')
            ->assertJsonPath('data.0.usage', ['held' => 0, 'used' => 0, 'remaining' => 2])
            ->assertJsonPath('data.1.id', $upcoming->id)
            ->assertJsonPath('data.1.status', 'upcoming')
            ->assertJsonPath('data.1.usage.remaining', 3);

        $this->actingAs($this->owner)->getJson("/api/admin/promotion-programs/{$upcoming->id}")
            ->assertOk()
            ->assertJsonPath('data.percent', 10)
            ->assertJsonPath('data.max_uses', 3)
            ->assertJsonPath('data.courts.0.id', $this->court->id);
    }

    public function test_fr_62_uc_3_12_owner_updates_terms_but_not_the_voucher_or_discount_kind(): void
    {
        $program = $this->publicProgram($this->court, ['max_uses' => 5]);
        $other = $this->activeCourt($this->venue);
        $this->priceCourt($other);

        $this->actingAs($this->owner)->patchJson("/api/admin/promotion-programs/{$program->id}", [
            'name' => 'Giờ vàng',
            'percent' => 15,
            'max_uses' => 8,
            'court_ids' => [$this->court->id, $other->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Giờ vàng')
            ->assertJsonPath('data.percent_bp', 1500)
            ->assertJsonPath('data.max_uses', 8)
            ->assertJsonPath('data.courts.1.id', $other->id);

        $this->actingAs($this->owner)->patchJson("/api/admin/promotion-programs/{$program->id}", ['type' => 'private'])
            ->assertUnprocessable()->assertJsonValidationErrors(['type' => 'Không đổi được loại voucher của chương trình đã phát hành.']);
        $this->actingAs($this->owner)->patchJson("/api/admin/promotion-programs/{$program->id}", ['discount_kind' => 'fixed'])
            ->assertUnprocessable()->assertJsonValidationErrors(['discount_kind']);
        $this->actingAs($this->owner)->patchJson("/api/admin/promotion-programs/{$program->id}", ['ends_at' => '2026-10-18T00:00:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['ends_at' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.']);
    }

    public function test_fr_63_br_71_uc_3_13_stopping_keeps_the_program_and_its_codes_and_blocks_further_edits(): void
    {
        $program = $this->privateProgram($this->court, ['AAAAAAAAAA'], ['amount' => 20000]);

        $this->actingAs($this->owner)->postJson("/api/admin/promotion-programs/{$program->id}/stop")
            ->assertOk()
            ->assertJsonPath('data.status', 'stopped');

        $this->actingAs($this->owner)->getJson("/api/admin/promotion-programs/{$program->id}/codes")
            ->assertOk()
            ->assertJsonPath('data.codes.0.code', 'AAAAAAAAAA')
            ->assertJsonPath('data.codes.0.state', 'unusable');
        $this->actingAs($this->owner)->patchJson("/api/admin/promotion-programs/{$program->id}", ['name' => 'Mở lại'])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['program' => ['Chương trình đã dừng nên không sửa được.']]);
        // Stopping again changes nothing; programs are never deleted.
        $this->actingAs($this->owner)->postJson("/api/admin/promotion-programs/{$program->id}/stop")->assertOk();
        $this->assertSame(1, PromotionProgram::whereKey($program->id)->count());
    }

    /** @return array<string, mixed> */
    private function percentPublic(): array
    {
        return ['type' => 'public', 'discount_kind' => 'percent', 'percent' => 10, 'max_uses' => 10];
    }

    /**
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function terms(array $kind, array $overrides = []): array
    {
        return $overrides + $kind + [
            'name' => 'Khuyến mãi tháng 10',
            'starts_at' => '2026-10-19T08:00:00+07:00',
            'ends_at' => '2026-10-31T00:00:00+07:00',
            'court_ids' => [$this->court->id],
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function store(array $body): TestResponse
    {
        return $this->actingAs($this->owner)->postJson("/api/admin/venues/{$this->venue->id}/promotion-programs", $body);
    }
}
