<?php

namespace App\Services\Admin;

use App\Models\ElevatedAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** BR-23: only a platform admin locks or unlocks an account, always with a reason, and every change is audited. */
class AccountLockService
{
    public function lock(User $admin, User $target, string $reason, ?string $ip): User
    {
        abort_if($admin->is($target), 422, 'Bạn không thể tự khóa tài khoản của mình.');
        abort_if($target->isRestricted(), 409, 'Tài khoản này đang bị khóa.');

        return DB::transaction(function () use ($admin, $target, $reason, $ip) {
            $before = $target->status;
            // Locking ends every existing session: browser sessions via sessions_revoked_at, remember-me cookies and API tokens directly.
            $target->forceFill([
                'status' => 'locked',
                'sessions_revoked_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            $target->tokens()->delete();

            $this->audit($admin, $target, 'ACCOUNT_LOCKED', $reason, $before, $ip);

            return $target;
        });
    }

    public function unlock(User $admin, User $target, string $reason, ?string $ip): User
    {
        abort_unless($target->isRestricted(), 409, 'Tài khoản này không bị khóa.');

        return DB::transaction(function () use ($admin, $target, $reason, $ip) {
            $before = $target->status;
            $target->forceFill(['status' => 'active'])->save();

            $this->audit($admin, $target, 'ACCOUNT_UNLOCKED', $reason, $before, $ip);

            return $target;
        });
    }

    private function audit(User $admin, User $target, string $action, string $reason, string $before, ?string $ip): void
    {
        ElevatedAuditLog::create([
            'user_id' => $admin->id,
            'action' => $action,
            'purpose' => $reason,
            'outcome' => 'success',
            'ip_address' => $ip,
            'metadata' => [
                'target_user_id' => $target->id,
                'target_email' => $target->email,
                'before' => ['status' => $before],
                'after' => ['status' => $target->status],
            ],
            'created_at' => now(),
        ]);
    }
}
