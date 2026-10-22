<?php

namespace App\Domain\Auth\Actions;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Domain\Auth\DTOs\CompletePersonalOnboardingData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompletePersonalOnboarding
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
    ) {}

    /**
     * @return array{
     *     user: User,
     *     account_type: string,
     *     next_step: string,
     *     partner_registration_url: ?string,
     *     message: string
     * }
     */
    public function execute(CompletePersonalOnboardingData $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = $this->userRepo->findByIdWithLock($data->userId);
            if (! $user) {
                abort(404, 'Người dùng không tồn tại.');
            }

            if ($user->onboarded) {
                abort(400, 'Tài khoản đã hoàn tất thiết lập ban đầu.');
            }

            // BR-ACC-05/08: onboarding never changes the account role; owner rights come only from partner approval.
            $updatePayload = [
                'onboarded' => true,
            ];

            if ($data->phone !== null) {
                $updatePayload['phone'] = $data->phone;
            }

            if ($data->playLevel !== null) {
                $updatePayload['play_level'] = $data->playLevel;
            }

            $updatedUser = $this->userRepo->update($user, $updatePayload);

            $isCourtOwnerIntent = $data->accountType === 'court_owner';

            return [
                'user' => $updatedUser,
                'account_type' => $data->accountType,
                'next_step' => $isCourtOwnerIntent ? 'partner_registration' : 'explore_venues',
                'partner_registration_url' => $isCourtOwnerIntent ? '/partner/register' : null,
                'message' => $isCourtOwnerIntent
                    ? 'Thiết lập tài khoản cá nhân thành công. Để kinh doanh và quản lý câu lạc bộ, vui lòng tiếp tục đăng ký trở thành đối tác.'
                    : 'Thiết lập tài khoản thành công.',
            ];
        });
    }
}
