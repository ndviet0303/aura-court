<?php

namespace App\Domain\Promotion\Actions;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Promotion\DTOs\UpdatePromotionProgramData;
use App\Events\PromotionProgramChanged;
use App\Models\Court;
use App\Models\PromotionProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes the editable terms of an issued program, its discount kind too (UC-3.12); total uses never go below the uses
 * held and used. Confirmed bookings keep the discount stored on their voucher use (BR-71).
 * Lock order follows checkout (D11): every court of a submitted set FOR SHARE in id order, not only those that differ
 * before the lock (another update may commit first), then the program row FOR UPDATE, which waits for a checkout
 * holding it (D17); new court rows lock their courts through the key. Three attempts, as on creation.
 * Every committed update, a rename too, then re-checks the bookings pending payment that hold its voucher (D19).
 */
class UpdatePromotionProgram
{
    public function __construct(
        private readonly PromotionProgramRepositoryInterface $programs,
    ) {}

    public function execute(UpdatePromotionProgramData $data): PromotionProgram
    {
        $program = DB::transaction(function () use ($data): PromotionProgram {
            $courts = $data->courtIds === null ? null : $this->programs->lockCourts($data->courtIds);
            $program = $this->programs->findForUpdate($data->programId) ?? abort(404);
            if ($program->stopped_at !== null) {
                throw ValidationException::withMessages(['program' => 'Chương trình đã dừng nên không sửa được.']);
            }
            if ($courts !== null && ($courts->count() !== count($data->courtIds)
                || $courts->contains(fn (Court $court): bool => (int) $court->venue_id !== (int) $program->venue_id))) {
                throw ValidationException::withMessages(['court_ids' => 'Sân được chọn không thuộc cơ sở của chương trình.']);
            }
            $terms = $this->withDiscountKind($program, $data);
            $this->assertWindowAndUses($program, $terms);

            $this->programs->update($program, $terms);
            if ($data->courtIds !== null) {
                $this->programs->syncCourts($program, $data->courtIds);
            }

            return $program;
        }, 3);
        PromotionProgramChanged::dispatch((int) $program->id);

        return $program;
    }

    /**
     * A new discount kind drops the old kind's terms, one kind per voucher (BR-60).
     *
     * @return array<string, mixed>
     */
    private function withDiscountKind(PromotionProgram $program, UpdatePromotionProgramData $data): array
    {
        if ($data->discountKind === $program->discount_kind) {
            return $data->terms;
        }
        $cleared = $data->discountKind === PromotionProgram::KIND_PERCENT
            ? ['amount' => null]
            : ['percent_bp' => null, 'max_discount' => null];

        return ['discount_kind' => $data->discountKind, ...$cleared, ...$data->terms];
    }

    /** @param  array<string, mixed>  $terms */
    private function assertWindowAndUses(PromotionProgram $program, array $terms): void
    {
        $startsAt = $terms['starts_at'] ?? $program->starts_at;
        $endsAt = $terms['ends_at'] ?? $program->ends_at;
        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.']);
        }

        if (isset($terms['max_uses'])) {
            // Under the program lock a hold cannot add a use; a release only lowers the count.
            $this->programs->loadDetail($program);
            $taken = (int) $program->getAttribute('held_uses_count') + (int) $program->getAttribute('used_uses_count');
            if ($terms['max_uses'] < $taken) {
                throw ValidationException::withMessages(['max_uses' => "Tổng lượt không được nhỏ hơn {$taken} lượt đang giữ và đã dùng."]);
            }
        }
    }
}
