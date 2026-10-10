<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Http\Requests\AttendanceRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * 打刻画面の表示
     */
    public function showStamp()
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');

        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();
        $user->attendance_status = $attendance ? $attendance->status : '勤務外';

        $currentDate = Carbon::now();
        $weeks = ['日', '月', '火', '水', '木', '金', '土'];
        $formattedDate = $currentDate->format('Y年m月d日') . '(' . $weeks[$currentDate->dayOfWeek] . ')';
        $formattedTime = $currentDate->format('H:i');

        return view('attendance.stamp', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 出勤・休憩・退勤の打刻保存処理
     */
    public function stamp(AttendanceRequest $request)
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');
        $action = $request->input('action');
        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();

        switch ($action) {
            case 'clock_in':
                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $today,
                    'clock_in_at' => Carbon::now(),
                    'status' => '出勤中',
                ]);
                break;

            case 'break_in':
                BreakLog::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at'   => Carbon::now(),
                ]);
                $attendance->update(['status' => '休憩中']);
                break;

            case 'break_out':
                $latestBreak = BreakLog::where('attendance_id', $attendance->id)->whereNull('break_out_at')->latest('id')->first();
                if ($latestBreak) {
                    $latestBreak->update(['break_out_at' => Carbon::now()]);
                }
                $attendance->update(['status' => '出勤中']);
                break;

            case 'clock_out':
                $attendance->update([
                    'clock_out_at' => Carbon::now(),
                    'status' => '退勤済',
                ]);
                return redirect()->back()->with('success', 'お疲れ様でした。');
        }

        return redirect()->back();
    }

    /**
     * 自身の勤怠一覧画面の表示
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $dateStr = $request->query('date', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $dateStr)->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendances = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$date->copy()->startOfMonth()->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d')])
            ->get()->keyBy('date');

        $formattedAttendanceRecords = [];
        for ($i = 1; $i <= $date->daysInMonth; $i++) {
            $currentDayStr = $date->copy()->day($i)->format('Y-m-d');
            $attendance = $attendances->get($currentDayStr);

            if ($attendance) {
                $totalBreakMinutes = 0;
                foreach ($attendance->breaks as $break) {
                    if ($break->break_in_at && $break->break_out_at) {
                        $totalBreakMinutes += Carbon::parse($break->break_in_at)->diffInMinutes(Carbon::parse($break->break_out_at));
                    }
                }

                $formattedAttendanceRecords[] = [
                    'id' => $attendance->id,
                    'date' => $date->copy()->day($i)->format('m/d'),
                    'clock_in' => $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
                    'clock_out' => $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
                    'total_break_time' => $totalBreakMinutes > 0 ? sprintf('%02d:%02d', floor($totalBreakMinutes / 60), $totalBreakMinutes % 60) : '',
                    'total_time' => ($attendance->clock_in_at && $attendance->clock_out_at) ? sprintf('%02d:%02d', floor((Carbon::parse($attendance->clock_in_at)->diffInMinutes(Carbon::parse($attendance->clock_out_at)) - $totalBreakMinutes) / 60), (Carbon::parse($attendance->clock_in_at)->diffInMinutes(Carbon::parse($attendance->clock_out_at)) - $totalBreakMinutes) % 60) : '',
                ];
            } else {
                $formattedAttendanceRecords[] = [
                    'id' => null,
                    'date' => $date->copy()->day($i)->format('m/d'),
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => '',
                    'total_time' => ''
                ];
            }
        }

        return view('attendance.index', compact('formattedAttendanceRecords', 'date', 'previousMonth', 'nextMonth'));
    }
}
