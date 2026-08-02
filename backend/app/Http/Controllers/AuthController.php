<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', 'unique:users'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = User::create($data + ['role' => 'customer']);
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
}
