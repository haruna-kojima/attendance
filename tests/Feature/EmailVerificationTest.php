<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未認証ユーザーが制限されたページにアクセスすると、メール認証画面にリダイレクトされること
     */
    public function test_unverified_user_is_redirected_to_verification_prompt(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => null, // 💡 未認証状態
        ]);

        // 未認証のまま打刻ページ（例: /attendance）へアクセス
        $response = $this->actingAs($user)->get('/attendance');

        // Fortify標準のメール認証待ち画面へリダイレクト（302）されることを検証
        $response->assertStatus(302);
        $response->assertRedirect('/email/verify');
    }

    /**
     * 会員登録時に認証メールがバックグラウンドで正しく送信（フェイク通知）されること
     */
    public function test_verification_email_is_sent_upon_registration(): void
    {
        Notification::fake();

        // Fortifyの登録エンドポイントへリクエスト
        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録後に、該当ユーザーに対してVerifyEmail通知が飛んでいることを検証
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * メールに記載された認証URLをクリックすると、メール認証が完了しステータスが更新されること
     */
    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => null, // 💡 最初は未認証
        ]);

        // Laravelの暗号化された署名付きURL（メール内のリンクに相当）をバックエンド側で擬似生成
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // 擬似的にURLをクリック（GETアクセス）
        $response = $this->actingAs($user)->get($verificationUrl);

        // 認証完了後、指定のルート（例: /home や設定されたリダイレクト先）へ遷移することを検証
        $response->assertRedirect(config('fortify.home', '/home') . '?verified=1');

        // データベース上のemail_verified_atがnullでなくなっている（認証成功）ことを検証
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
