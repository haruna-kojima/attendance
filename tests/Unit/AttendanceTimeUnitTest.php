<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\BreakLog;
use PHPUnit\Framework\TestCase;

class AttendanceTimeUnitTest extends TestCase
{
    /**
     * 正常系テスト1: 休憩なしのシンプルな勤務時間計算
     */
    public function test_calculate_working_time_without_break(): void
    {
        $attendance = new Attendance();
        $attendance->clock_in_at = '2026-10-11 09:00:00';
        $attendance->clock_out_at = '2026-10-11 18:00:00';

        $attendance->setRelation('breaksLogs', collect([]));

        // 💡 プロパティではなく、モデルの「関数」を直接実行して検証します
        $this->assertEquals('09:00', $attendance->calculateActualWorkingTime());
    }

    /**
     * 正常系テスト2: 複数回の休憩ログが存在する場合の勤務時間引き算計算
     */
    public function test_calculate_working_time_with_multiple_breaks(): void
    {
        $attendance = new Attendance();
        $attendance->clock_in_at = '2026-10-11 09:00:00';
        $attendance->clock_out_at = '2026-10-11 18:15:00';

        $break1 = new BreakLog();
        $break1->break_in_at = '2026-10-11 12:00:00';
        $break1->break_out_at = '2026-10-11 13:00:00';

        $break2 = new BreakLog();
        $break2->break_in_at = '2026-10-11 15:00:00';
        $break2->break_out_at = '2026-10-11 15:15:00';

        $attendance->setRelation('breaksLogs', collect([$break1, $break2]));

        // 💡 プロパティではなく、モデルの「関数」を直接実行して検証します
        $this->assertEquals('08:00', $attendance->calculateActualWorkingTime());
    }

    /**
     * 異常系テスト3: まだ退勤打刻がされていない場合
     */
    public function test_calculate_working_time_returns_empty_string_if_not_clocked_out(): void
    {
        $attendance = new Attendance();
        $attendance->clock_in_at = '2026-10-11 09:00:00';
        $attendance->clock_out_at = null;

        $attendance->setRelation('breaksLogs', collect([]));

        $this->assertEquals('', $attendance->calculateActualWorkingTime());
    }
}
