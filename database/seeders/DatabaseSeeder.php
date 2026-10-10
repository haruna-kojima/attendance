<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,       // 1番目：まずユーザー（親）を登録
            AttendanceSeeder::class, // 2番目：作成されたユーザーに勤怠（子）を紐付け
        ]);
    }
}
