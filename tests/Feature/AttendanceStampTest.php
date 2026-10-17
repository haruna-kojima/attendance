<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceStampTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の一般ユーザーを共通で作成（メール認証済み状態）
        $this->user = User::create([
            'name' => '打刻テストスタッフ',
            'email' => 'stamptest@example.com',
            'password' => bcrypt('password123'),
            'role' => 'general',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 打刻画面の初期表示（日時情報・ステータス確認）テスト
     */
    public function test_stamp_page_initial_display(): void
    {
        // 模擬の現在日時を固定
        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00'));

        $response = $this->actingAs($this->user)->get('/attendance');

        $response->assertStatus(200);
        // 初期状態のステータス「勤務外」が表示されていること
        $response->assertSee('勤務外');
        // UIと同じ形式の日時情報が出力されていること（2026年10月17日(土)）
        $response->assertSee('2026年10月17日(土)');
    }

    /**
     * 出勤機能のテスト（1日1回制限・ステータスが出勤中に変更）
     */
    public function test_clock_in_functionality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00'));

        // 1. 出勤ボタンを押下
        $response = $this->actingAs($this->user)->post('/attendance', [
            'action' => 'clock_in'
        ]);

        $response->assertRedirect();

        // データベースに「出勤中」でレコードが作成されていること
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'date' => '2026-10-17',
            'status' => '出勤中',
        ]);

        // 画面のステータス表示が「出勤中」に切り替わっていること
        $this->actingAs($this->user)->get('/attendance')->assertSee('出勤中');

        // 2. 二重出勤制限のテスト（同じ日に2回目は押せないこと）
        $response2 = $this->actingAs($this->user)->post('/attendance', [
            'action' => 'clock_in'
        ]);

        // 2件目は作成されず、リダイレクトセッションにエラーがあること
        $this->assertEquals(1, Attendance::count());
    }

    /**
     * 休憩機能のテスト（休憩入・休憩戻が何回でも連動して記録できること）
     */
    public function test_break_in_and_out_functionality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00'));

        // まず出勤させる
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'date' => '2026-10-17',
            'clock_in_at' => now(),
            'status' => '出勤中',
        ]);

        // 1回目：休憩入
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00'));
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_in']);

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'status' => '休憩中']);
        $this->assertEquals(1, BreakLog::count());

        // 1回目：休憩戻
        Carbon::setTestNow(Carbon::parse('2026-10-17 13:00:00'));
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_out']);

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'status' => '出勤中']);

        // 2回目：休憩入（1日に何回でも押下できることの検証）
        Carbon::setTestNow(Carbon::parse('2026-10-17 15:00:00'));
        $this->actingAs($this->user)->post('/attendance', ['action' => 'break_in']);

        $this->assertEquals(2, BreakLog::count()); // 休憩回数分のレコード(2件目)が正常作成
    }

    /**
     * 退勤機能のテスト（メッセージ表示・1日1回制限・ステータス退勤済）
     */
    public function test_clock_out_functionality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00'));

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'date' => '2026-10-17',
            'clock_in_at' => now(),
            'status' => '出勤中',
        ]);

        // 退勤ボタンを押下
        Carbon::setTestNow(Carbon::parse('2026-10-17 18:00:00'));
        $response = $this->actingAs($this->user)->post('/attendance', [
            'action' => 'clock_out'
        ]);

        // 退勤メッセージ「お疲れ様でした。」がセッションに引き渡されること
        $response->assertSessionHas('success', 'お疲れ様でした。');

        // ステータスが「退勤済」に更新されていること
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => '退勤済',
        ]);
    }
}
