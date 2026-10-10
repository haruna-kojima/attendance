<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminAttendanceController extends Controller
{
    /**
     * 勤怠一覧画面（管理者） 【/admin/attendance/list】 （日次勤怠一覧）
     */
    public function showDaily(Request $request)
    {
        $dateStr = $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateStr);
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('role', 'general')->get();
        $attendanceRecords = Attendance::with('breakLogs')->where('date', $dateStr)->get()->keyBy('user_id');

        $formattedRecords = [];
        foreach ($users as $user) {
            $attendance = $attendanceRecords->get($user->id);

            if ($attendance) {
                $totalBreakMinutes = 0;
                foreach ($attendance->breakLogs as $break) {
                    if ($break->break_in_at && $break->break_out_at) {
                        $totalBreakMinutes += Carbon::parse($break->break_in_at)->diffInMinutes(Carbon::parse($break->break_out_at));
                    }
                }

                $formattedRecords[] = [
                    'id' => $attendance->id,
                    'name' => $user->name,
                    'clock_in' => $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
                    'clock_out' => $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
                    'total_break_time' => $totalBreakMinutes > 0 ? sprintf('%02d:%02d', floor($totalBreakMinutes / 60), $totalBreakMinutes % 60) : '',
                    'total_time' => ($attendance->clock_in_at && $attendance->clock_out_at) ? sprintf('%02d:%02d', floor((Carbon::parse($attendance->clock_in_at)->diffInMinutes(Carbon::parse($attendance->clock_out_at)) - $totalBreakMinutes) / 60), (Carbon::parse($attendance->clock_in_at)->diffInMinutes(Carbon::parse($attendance->clock_out_at)) - $totalBreakMinutes) % 60) : '',
                ];
            } else {
                $formattedRecords[] = [
                    'id' => null,
                    'name' => $user->name,
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => '',
                    'total_time' => ''
                ];
            }
        }

        return view('admin.attendance-daily', compact('formattedRecords', 'date', 'previousDay', 'nextDay'));
    }

    /**
     * スタッフ一覧画面（管理者） 【/admin/staff/list】
     */
    public function showStaffList()
    {
        $users = User::where('role', 'general')->get();
        return view('admin.staff-list', compact('users'));
    }

    /**
     * 勤怠詳細画面（管理者） 【/admin/attendance/{id}】 (表示・GET)
     */
    public function showAdminDetail($id)
    {
        $attendance = Attendance::with('breakLogs')->findOrFail($id);
        $user = User::findOrFail($attendance->user_id);

        $attendanceRecord = [
            'id'        => $attendance->id,
            'year'      => Carbon::parse($attendance->date)->format('Y年'),
            'date'      => Carbon::parse($attendance->date)->format('n月j日'),
            'clock_in'  => $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
            'clock_out' => $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
            'comment'   => $attendance->remarks,
            'breaks'    => [],
        ];

        foreach ($attendance->breakLogs as $break) {
            $attendanceRecord['breaks'][] = [
                'break_in'  => $break->break_in_at ? Carbon::parse($break->break_in_at)->format('H:i') : '',
                'break_out' => $break->break_out_at ? Carbon::parse($break->break_out_at)->format('H:i') : '',
            ];
        }

        $application = $attendance->attendanceCorrections()->where('approval_status', '承認待ち')->first();
        $attendanceRecord['approval_status'] = $application ? '承認待ち' : '通常';

        return view('admin.attendance-detail', compact('attendanceRecord', 'user', 'application'));
    }

    /**
     * スタッフ別勤怠一覧画面（管理者） 【/admin/attendance/staff/{id}】
     */
    public function showStaffMonthly(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $dateStr = $request->query('date', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $dateStr)->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords = [];
        $attendances = Attendance::with('breakLogs')->where('user_id', $user->id)
            ->whereBetween('date', [$date->copy()->startOfMonth()->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d')])
            ->get()->keyBy('date');

        for ($i = 1; $i <= $date->daysInMonth; $i++) {
            $currentDayStr = $date->copy()->day($i)->format('Y-m-d');
            $attendance = $attendances->get($currentDayStr);

            if ($attendance) {
                $totalBreakMinutes = 0;
                foreach ($attendance->breakLogs as $break) {
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

        return view('admin.staff-monthly', compact('formattedAttendanceRecords', 'user', 'date', 'previousMonth', 'nextMonth'));
    }

    /**
     * 勤怠詳細画面（管理者） 【/admin/attendance/{id}】 (直接修正・POST)
     */
    public function updateDirectly(AdminAttendanceUpdateRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        DB::transaction(function () use ($request, $attendance) {
            $dateStr = $attendance->date;

            $attendance->update([
                'clock_in_at' => Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $request->new_clock_in),
                'clock_out_at' => Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $request->new_clock_out),
                'remarks' => $request->comment,
            ]);

            $existingBreaks = BreakLog::where('attendance_id', $attendance->id)->orderBy('id', 'asc')->get();
            foreach ($existingBreaks as $index => $breakLog) {
                $breakInStr = $request->new_break_in[$index] ?? null;
                if ($breakInStr) {
                    $breakLog->update([
                        'break_in_at' => $dateStr . ' ' . $breakInStr . ':00',
                        'break_out_at' => ($request->new_break_out[$index] ?? null) ? $dateStr . ' ' . $request->new_break_out[$index] . ':00' : null,
                    ]);
                } else {
                    $breakLog->delete();
                }
            }

            $newIndex = count($existingBreaks);
            if (!empty($request->new_break_in[$newIndex])) {
                BreakLog::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at' => $dateStr . ' ' . $request->new_break_in[$newIndex] . ':00',
                    'break_out_at' => ($request->new_break_out[$newIndex] ?? null) ? $dateStr . ' ' . $request->new_break_out[$newIndex] . ':00' : null,
                ]);
            }
        });

        return redirect()->route('admin.attendance.daily', ['date' => $attendance->date])->with('success', '修正完了しました。');
    }
}
