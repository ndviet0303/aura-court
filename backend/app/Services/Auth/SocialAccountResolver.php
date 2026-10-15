<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialAccountResolver
{
    /**
     * The account a provider identity signs into, or null when the email already belongs to an
     * account this identity may not take over (BR-06: never link by matching email alone).
     */
    public function resolve(string $provider, string $socialId, ?string $providerEmail, bool $emailVerified, string $name, ?string $avatar): ?User
    {
        $user = User::where('social_provider', $provider)->where('social_id', $socialId)->first();
        if ($user) {
            return $user;
        }

        $email = $providerEmail ?: ($socialId.'@facebook.auracourt.vn');
        $existing = User::whereRaw('lower(email) = ?', [Str::lower($email)])->first();
        if (! $existing) {
            return $this->create($name, $email, $provider, $socialId, $emailVerified, $avatar);
        }
        if ($this->isUnclaimed($existing, $provider, $emailVerified || ! $providerEmail)) {
            $existing->forceFill(['social_id' => $socialId])->save();

            return $existing;
        }

        return null;
    }

    /**
     * An account this provider created before provider ids were stored. It is claimed once,
     * and only when the provider vouches for the email or the email was built from its id.
     */
    private function isUnclaimed(User $user, string $provider, bool $emailProven): bool
    {
        return $user->social_provider === $provider && $user->social_id === null && ! $user->password_set && $emailProven;
    }

    private function create(string $name, string $email, string $provider, string $socialId, bool $emailVerified, ?string $avatar): User
    {
        $payload = [
            'name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(32)),
            'role' => 'customer', 'avatar_url' => $avatar, 'onboarded' => false,
            'password_set' => false, 'social_provider' => $provider,
        ];
        try {
            $user = User::create($payload);
        } catch (QueryException $qe) {
            if (str_contains($qe->getMessage(), '22001') || str_contains($qe->getMessage(), 'avatar_url')) {
                $payload['avatar_url'] = null;
                $user = User::create($payload);
            } else {
                throw $qe;
            }
        }
        $user->forceFill(['social_id' => $socialId, 'email_verified_at' => $emailVerified ? now() : null])->save();

        return $user;
    }
}
