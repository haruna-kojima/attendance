<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class AttendanceReportService
{
    // 一般ユーザーの勤怠レポートデータを集計する
    public function getMonthlyReportData(User $user): array
    {
        $startPeriod = Carbon::now()->subMonths(5)->startOfMonth()->format('Y-m-d');
        $endPeriod = Carbon::now()->endOfMonth()->format('Y-m-d');

        $records = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startPeriod, $endPeriod])
            ->get();

        $summary = $this->calculateSummary($records);
        $monthlyTrend = $this->calculateMonthlyTrend($records);
        $anomalies = $this->calculateAnomalies($records);

        return compact('summary', 'monthlyTrend', 'anomalies');
    }

    private function calculateSummary($records): array
    {
        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;
        $workingDaysCount = 0;

        foreach ($records as $record) {
            $workMinutes = $this->getWorkMinutes($record);

            if ($workMinutes > 0) {
                $totalWorkMinutes += $workMinutes;
                $workingDaysCount++;

                if ($workMinutes > 480) {
                    $totalOvertimeMinutes += ($workMinutes - 480);
                }
            }
        }

        return [
            'total_work_minutes'     => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes'       => $workingDaysCount > 0 ? floor($totalWorkMinutes / $workingDaysCount) : 0,
        ];
    }

    private function calculateMonthlyTrend($records): array
    {
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthObj = Carbon::now()->subMonths($i);
            $monthKey = $monthObj->format('Y-m');

            $monthRecords = $records->filter(function ($r) use ($monthKey) {
                return strpos($r->date, $monthKey) === 0;
            });

            $mWork = 0;
            $mOver = 0;
            foreach ($monthRecords as $mr) {
                $mrMin = $this->getWorkMinutes($mr);
                $mWork += $mrMin;
                if ($mrMin > 480) {
                    $mOver += ($mrMin - 480);
                }
            }

            $monthlyTrend[] = [
                'month'            => $monthObj->format('Y/m'),
                'work_minutes'     => $mWork,
                'overtime_minutes' => $mOver,
            ];
        }
        return $monthlyTrend;
    }

    private function calculateAnomalies($records): array
    {
        $currentMonthKey = Carbon::now()->format('Y-m');
        $thisMonthRecords = $records->filter(function ($r) use ($currentMonthKey) {
            return strpos($r->date, $currentMonthKey) === 0;
        });

        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        foreach ($thisMonthRecords as $tmr) {
            if ($tmr->clock_in_at && Carbon::parse($tmr->clock_in_at)->format('H:i') > '09:00') {
                $lateCount++;
            }
            if ($tmr->clock_out_at && Carbon::parse($tmr->clock_out_at)->format('H:i') < '18:00') {
                $earlyLeaveCount++;
            }

            $tmrMin = $this->getWorkMinutes($tmr);
            if ($tmrMin > 600) {
                $longWorkCount++;
            }
        }

        return [
            'late_count'        => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count'   => $longWorkCount,
        ];
    }

    private function getWorkMinutes($record): int
    {
        if (method_exists($record, 'calculateTotalWorkingMinutes')) {
            return $record->calculateTotalWorkingMinutes();
        }

        if ($record->clock_in_at && $record->clock_out_at) {
            $in = Carbon::parse($record->clock_in_at);
            $out = Carbon::parse($record->clock_out_at);
            $breakMin = method_exists($record, 'calculateTotalBreakMinutes') ? $record->calculateTotalBreakMinutes() : 0;
            return max(0, $in->diffInMinutes($out) - $breakMin);
        }

        return 0;
    }
}
