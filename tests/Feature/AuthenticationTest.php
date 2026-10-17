<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    // テスト実行ごとにデータベースを綺麗にリセットする設定
    use RefreshDatabase;

    /**
     * 【FN003】会員登録のバリデーション・エラーメッセージテスト
     */
    public function test_register_validation_errors(): void
    {
        // 1. 未入力で送信した場合のエラーメッセージ検証
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);

        // 2. メール形式違反・パスワード文字数違反・確認用不一致の検証
        $response2 = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'invalid-email-format',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response2->assertSessionHasErrors([
            'email' => 'メールアドレスはメール形式で入力してください',
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    /**
     * 【FN005】会員登録成功直後に打刻画面へ遷移するかテスト
     */
    public function test_register_success_redirects_to_attendance(): void
    {
        $response = $this->post('/register', [
            'name' => '新規スタッフ',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 会員登録直後、打刻画面（/attendance）へ自動遷移すること
        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', ['email' => 'newstaff@example.com', 'role' => 'general']);
    }

    /**
     * 【FN009】一般ログインのバリデーション・エラーメッセージテスト
     */
    public function test_user_login_validation_and_auth_errors(): void
    {
        // 1. 未入力テスト
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);

        // 2. 登録情報誤りテスト
        $response2 = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);
        $response2->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }

    /**
     * 【FN016】管理者ログインのバリデーション・エラーメッセージテスト
     */
    public function test_admin_login_validation_and_auth_errors(): void
    {
        // 1. 未入力テスト
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => '',
        ]);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);

        // 2. 登録情報誤りテスト
        $response2 = $this->post('/admin/login', [
            'email' => 'wrong_admin@example.com',
            'password' => 'wrongpassword',
        ]);
        $response2->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }

    /**
     * 一般・管理者の正常ログイン・リダイレクト遷移テスト
     */
    public function test_successful_logins_redirect_correctly(): void
    {
        // テスト用の一般ユーザーと管理者をデータベースに用意
        $user = User::create([
            'name' => '一般スタッフ',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $admin = User::create([
            'name' => '管理者',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // 1. 一般ユーザーログイン → 打刻画面（/attendance）へ
        $userResponse = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        $userResponse->assertRedirect('/attendance');

        // 一旦ログアウト
        $this->post('/logout');

        // 2. 管理者ログイン → 管理者用日次一覧（/admin/attendance/list）へ
        $adminResponse = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);
        $adminResponse->assertRedirect('/admin/attendance/list');
    }
}
