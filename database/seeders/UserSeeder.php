<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * ユーザーマスターデータの生成
     */
    public function run(): void
    {
        // ユーザー1（一般）：メール認証済み
        User::create([
            'name' => '一般ユーザー1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'general',
        ]);

        // ユーザー2（一般）：メール認証済み
        User::create([
            'name' => '一般ユーザー2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'general',
        ]);

        // ユーザー3（管理者）：メール認証済み（role = admin）
        User::create([
            'name' => '管理者ユーザー3',
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }
}
