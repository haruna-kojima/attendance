<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * 勤怠データのデフォルト生成ルールの定義
     */
    public function definition(): array
    {
        // テスト等で日付が指定されなかった場合は「今日」をデフォルトにする
        $targetDate = Carbon::today();

        // 9:00前後のランダムな出勤日時を作成（datetime型用）
        $clockInAt = $targetDate->copy()->setHour(rand(8, 9))->setMinute(rand(0, 59))->setSecond(rand(0, 59));

        // 18:00前後のランダムな退勤日時を作成（datetime型用）
        $clockOutAt = $targetDate->copy()->setHour(rand(18, 19))->setMinute(rand(0, 59))->setSecond(rand(0, 59));

        return [
            // 💡 一般ユーザーが未指定の場合は、自動的にUserをその場で1人新規作成してIDを紐付ける（Laravelの強力な機能）
            'user_id'      => User::factory()->state(['role' => 'general']),
            'date'         => $targetDate->format('Y-m-d'),
            'clock_in_at'  => $clockInAt,
            'clock_out_at' => $clockOutAt,
            'status'       => '退勤済',
            'remarks'      => $this->faker->optional(0.2)->realText(15), // 20%の確率で備考が入る
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }
}
