<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminAccount extends Command
{
    protected $signature = 'admin:create
        {--email=admin@auracourt.vn : Địa chỉ email của tài khoản admin}
        {--password=Admin@123456 : Mật khẩu khởi tạo của tài khoản admin}
        {--name=Aura Administrator : Họ tên hiển thị của tài khoản admin}
        {--phone=0988888888 : Số điện thoại liên hệ}';

    protected $description = 'Tạo mới hoặc cập nhật tài khoản quản trị viên (Admin) với đầy đủ quyền hạn và liên kết tổ chức';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');
        $phone = (string) $this->option('phone');

        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $this->info("Đang xử lý tài khoản admin cho: {$email}...");

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'role' => 'admin',
            'phone' => $phone,
            'play_level' => 'advanced',
            'onboarded' => true,
            'password_set' => true,
            'email_verified_at' => $user->email_verified_at ?: $now,
        ])->save();

        // Gắn quyền Organization Owner cho tổ chức mặc định
        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        if ($org) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $org->id, 'user_id' => $user->id],
                ['role' => 'organization_owner', 'status' => 'active', 'permissions' => ['*']]
            );
            $this->line("-> Đã liên kết tổ chức: {$org->name} (ID #{$org->id}) với quyền organization_owner.");
        }

        $this->newLine();
        $this->info('=== KHỞI TẠO TÀI KHOẢN ADMIN THÀNH CÔNG ===');
        $this->table(
            ['ID', 'Tên', 'Email', 'Mật khẩu', 'Vai trò', 'Trạng thái'],
            [
                [$user->id, $user->name, $user->email, $password, $user->role, 'Sẵn sàng đăng nhập'],
            ]
        );

        return self::SUCCESS;
    }
}
