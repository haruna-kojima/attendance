<?php

namespace Database\Factories;

use App\Models\BreakLogModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreakFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_id' => null, // Seeder側で注入
            // 休憩時間は1回につき30分〜60分のランダム
            'break_in'  => null, // 整合性を保つためSeeder側で出勤時間ベースで計算
            'break_out' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
