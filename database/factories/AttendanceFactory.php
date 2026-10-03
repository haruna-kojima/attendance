<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        // 9:00前後のランダムな出勤時間（例: 08:45〜09:15）
        $clockInHour = rand(8, 9);
        $clockInMinute = ($clockInHour === 8) ? rand(45, 59) : rand(0, 15);
        $clockIn = sprintf('%02d:%02d:00', $clockInHour, $clockInMinute);

        // 18:00前後のランダムな退勤時間（例: 18:00〜19:30）
        $clockOutHour = rand(18, 19);
        $clockOutMinute = ($clockOutHour === 18) ? rand(0, 59) : rand(0, 30);
        $clockOut = sprintf('%02d:%02d:00', $clockOutHour, $clockOutMinute);

        return [
            'user_id'   => null, // Seeder側で注入
            'date'      => null, // Seeder側で平日の日付を注入
            'clock_in'  => $clockIn,
            'clock_out' => $clockOut,
            'comment'   => $this->faker->optional(0.3)->realText(20), // 30%の確率で備考が入る
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
