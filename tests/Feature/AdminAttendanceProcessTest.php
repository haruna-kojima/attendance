<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AdminAttendanceProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $generalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. テスト用の「管理者ユーザー」を作成
        $this->admin = User::create([
            'name' => '管理者ボス',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // 2. 紐付け確認用の「一般ユーザー」を作成
        $this->generalUser = User::create([
            'name' => '一般スタッフ',
            'email' => 'staff_test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'general',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 管理者の日次勤怠一覧およびスタッフ一覧の表示テスト
     */
    public function test_admin_lists_display(): void
    {
        Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'clock_in_at' => Carbon::now(),
            'status' => '出勤中',
        ]);

        // 管理者としてログインして日次勤怠一覧にアクセス (URL: admin/attendance/list)
        $responseDaily = $this->actingAs($this->admin)->get('/admin/attendance/list');
        $responseDaily->assertStatus(200);

        // スタッフ一覧画面にアクセス (URL: admin/staff/list)
        $responseStaff = $this->actingAs($this->admin)->get('/admin/staff/list');
        $responseStaff->assertStatus(200);
    }

    /**
     * 管理者による勤怠詳細の直接修正保存テスト
     */
    public function test_admin_direct_update_functionality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 09:00:00',
            'clock_out_at' => '2026-10-15 18:00:00',
            'status' => '退勤済',
        ]);

        // 管理者が直接修正をPOST送信 (URL: admin/attendance/{id})
        // 💡 確定した「new_」ありリクエストの変数名に完全同期
        $response = $this->actingAs($this->admin)->post("/admin/attendance/{$attendance->id}", [
            'new_clock_in' => '08:30',
            'new_clock_out' => '17:30',
            'comment' => '管理者が直に代理修正',
        ]);

        $response->assertRedirect();

        // 本番テーブル「attendances」が直接上書き更新されていることの検証
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'remarks' => '管理者が直に代理修正'
        ]);
    }

    /**
     * 一般ユーザーからの修正申請に対する管理者の「承認」処理テスト
     */
    public function test_admin_approve_application_functionality(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00'));

        // 元となる本番勤怠データ（9:00〜18:00）
        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 09:00:00',
            'clock_out_at' => '2026-10-15 18:00:00',
            'status' => '退勤済',
        ]);

        // 事前に一般ユーザーが休憩時間の修正付きで「承認待ち」の申請を出していた状態を作る
        $breakCorrectionData = [
            [
                'break_in' => '12:00:00',
                'break_out' => '13:00:00'
            ]
        ];

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'user_id' => $this->generalUser->id,
            'new_clock_in' => '08:45:00', // 出勤を8:45に直したい
            'new_clock_out' => '17:45:00', // 退勤を17:45に直したい
            'new_breaks' => json_encode($breakCorrectionData),
            'comment' => '電車の遅延に伴う打刻修正',
            'approval_status' => '承認待ち'
        ]);

        // 💡 共通ルート承認POSTを実行 (URL: stamp_correction_request/approve/{id})
        $response = $this->actingAs($this->admin)->post("/stamp_correction_request/approve/{$correction->id}");

        // 承認完了後、指定の共通申請一覧（ルート名: correction.list）へ戻ることの検証
        $response->assertRedirect(route('correction.list'));

        // 1. 修正申請データのステータスが「承認済み」に変更されていることの検証
        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'approval_status' => '承認済み'
        ]);

        // 2. 本番勤怠データの時刻・備考欄が申請内容通りに「上書き上書き反映」されていることの検証
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-10-15 08:45:00',
            'clock_out_at' => '2026-10-15 17:45:00',
            'remarks' => '電車の遅延に伴う打刻修正'
        ]);

        // 3. 本番休憩データ（breaksテーブル）に修正分の休憩が入っていることの検証
        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in_at' => '2026-10-15 12:00:00',
            'break_out_at' => '2026-10-15 13:00:00'
        ]);
    }
}
