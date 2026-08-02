<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoData extends Command
{
    protected $signature = 'demo:reset {--force : Reset even when the app is not in demo mode}';

    protected $description = 'Xóa và tạo lại dữ liệu sandbox demo';

    public function handle(): int
    {
        if (! config('app.demo_mode') && ! $this->option('force')) {
            $this->info('DEMO_MODE đang tắt, không thay đổi dữ liệu.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            DB::table('refunds')->delete();
            DB::table('payments')->delete();
            DB::table('booking_slots')->delete();
            DB::table('bookings')->delete();
            DB::table('court_blocks')->delete();
            DB::table('court_price_slots')->delete();
            DB::table('courts')->delete();
            DB::table('users')->where('email', 'like', '%@demo.auracourt.vn')->delete();
        });

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->info('Đã khôi phục dữ liệu demo.');

        return self::SUCCESS;
    }
}
