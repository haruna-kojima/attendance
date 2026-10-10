<?php

namespace Database\Factories;

use App\Models\BreakLog;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class BreakLogFactory extends Factory
{
    protected $model = BreakLog::class;

    /**
     * 休憩データのデフォルト生成ルールの定義
     */
    public function definition(): array
    {
        $targetDate = Carbon::today()->format('Y-m-d');

        return [
            // 💡 親である勤怠データが未指定の場合は、自動的にAttendanceFactoryを呼び出して紐付ける
            'attendance_id' => Attendance::factory(),

            // 先ほどのスペース問題をクリアした、安全なドッキング形式を定義
            'break_in_at'   => $targetDate . ' 12:00:00',
            'break_out_at'  => $targetDate . ' 13:00:00',
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }
}
