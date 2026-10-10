<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceSeeder extends Seeder
{
    /**
     * 実運用に近い勤怠・休憩データの生成
     */
    public function run(): void
    {
        // 1. DBから一般ユーザーのみをすべて取得（IDを動的に紐付けるため）
        $generalUsers = User::where('role', 'general')->get();

        // 2. 直近2ヶ月前（今日から約60日前）から今日までの日付の波を作成
        $start = now()->subMonths(2)->startOfMonth();
        $end = now();
        $period = CarbonPeriod::create($start, $end);

        foreach ($generalUsers as $user) {
            foreach ($period as $date) {

                // 💡 実運用再現のため、土日はスキップ（勤務しない）
                if ($date->isWeekend()) {
                    continue;
                }

                // 稀にある欠勤日を再現するため、5%の確率で打刻しない日を作る
                if (rand(1, 100) <= 5) {
                    continue;
                }

                // 3. 9:00前後のリアルな出勤日時、18:00前後の退勤日時をパディングして作成
                $clockInAt = $date->copy()->setHour(rand(8, 9))->setMinute(rand(0, 59))->setSecond(rand(0, 59));
                $clockOutAt = $date->copy()->setHour(rand(18, 19))->setMinute(rand(0, 59))->setSecond(rand(0, 59));

                // 勤怠レコードを1件作成（statusは確定で『退勤済』にしておく）
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in_at' => $clockInAt,
                    'clock_out_at' => $clockOutAt,
                    'status' => '退勤済',
                    'remarks' => rand(1, 100) <= 20 ? '体調不良のため遅のり' : null, // 20%で備考を挿入
                ]);

                // 4. 1回目の休憩（昼休憩：12:00〜13:00）を確定で作成（FN021-5-a）
                BreakLog::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at'   => $date->format('Y-m-d') . ' ' . '12:00:00',
                    'break_out_at'  => $date->format('Y-m-d') . ' ' . '13:00:00',
                ]);

                // 5. 複数回休憩要件のクリア：15%の確率で「2回目の小休憩（15:00〜15:15）」が発生したデータ行を作る
                if (rand(1, 100) <= 15) {
                    BreakLog::create([
                        'attendance_id' => $attendance->id,
                        'break_in_at' => $date->format('Y-m-d') . ' ' . '15:00:00',
                        'break_out_at' => $date->format('Y-m-d') . ' ' . '15:15:00',
                    ]);
                }
            }
        }
    }
}
