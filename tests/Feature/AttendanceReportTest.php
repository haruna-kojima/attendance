<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. マイ勤怠レポートの集計値・異常検知のロジック検証
     */
    public function test_my_attendance_report_calculations_and_anomalies(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        $this->actingAs($user);

        // テスト内の時間を固定（2026年10月25日）
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 9, 0, 0));
        $todayStr = Carbon::today()->format('Y-m-d');
        $yesterdayStr = Carbon::today()->subDay()->format('Y-m-d');

        // 実績1: 遅刻（09:15出勤）＆ 長時間労働（計11時間勤務）
        // 09:15 〜 21:15（12時間） - 休憩1時間（60分） = 11時間
        $attendance1 = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in_at' => Carbon::create(2026, 10, 25, 9, 15, 0),
            'clock_out_at' => Carbon::create(2026, 10, 25, 21, 15, 0),
        ]);
        BreakLog::factory()->create([
            'attendance_id' => $attendance1->id,
            'break_in_at' => Carbon::create(2026, 10, 25, 12, 0, 0),
            'break_out_at' => Carbon::create(2026, 10, 25, 13, 0, 0),
        ]);

        // 実績2: 早退（17:30退勤）＆ 通常労働（計7.5時間勤務）
        // 09:00 〜 17:30（8.5時間） - 休憩1時間（60分） = 7.5時間
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $yesterdayStr,
            'clock_in_at' => Carbon::create(2026, 10, 24, 9, 0, 0),
            'clock_out_at' => Carbon::create(2026, 10, 24, 17, 30, 0),
        ]);
        BreakLog::factory()->create([
            'attendance_id' => $attendance2->id,
            'break_in_at' => Carbon::create(2026, 10, 24, 12, 0, 0),
            'break_out_at' => Carbon::create(2026, 10, 24, 13, 0, 0),
        ]);

        $response = $this->get('/attendance/report');

        $response->assertStatus(200);

        // 合計18.5時間（18h 30m）が画面にあるか確認
        $response->assertSee('18h 30m');

        // 各種異常検知がカウントされているか確認
        $response->assertSee('1 回'); // 遅刻
        $response->assertSee('1 日'); // 長時間労働

        Carbon::setTestNow();
    }

    /**
     * 2. 未ログイン（ゲスト）アクセスのリダイレクト検証
     */
    public function test_guest_access_redirects_to_login(): void
    {
        // ゲスト状態でレポートに直アクセス
        $response = $this->get('/attendance/report');
        $response->assertRedirect('/login');

        // ゲスト状態で管理者用画面に直アクセス
        $adminResponse = $this->get('/admin/attendance/list');
        $adminResponse->assertRedirect();
    }
}
