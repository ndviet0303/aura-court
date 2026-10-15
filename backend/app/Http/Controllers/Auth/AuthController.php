<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\CompletePersonalOnboarding;
use App\Domain\Auth\DTOs\CompletePersonalOnboardingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompletePersonalOnboardingRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Rules\AccountPassword;
use App\Services\Auth\EmailLinkThrottle;
use App\Services\Auth\SocialAccountResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'customer',
            'onboarded' => false,
            'password_set' => true,
            'social_provider' => null,
        ]);
        event(new Registered($user));
        EmailLinkThrottle::hit($user->email);

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $user], 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        if (! Auth::attempt($data, true)) {
            return response()->json(['message' => __('auth.failed')], 422);
        }
        $user = Auth::user();
        if ($user && $user->isRestricted()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.',
                'error' => 'account_restricted',
            ], 403);
        }
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ['user' => $request->user()];
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
        // BR-24: only this device signs out; remember-me cookies elsewhere stay valid.
        Auth::guard('web')->logoutCurrentDevice();
        try {
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } catch (\Throwable) {
            // Stateless request
        }

        return response()->json(['message' => __('auth.logged_out')]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();
        // BR-10: only accounts with a password and a verified email; the reply never reveals which.
        if ($user && $user->password_set && $user->hasVerifiedEmail() && ! $user->isDemo()) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return ['message' => __('auth.password_reset_sent')];
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'password_set' => true])->save();
        });

        if ($status === Password::PASSWORD_RESET) {
            return ['message' => __('passwords.reset')];
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => __('passwords.token'),
            Password::INVALID_USER => __('passwords.user'),
            default => __('passwords.token'),
        };

        return response()->json(['message' => $message], 422);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (! $user->password_set) {
            return response()->json(['message' => 'Tài khoản chưa có mật khẩu. Vui lòng thiết lập mật khẩu qua liên kết gửi đến email.'], 422);
        }
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', new AccountPassword, 'different:current_password', 'confirmed'],
        ], ['password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.']);
        $user->update(['password' => $data['password']]);

        // BR-10: a password change ends every session, this one included; the user signs in again.
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ['message' => 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập lại.', 'logged_out' => true];
    }

    /** BR-09: an OAuth-only account proves it owns its verified email before setting a password. */
    public function setPassword(Request $request)
    {
        $user = $request->user();
        if ($user->password_set) {
            return response()->json(['message' => 'Tài khoản đã có mật khẩu. Hãy dùng chức năng đổi mật khẩu.'], 422);
        }
        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Vui lòng xác thực email trước khi thiết lập mật khẩu.'], 422);
        }
        Password::sendResetLink(['email' => $user->email]);

        return ['message' => 'Đã gửi liên kết thiết lập mật khẩu đến email của bạn. Liên kết có hiệu lực 15 phút.'];
    }

    protected function resolveOAuthRedirectUri(Request $request, string $provider): string
    {
        $configured = config("services.{$provider}.redirect");
        $host = $request->getHost();

        if (in_array($host, ['ziet-mac', 'ziet-mac.local', '192.168.1.181'], true)) {
            $scheme = $request->getScheme();
            $port = $request->getPort() ? ':'.$request->getPort() : ':8000';

            return "{$scheme}://{$host}{$port}/api/v1/auth/{$provider}/callback";
        }

        return $configured ?: ($request->getSchemeAndHttpHost()."/api/v1/auth/{$provider}/callback");
    }

    protected function resolveFrontendUrl(Request $request): string
    {
        $host = $request->getHost();

        if (in_array($host, ['ziet-mac', 'ziet-mac.local', '192.168.1.181'], true)) {
            return "http://{$host}:5173";
        }

        if ($host === 'court-api.ziet.dev') {
            return 'https://court.ziet.dev';
        }

        return rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
    }

    public function redirectToProvider(Request $request, string $provider)
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            return response()->json(['message' => 'Provider không được hỗ trợ.'], 400);
        }

        if (empty(config("services.{$provider}.client_id"))) {
            return response()->json([
                'url' => null,
                'demo_mode' => true,
                'message' => "Chưa cấu hình API credentials cho {$provider}.",
            ]);
        }

        $redirectUri = $this->resolveOAuthRedirectUri($request, $provider);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return response()->json(['url' => $driver->redirectUrl($redirectUri)->stateless()->redirect()->getTargetUrl()]);
    }

    public function handleProviderCallback(Request $request, string $provider, SocialAccountResolver $accounts)
    {
        $frontendUrl = $this->resolveFrontendUrl($request);

        if (! in_array($provider, ['google', 'facebook'], true)) {
            return redirect("{$frontendUrl}/login?error=invalid_provider");
        }

        try {
            $redirectUri = $this->resolveOAuthRedirectUri($request, $provider);

            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($provider);
            $socialUser = $driver->redirectUrl($redirectUri)->stateless()->user();

            // Only Google states that it checked the address; anything else stays unverified (BR-06).
            $emailVerified = $provider === 'google' && $socialUser instanceof AbstractUser
                && filter_var($socialUser->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $name = Str::limit($socialUser->getName() ?: ($socialUser->getNickname() ?: 'Athlete'), 255, '');
            $avatar = $socialUser->getAvatar() ? Str::limit($socialUser->getAvatar(), 2048, '') : null;

            $user = $accounts->resolve($provider, (string) $socialUser->getId(), $socialUser->getEmail(), $emailVerified, $name, $avatar);
            if (! $user) {
                return redirect("{$frontendUrl}/login?error=oauth_email_taken&msg=".urlencode('Email này đã có tài khoản Aura Court. Vui lòng đăng nhập bằng email và mật khẩu.'));
            }

            if ($user->isRestricted()) {
                return redirect("{$frontendUrl}/login?error=account_restricted&msg=".urlencode('Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.'));
            }
            if ($avatar && (empty($user->avatar_url) || str_contains($user->avatar_url, 'athlete-'))) {
                try {
                    $user->avatar_url = $avatar;
                    $user->save();
                } catch (\Throwable) {
                    // Prevent legacy schema avatar length from breaking login
                }
            }

            Auth::login($user, true);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            if (! $user->onboarded) {
                return redirect("{$frontendUrl}/onboarding");
            }

            if ($user->role === 'admin') {
                return redirect("{$frontendUrl}/admin/organization");
            }

            return redirect("{$frontendUrl}/");
        } catch (\Throwable $e) {
            Log::error('OAuth Callback Error: '.$e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString(),
            ]);
            $userMessage = config('app.debug') ? $e->getMessage() : 'Đăng nhập mạng xã hội thất bại. Vui lòng thử lại sau.';

            return redirect("{$frontendUrl}/login?error=oauth_failed&msg=".urlencode($userMessage));
        }
    }

    public function onboarding(CompletePersonalOnboardingRequest $request, CompletePersonalOnboarding $action)
    {
        $dto = CompletePersonalOnboardingData::fromRequest($request);
        $result = $action->execute($dto);

        return response()->json([
            'user' => $result['user'],
            'account_type' => $result['account_type'],
            'next_step' => $result['next_step'],
            'partner_registration_url' => $result['partner_registration_url'],
            'message' => $result['message'],
        ], 200);
    }
}
