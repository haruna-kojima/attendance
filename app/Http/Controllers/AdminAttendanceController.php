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

        // ① 一般ユーザー一覧を取得
        $users = User::where('role', 'general')->get();

        // 💡 解決の核心：データが1件もない日でも、Bladeの「$attendance->where()」が走った時に
        // 2周目で null になって気絶するのを防ぐため、全ユーザー分の「空のモデルの入れ物」をあらかじめ用意して手渡します！
        $attendanceCollection = collect();

        foreach ($users as $user) {
            // 本日の実績をデータベースから検索
            $record = Attendance::with('breaksLogs')
                ->where('user_id', $user->id)
                ->where('date', $dateStr)
                ->first();

            if ($record) {
                // データがある日は表示用に時間をH:i形式に成形
                $record->clock_in  = $record->clock_in_at ? Carbon::parse($record->clock_in_at)->format('H:i') : null;
                $record->clock_out = $record->clock_out_at ? Carbon::parse($record->clock_out_at)->format('H:i') : null;

                $breakMinutes = $record->calculateTotalBreakMinutes();
                $record->total_break_time = $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : null;
                $record->total_time = $record->calculateActualWorkingTime();

                $attendanceCollection->push($record);
            } else {
                // 💡 【重要ガード】データが1件もない日（土日など）は、このユーザー用の「空のAttendanceモデル」をその場で作ります
                // これにより、Bladeのwhereがヒットした際、nullではなく「空のオブジェクト」が代入されるためクラッシュを100%回避できます！
                $emptyRecord = new Attendance();
                $emptyRecord->user_id = $user->id;
                $emptyRecord->clock_in = '';
                $emptyRecord->clock_out = '';
                $emptyRecord->total_break_time = '';
                $emptyRecord->total_time = '';

                $attendanceCollection->push($emptyRecord);
            }
        }

        // 変数名をBladeが求めている「$attendance」として引き渡します
        $attendance = $attendanceCollection;

        return view('admin.admin-attendance-list', compact('users', 'attendance', 'date', 'previousDay', 'nextDay'));
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
        $attendance = Attendance::with('breaksLogs')->findOrFail($id);
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

        foreach ($attendance->breaksLogs as $break) {
            $attendanceRecord['breaks'][] = [
                'break_in'  => $break->break_in_at ? Carbon::parse($break->break_in_at)->format('H:i') : '',
                'break_out' => $break->break_out_at ? Carbon::parse($break->break_out_at)->format('H:i') : '',
            ];
        }

        $application = $attendance->attendanceCorrections()->where('approval_status', '承認待ち')->first();
        $attendanceRecord['approval_status'] = $application ? '承認待ち' : '通常';

        return view('admin.admin-detail', compact('attendanceRecord', 'user', 'application'));
    }

    /**
     * スタッフ別勤怠一覧画面（管理者） 【/admin/attendance/staff/{id}】
     * 💡 解決策：Bladeの41行目「$attendance['id']」という配列の呼び出し形式に完全同期させるため、
     * データがある日も無い日も、すべてカチッとした「連想配列(array)」の形に統一して手渡します！
     */
    public function showStaffMonthly(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $dateStr = $request->query('date', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $dateStr)->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords = [];
        $attendances = Attendance::with('breaksLogs')->where('user_id', $user->id)
            ->whereBetween('date', [$date->copy()->startOfMonth()->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d')])
            ->get()->keyBy('date');

        for ($i = 1; $i <= $date->daysInMonth; $i++) {
            $currentDayStr = $date->copy()->day($i)->format('Y-m-d');
            $attendance = $attendances->get($currentDayStr);

            if ($attendance) {
                // 💡 データがある日：Bladeの['キー名']で開けるように配列形式で格納
                $breakMinutes = $attendance->calculateTotalBreakMinutes();
                $totalBreakTime = $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : '';

                $formattedAttendanceRecords[] = [
                    'id'               => $attendance->id,
                    'date'             => $date->copy()->day($i)->format('m/d'),
                    'clock_in'         => $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
                    'clock_out'        => $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
                    'total_break_time' => $totalBreakTime,
                    'total_time'       => $attendance->calculateActualWorkingTime(),
                ];
            } else {
                // 💡 データがない欠勤日（土日など）：こちらも完全に同じ構造の配列で空枠を作ります
                // これにより、Bladeの41行目（$attendance['id']）に差し掛かってもエラーを100%回避できます！
                $formattedAttendanceRecords[] = [
                    'id'               => null,
                    'date'             => $date->copy()->day($i)->format('m/d'),
                    'clock_in'         => '',
                    'clock_out'        => '',
                    'total_break_time' => '',
                    'total_time'       => ''
                ];
            }
        }

        return view('admin.staff-attendance-list', compact('formattedAttendanceRecords', 'user', 'date', 'previousMonth', 'nextMonth'));
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
                'clock_in_at' => Carbon::parse($dateStr . ' ' . $request->new_clock_in),
                'clock_out_at' => Carbon::parse($dateStr . ' ' . $request->new_clock_out),
                'remarks' => $request->comment,
            ]);

            $existingBreaks = BreakLog::where('attendance_id', $attendance->id)->orderBy('id', 'asc')->get();
            foreach ($existingBreaks as $index => $breakLog) {
                $breakInStr = $request->new_break_in[$index] ?? null;
                $breakOutStr = $request->new_break_out[$index] ?? null;

                if ($breakInStr) {
                    $breakLog->update([
                        'break_in_at' => Carbon::parse($dateStr . ' ' . $breakInStr),
                        'break_out_at' => $breakOutStr ? Carbon::parse($dateStr . ' ' . $breakOutStr) : null,
                    ]);
                } else {
                    $breakLog->delete();
                }
            }

            $newIndex = count($existingBreaks);
            $addedBreakIn = $request->new_break_in[$newIndex] ?? null;
            $addedBreakOut = $request->new_break_out[$newIndex] ?? null;

            if ($addedBreakIn) {
                BreakLog::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at' => Carbon::parse($dateStr . ' ' . $addedBreakIn),
                    'break_out_at' => $addedBreakOut ? Carbon::parse($dateStr . ' ' . $addedBreakOut) : null,
                ]);
            }
        });

        return redirect()->route('admin.attendance.daily', ['date' => $attendance->date])->with('success', '勤怠データを修正しました。');
    }
}
