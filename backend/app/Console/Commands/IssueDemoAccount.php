<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IssueDemoAccount extends Command
{
    protected $signature = 'demo:issue
        {--admin : Cấp tài khoản demo có quyền quản trị}
        {--name= : Tên hiển thị}
        {--email= : Email demo tùy chọn, phải thuộc miền @demo.auracourt.vn}';

    protected $description = 'Cấp một tài khoản demo riêng để gửi cho người trải nghiệm';

    public function handle(): int
    {
        $email = $this->option('email') ?: 'demo-'.Str::lower(Str::random(8)).'@demo.auracourt.vn';

        if (! str_ends_with(strtolower($email), '@demo.auracourt.vn')) {
            $this->error('Email demo phải thuộc miền @demo.auracourt.vn.');

            return self::INVALID;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Email demo này đã tồn tại.');

            return self::INVALID;
        }

        $password = Str::random(14);
        $user = User::create([
            'name' => $this->option('name') ?: ($this->option('admin') ? 'Quản trị demo' : 'Khách demo'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $this->option('admin') ? 'admin' : 'customer',
            'email_verified_at' => now(),
        ]);

        $this->info('Đã cấp tài khoản demo #'.$user->id.'.');
        $this->line('Email: '.$email);
        $this->line('Mật khẩu: '.$password);
        $this->line('Mọi thao tác quản trị của tài khoản này chỉ được mô phỏng và rollback sau request.');

        return self::SUCCESS;
    }
}
