<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLogModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------------------------------
        // 1. 指定ユーザーの作成（マイグレーションの role カラムに完全対応）
        // ----------------------------------------------------
        $user1 = User::create([
            'name' => '一般ユーザー1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'general',
        ]);

        $user2 = User::create([
            'name' => '一般ユーザー2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'general',
        ]);

        $admin = User::create([
            'name' => '管理者ユーザー3',
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);

        // ----------------------------------------------------
        // 2. 実運用に近い勤怠データの生成（直近2ヶ月の平日）
        // ----------------------------------------------------
        $generalUsers = [$user1, $user2];

        // 直近2ヶ月前（今日から約60日前）から今日までの期間を設定
        $start = now()->subMonths(2)->startOfMonth();
        $end = now();
        $period = CarbonPeriod::create($start, $end);

        foreach ($generalUsers as $user) {
            foreach ($period as $date) {
                // 土日はスキップ（実運用に近い平日の稼働にする）
                if ($date->isWeekend()) {
                    continue;
                }

                // 稀に欠勤（有給など）の日を作るため、5%の確率で打刻しない日を設ける
                if (rand(1, 100) <= 5) {
                    continue;
                }

                // 勤怠レコードを1件生成
                $attendance = Attendance::factory()->create([
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                ]);

                // 出勤時間をもとに休憩時間を計算（例: 12:00〜13:00の1時間休憩）
                // 1回の休憩は確定で入れる（昼休憩想定）
                BreakLogModel::factory()->create([
                    'attendance_id' => $attendance->id,
                    'break_in'  => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                // 20%の確率で「2回目の小休憩（15:00〜15:15など）」が発生したデータを作る（FN021の複数レコード要件に対応）
                if (rand(1, 100) <= 20) {
                    BreakLogModel::factory()->create([
                        'attendance_id' => $attendance->id,
                        'break_in'  => '15:00:00',
                        'break_out' => '15:15:00',
                    ]);
                }
            }
        }
    }
}
