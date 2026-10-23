<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\User;
use App\Models\VoucherCode;
use App\Models\VoucherUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OffersVouchers;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-111, BR-143 (UC-7.3): the owner gifts a free private code of the venue through chat. */
class VoucherGiftTest extends TestCase
{
    use OffersVouchers, ServesCustomers;
    use RefreshDatabase;

    private Court $court;

    private User $owner;

    private int $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->court = $this->bookableCourt();
        $this->owner = $this->ownerOf($this->court->venue->organization);
        $this->conversation = $this->actingAs($this->owner)->postJson('/api/conversations', [
            'type' => 'customer_venue', 'venue_id' => $this->court->venue_id, 'user_id' => User::factory()->create()->id,
        ])->json('conversation.id');
    }

    public function test_fr_111_br_143_the_owner_gifts_a_running_private_code_with_its_terms_and_it_stays_unused(): void
    {
        $this->privateProgram($this->court, ['TRIAN01'], ['min_rental' => 200000]);

        $this->actingAs($this->owner)->getJson("/api/venues/{$this->court->venue_id}/gift-vouchers")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'TRIAN01');
        $this->postJson("/api/conversations/{$this->conversation}/vouchers", ['code' => 'trian01', 'note' => 'Cảm ơn bạn!'])
            ->assertCreated()
            ->assertJsonPath('message.voucher.code', 'TRIAN01')
            ->assertJsonPath('message.voucher.percent_bp', 1000)
            ->assertJsonPath('message.voucher.min_rental', 200000)
            ->assertJsonPath('message.voucher.courts', [$this->court->name])
            ->assertJsonPath('message.body', 'Cảm ơn bạn!');
        $this->assertSame(0, VoucherUse::count());
    }

    public function test_br_143_only_the_owner_gifts(): void
    {
        $this->privateProgram($this->court, ['TRIAN01']);
        $staff = $this->staffOf($this->court->venue);

        $this->actingAs($staff)->postJson("/api/conversations/{$this->conversation}/vouchers", ['code' => 'TRIAN01'])
            ->assertForbidden()->assertJsonPath('code', 'VOUCHER_OWNER_ONLY');
        $this->getJson("/api/venues/{$this->court->venue_id}/gift-vouchers")->assertForbidden();
    }

    public function test_br_143_used_expired_or_other_venue_codes_are_not_giftable(): void
    {
        $program = $this->privateProgram($this->court, ['DADUNG', 'CONLAI']);
        VoucherUse::create(['reservation_id' => $this->booking($this->court, now()->addDay())->id, 'program_id' => $program->id, 'voucher_code_id' => VoucherCode::where('code', 'DADUNG')->value('id'), 'status' => VoucherUse::STATUS_USED, 'discount' => 10000, 'held_at' => now(), 'used_at' => now()]);
        $this->privateProgram($this->court, ['HETHAN'], ['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
        $this->privateProgram($this->bookableCourt(), ['COSOKHAC']);
        $this->actingAs($this->owner);

        foreach (['DADUNG', 'HETHAN', 'COSOKHAC', 'KHONGCO'] as $code) {
            $this->postJson("/api/conversations/{$this->conversation}/vouchers", ['code' => $code])
                ->assertUnprocessable()->assertJsonPath('code', 'VOUCHER_NOT_GIFTABLE');
        }
        $this->getJson("/api/venues/{$this->court->venue_id}/gift-vouchers")->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'CONLAI');
    }
}
