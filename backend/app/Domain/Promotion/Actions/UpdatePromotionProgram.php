<?php

namespace App\Domain\Promotion\Actions;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Promotion\DTOs\PromotionProgramChange;
use App\Domain\Promotion\DTOs\UpdatePromotionProgramData;
use App\Models\Court;
use App\Models\PromotionProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes the editable terms of an issued program (design D14); total uses never go below the uses held and used.
 * Lock order follows checkout (D11): every court of a submitted set FOR SHARE in id order, not only those that differ
 * before the lock (another update may commit first), then the program row FOR UPDATE, which waits for a checkout
 * holding it (D17); new court rows lock their courts through the key. Three attempts, as on creation.
 * Re-pricing the bookings pending payment after the commit (D19) belongs to task 6.2 and keys on $termsChanged.
 */
class UpdatePromotionProgram
{
    public function __construct(
        private readonly PromotionProgramRepositoryInterface $programs,
    ) {}

    public function execute(UpdatePromotionProgramData $data): PromotionProgramChange
    {
        return DB::transaction(function () use ($data): PromotionProgramChange {
            $courts = $data->courtIds === null ? null : $this->programs->lockCourts($data->courtIds);
            $program = $this->programs->findForUpdate($data->programId) ?? abort(404);
            if ($program->stopped_at !== null) {
                throw ValidationException::withMessages(['program' => 'Chương trình đã dừng nên không sửa được.']);
            }
            if ($courts !== null && ($courts->count() !== count($data->courtIds)
                || $courts->contains(fn (Court $court): bool => (int) $court->venue_id !== (int) $program->venue_id))) {
                throw ValidationException::withMessages(['court_ids' => 'Sân được chọn không thuộc cơ sở của chương trình.']);
            }
            $this->assertWindowAndUses($program, $data->terms);

            $this->programs->update($program, $data->terms);
            $termsChanged = $program->wasChanged(PromotionProgram::PRICING_TERMS);
            if ($data->courtIds !== null) {
                $termsChanged = $this->programs->syncCourts($program, $data->courtIds) || $termsChanged;
            }

            return new PromotionProgramChange($program, $termsChanged);
        }, 3);
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
