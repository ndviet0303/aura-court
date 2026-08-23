<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'customer',
            'onboarded' => false,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($data, true)) {
            return response()->json(['message' => 'Thông tin đăng nhập không đúng.'], 422);
        }
        $request->session()->regenerate();

        return ['user' => $request->user()];
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        if (User::where('email', $request->input('email'))->first()?->isDemo()) {
            return ['message' => 'Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.'];
        }
        Password::sendResetLink($request->only('email'));

        return ['message' => 'Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.'];
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });

        return $status === Password::PASSWORD_RESET ? ['message' => 'Mật khẩu đã được cập nhật.'] : response()->json(['message' => __($status)], 422);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return ['message' => 'Mật khẩu đã được cập nhật.'];
    }

    public function socialLogin(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:google,facebook'],
            'email' => ['required', 'email', 'max:190'],
            'name' => ['required', 'string', 'max:120'],
            'provider_id' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(32)),
                'role' => 'customer',
                'onboarded' => false,
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 200);
    }

    protected function resolveOAuthRedirectUri(Request $request, string $provider): string
    {
        $configured = config("services.{$provider}.redirect");
        $host = $request->getHost();

        if (in_array($host, ['ziet-mac', 'ziet-mac.local', '192.168.1.181'], true)) {
            $scheme = $request->getScheme();
            $port = $request->getPort() ? ':' . $request->getPort() : ':8000';
            return "{$scheme}://{$host}{$port}/api/v1/auth/{$provider}/callback";
        }

        return $configured ?: ($request->getSchemeAndHttpHost() . "/api/v1/auth/{$provider}/callback");
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
                'message' => "Chưa cấu hình API credentials cho {$provider}."
            ]);
        }

        $redirectUri = $this->resolveOAuthRedirectUri($request, $provider);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = \Laravel\Socialite\Facades\Socialite::driver($provider);
        return response()->json(['url' => $driver->redirectUrl($redirectUri)->stateless()->redirect()->getTargetUrl()]);
    }

    public function handleProviderCallback(Request $request, string $provider)
    {
        $frontendUrl = $this->resolveFrontendUrl($request);

        if (! in_array($provider, ['google', 'facebook'], true)) {
            return redirect("{$frontendUrl}/login?error=invalid_provider");
        }

        try {
            $redirectUri = $this->resolveOAuthRedirectUri($request, $provider);

            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = \Laravel\Socialite\Facades\Socialite::driver($provider);
            $socialUser = $driver->redirectUrl($redirectUri)->stateless()->user();

            $email = $socialUser->getEmail() ?: ($socialUser->getId() . '@facebook.auracourt.vn');
            $name = $socialUser->getName() ?: ($socialUser->getNickname() ?: 'Athlete');

            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'customer',
                    'onboarded' => false,
                ]);
                $user->email_verified_at = now();
                $user->save();
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            if (! $user->onboarded) {
                return redirect("{$frontendUrl}/onboarding");
            }

            if ($user->role === 'admin') {
                return redirect("{$frontendUrl}/admin/organization");
            }

            return redirect("{$frontendUrl}/");
        } catch (\Throwable) {
            return redirect("{$frontendUrl}/login?error=oauth_failed");
        }
    }

    public function onboarding(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'account_type' => ['required', 'string', 'in:customer,court_owner'],
            'phone' => ['nullable', 'string', 'max:30'],
            'play_level' => ['nullable', 'string', 'max:50'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'courts_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $accountType = $data['account_type'];
        $role = $accountType === 'court_owner' ? 'admin' : 'customer';

        $user->forceFill([
            'role' => $role,
            'phone' => $data['phone'] ?? $user->phone,
            'play_level' => $data['play_level'] ?? $user->play_level,
            'onboarded' => true,
        ])->save();

        if ($accountType === 'court_owner') {
            $orgName = !empty($data['organization_name']) ? trim($data['organization_name']) : ($user->name . ' Badminton Club');
            $slugBase = Str::slug($orgName);
            if (empty($slugBase)) {
                $slugBase = 'club';
            }
            $slug = $slugBase . '-' . substr(uniqid(), -5);

            $org = Organization::create([
                'name' => $orgName,
                'slug' => $slug,
                'status' => 'active',
                'settings' => [
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['venue_address'] ?? null,
                    'courts_count' => $data['courts_count'] ?? null,
                ],
            ]);

            OrganizationMember::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
                'status' => 'active',
            ]);

            $venue = Venue::create([
                'organization_id' => $org->id,
                'name' => $orgName,
                'slug' => $slug . '-venue',
                'address' => $data['venue_address'] ?? 'Việt Nam',
                'is_active' => true,
            ]);

            $courtsCount = isset($data['courts_count']) ? (int) $data['courts_count'] : 2;
            if ($courtsCount > 0) {
                for ($i = 1; $i <= min($courtsCount, 20); $i++) {
                    $court = Court::create([
                        'venue_id' => $venue->id,
                        'name' => 'Sân ' . $i,
                        'description' => 'Sân cầu lông chất lượng cao.',
                        'is_active' => true,
                    ]);

                    $priceRows = [];
                    for ($weekday = 1; $weekday <= 7; $weekday++) {
                        for ($hour = 6; $hour < 23; $hour++) {
                            $priceRows[] = [
                                'court_id' => $court->id,
                                'weekday' => $weekday,
                                'starts_at' => sprintf('%02d:00', $hour),
                                'ends_at' => sprintf('%02d:00', $hour + 1),
                                'price' => 120000,
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                    CourtPriceSlot::insert($priceRows);
                }
            }
        }

        return response()->json(['user' => $user->fresh()], 200);
    }
}
