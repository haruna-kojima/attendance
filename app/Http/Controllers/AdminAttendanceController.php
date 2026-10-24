<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminAttendanceController extends Controller
{
    /**
     * 勤怠一覧画面（管理者） 【/admin/attendance/list】 （日次勤怠一覧）
     */
    public function showDaily(Request $request): View
    {
        $dateStr = (string) $request->query('date', Carbon::today()->format('Y-m-d'));
        $date = Carbon::parse($dateStr);
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // ① 一般ユーザー一覧を取得
        $users = User::where('role', 'general')->get();

        //【N+1解消の核心！】ループに入る前に、本日の勤怠実績と「休憩ログ(breaksLogs)」を
        // 全員分まとめて一括で Eager Loading して脳内に記憶させます！
        $todayAttendances = Attendance::with('breaksLogs')
            ->where('date', $dateStr)
            ->get()
            ->keyBy('user_id');

        $attendanceCollection = new \Illuminate\Database\Eloquent\Collection();

        foreach ($users as $user) {
            $record = $todayAttendances->get($user->id);

            if ($record) {
                $record->clock_in  = $record->clock_in_at ? Carbon::parse($record->clock_in_at)->format('H:i') : null;
                $record->clock_out = $record->clock_out_at ? Carbon::parse($record->clock_out_at)->format('H:i') : null;

                $breakMinutes = $record->calculateTotalBreakMinutes();
                $record->total_break_time = $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : null;
                $record->total_time = $record->calculateActualWorkingTime();

                $attendanceCollection->push($record);
            } else {
                $emptyRecord = new Attendance();
                $emptyRecord->user_id = $user->id;
                $emptyRecord->clock_in = '';
                $emptyRecord->clock_out = '';
                $emptyRecord->total_break_time = '';
                $emptyRecord->total_time = '';

                $attendanceCollection->push($emptyRecord);
            }
        }

        $attendance = $attendanceCollection;
        $attendanceRecords = $attendanceCollection;


        return view('admin.admin-attendance-list', compact('users', 'attendance', 'attendanceRecords', 'date', 'previousDay', 'nextDay'));
    }

    /**
     * スタッフ一覧画面（管理者） 【/admin/staff/list】
     */
    public function showStaffList(): View
    {
        $users = User::where('role', 'general')->get();
        return view('admin.staff-list', compact('users'));
    }

    /**
     * 勤怠詳細画面（管理者） 【/admin/attendance/{id}】 (表示・GET)
     */
    public function showAdminDetail($id): View
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

        return view('admin.admin-detail', compact('attendanceRecord', 'user'));
    }

    public function showApprovalDetail($id): View
    {
        // 申請データ(AttendanceCorrection)を主軸として取得し、リレーションをEager Loadします
        $application = AttendanceCorrection::with('user')->findOrFail($id);
        $user = $application->user;

        // 管理者1枚目のブレード内での「$application->new_date->format()」というCarbonインスタンス利用に備え、
        // データベースから読み込んだ日付文字列をCarbonインスタンスへ動的にパース・キャストしてセットします。
        // ※（モデルの $casts で new_date => 'date' が未設定の場合の保険です）
        if (is_string($application->new_date)) {
            $application->new_date = Carbon::parse($application->new_date);
        } elseif (!$application->new_date && $application->attendance) {
            $application->new_date = Carbon::parse($application->attendance->date);
        }

        // 💡 修正申請で提案されている休憩データをブレードのループ（$application->proposalBreaks）へマッピングして供給します
        // json_encodeされた休憩データを、ブレードがオブジェクト形式のプロパティチェーン（->break_in）で読めるようにFluentに変換します
        $decodedBreaks = json_decode($application->new_breaks, true) ?? [];
        $proposalBreaksCollection = collect();
        foreach ($decodedBreaks as $b) {
            $proposalBreaksCollection->push(new \Illuminate\Support\Fluent([
                'break_in'  => $b['break_in'] ?? '',
                'break_out' => $b['break_out'] ?? '',
            ]));
        }
        $application->proposalBreaks = $proposalBreaksCollection;

        // 💡 戻り先ビューを、承認用の「admin.admin-application-detail」（管理者1枚目）に設定
        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    public function showStaffMonthly(Request $request, $id): View
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
    public function updateDirectly(AdminAttendanceUpdateRequest $request, int $id): RedirectResponse
    {
        $attendance = Attendance::findOrFail($id);

        DB::transaction(function () use ($request, $attendance): void {
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
