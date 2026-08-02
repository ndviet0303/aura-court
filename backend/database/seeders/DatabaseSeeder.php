<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')], ['name' => 'Quản trị viên', 'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'ChangeMe123!')), 'role' => 'admin']);
        $court = Court::firstOrCreate(['name' => 'Sân 1'], ['description' => 'Sân cầu lông tiêu chuẩn', 'is_active' => true]);
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            for ($hour = 6; $hour < 23; $hour++) {
                CourtPriceSlot::updateOrCreate(['court_id' => $court->id, 'weekday' => $weekday, 'starts_at' => sprintf('%02d:00', $hour)], ['ends_at' => sprintf('%02d:00', $hour + 1), 'price' => 100000, 'is_active' => true]);
            }
        }
    }
}
