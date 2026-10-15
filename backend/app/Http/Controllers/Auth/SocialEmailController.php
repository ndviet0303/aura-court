<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteSocialEmailRequest;
use App\Services\Auth\EmailLinkThrottle;
use Illuminate\Http\JsonResponse;

class SocialEmailController extends Controller
{
    /** BR-06: an account created from Facebook without an email adds a real one, then verifies it. */
    public function __invoke(CompleteSocialEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['email' => $request->validated('email'), 'email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();
        // BR-05: this send counts toward the resend throttle of the new address.
        EmailLinkThrottle::hit($user->email);

        return response()->json(['message' => 'Đã lưu email. Vui lòng mở liên kết xác thực vừa được gửi.', 'user' => $user->fresh()]);
    }
}
