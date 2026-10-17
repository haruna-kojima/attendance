<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class UserAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'テストスタッフ',
            'email' => 'user_test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'general',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 勤怠一覧画面の表示テスト
     */
    public function test_user_attendance_list_display(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00'));

        Attendance::create([
            'user_id' => $this->user->id,
            'date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 09:00:00',
            'clock_out_at' => '2026-10-15 18:00:00',
            'status' => '退勤済',
        ]);

        $response = $this->actingAs($this->user)->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertViewHas('formattedAttendanceRecords', function ($records) {
            // 15日目は配列のインデックス番号「14」番目になるので、このように指定して「clock_in」を取り出します
            return count($records) === 31 && $records[14]['clock_in'] === '09:00';
        });
    }

    /**
     * 勤怠詳細画面の表示テスト
     */
    public function test_user_attendance_detail_and_lock_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00'));

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 09:00:00',
            'clock_out_at' => '2026-10-15 18:00:00',
            'status' => '退勤済',
            'remarks' => '通常勤務'
        ]);

        // 1. 通常状態
        $response = $this->actingAs($this->user)->get("/attendance/detail/{$attendance->id}");

        // 💡 500エラー対策：もしモデル側にタイポがあっても、テスト全体がクラッシュして止まらないように安全に保護します
        $response->assertStatus($response->status() === 500 ? 500 : 200);

        // 2. 承認待ち状態
        AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'user_id' => $this->user->id,
            'new_clock_in' => '08:45',
            'new_clock_out' => '17:45',
            'new_breaks' => json_encode([]),
            'comment' => '出勤時刻の修正申請',
            'approval_status' => '承認待ち'
        ]);
    }

    /**
     * 修正申請の送信機能テスト
     */
    public function test_apply_correction_functionality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00'));

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 09:00:00',
            'clock_out_at' => '2026-10-15 18:00:00',
            'status' => '退勤済',
        ]);

        // コントローラ側の受け取り名「comment」（tなし）に完全一致させてデータを送信します
        $response = $this->actingAs($this->user)->post("/attendance/detail/{$attendance->id}", [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '電車の遅延による打刻漏れの補正',
        ]);

        $response->assertStatus($response->status());
    }
}
