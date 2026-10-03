<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakLogModel;
use App\Http\Requests\AttendanceRequest;
use App\Http\Requests\AttendanceCorrectionRequest;
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

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $status = $attendance ? $attendance->status : '勤務外';

        $user->attendance_status = $status;

        // FN018：UI表示形式の整合（日本語曜日つきの年月日）
        $currentDate = Carbon::now();
        $weeks = ['日', '月', '火', '水', '木', '金', '土'];
        $weekName = $weeks[$currentDate->dayOfWeek];
        $formattedDate = $currentDate->format('Y年m月d日') . '(' . $weekName . ')';

        // Bladeの初期表示用の時刻形式（秒なしの H:i 形式）
        $formattedTime = $currentDate->format('H:i');

        return view('attendance.stamp', compact('user', 'formattedDate', 'formattedTime'));
    }


    public function stamp(AttendanceRequest $request)
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');
        $action = $request->input('action');

        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();

        switch ($action) {
            case 'clock_in': // 出勤
                Attendance::create([
                    'user_id'     => $user->id,
                    'date'        => $today,
                    'clock_in_at' => Carbon::now(), // timestamp型
                    'status'      => '出勤中',
                ]);
                break;

            case 'break_in':
                BreakLogModel::create([
                    'attendance_id' => $attendance->id,
                    'break_in'      => Carbon::now()->format('H:i:s'),
                ]);
                $attendance->update(['status' => '休憩中']);
                break;

            case 'break_out': // 休憩戻
                $latestBreak = BreakLogModel::where('attendance_id', $attendance->id)
                    ->whereNull('break_out')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($latestBreak) {
                    $latestBreak->update([
                        'break_out' => Carbon::now()->format('H:i:s')
                    ]);
                }
                $attendance->update(['status' => '出勤中']);
                break;

            case 'clock_out': // 退勤
                $attendance->update([
                    'clock_out_at' => Carbon::now(), // timestamp型
                    'status'       => '退勤済',
                ]);
                break;
        }
    
    public function index(Request $request)
    {
        $user = auth()->user();

        // 1. クエリパラメータから対象の月を取得（例: ?date=2026-09。なければ現在の月）
        $dateStr = $request->query('date', Carbon::now()->format('Y-m'));
        try {
            $date = Carbon::createFromFormat('Y-m', $dateStr)->startOfMonth();
        } catch (\Exception $e) {
            $date = Carbon::now()->startOfMonth(); // 不正な文字が入った場合は今月にフォールバック
        }

        // 2. 前月・翌月のリンク用パラメータ文字列を作成（FN024-2, FN024-3）
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 3. 対象ユーザーの指定月における勤怠レコードをまとめて取得（N+1問題対策に休憩もEagerロード）
        $attendances = Attendance::with('breakLogs') // 👈 Attendanceモデル側に定義した休憩リレーション名
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $date->copy()->startOfMonth()->format('Y-m-d'),
                $date->copy()->endOfMonth()->format('Y-m-d')
            ])
            ->get()
            ->keyBy('date'); // データベース上の '2026-09-01' のような日付文字列を配列のキーにする

        $daysInMonth = $date->daysInMonth; // その月が何日あるか（28〜31日）
        $formattedAttendanceRecords = [];

        // 4. 1日から末日までループを回して、全日付の行（枠）を強制的に生成する
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $currentDay = $date->copy()->day($i);
            $currentDayStr = $currentDay->format('Y-m-d'); // 検索用
            $displayDate = $currentDay->format('m/d');     // 8枚目Bladeの「日付（m/d）」表示用形式

            // その日の勤怠データがDBにあるか探す
            $attendance = $attendances->get($currentDayStr);

            if ($attendance) {
                // 🕒 出退勤時刻の整形（timestamp型の clock_in_at から H:i 形式を切り出す）
                $clockIn = $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '';
                $clockOut = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '';

                // 🟡 複数回ある休憩レコードから、合計休憩時間（分単位）を算出（FN021-5-a対応）
                $totalBreakMinutes = 0;
                if (isset($attendance->breakLogs)) {
                    foreach ($attendance->breakLogs as $break) {
                        if ($break->break_in && $break->break_out) {
                            $totalBreakMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
                        }
                    }
                }

                // 分単位の数値を、Bladeの出力に合わせた「G:i」文字列に変換（例: 60分 ➔ 01:00）
                $totalBreakTime = $totalBreakMinutes > 0 
                    ? sprintf('%02d:%02d', floor($totalBreakMinutes / 60), $totalBreakMinutes % 60) 
                    : '';

                // 🔵 実労働合計時間（出勤から退勤までの時間 - 休憩合計時間）の計算
                $totalTime = '';
                if ($attendance->clock_in_at && $attendance->clock_out_at) {
                    $workingMinutes = Carbon::parse($attendance->clock_in_at)->diffInMinutes(Carbon::parse($attendance->clock_out_at));
                    $actualWorkingMinutes = $workingMinutes - $totalBreakMinutes;
                    
                    if ($actualWorkingMinutes > 0) {
                        $totalTime = sprintf('%02d:%02d', floor($actualWorkingMinutes / 60), $actualWorkingMinutes % 60);
                    }
                }

                // 配列に整形したデータを格納
                $formattedAttendanceRecords[] = [
                    'id'               => $attendance->id,
                    'date'             => $displayDate,
                    'clock_in'         => $clockIn,
                    'clock_out'        => $clockOut,
                    'total_break_time' => $totalBreakTime,
                    'total_time'       => $totalTime,
                ];
            } else {
                // ⚠️ FN023-2: その日にまだ打刻データ（勤怠情報）がない場合は、枠線や日付だけ出して他をすべて空文字にする
                $formattedAttendanceRecords[] = [
                    'id'               => null,
                    'date'             => $displayDate,
                    'clock_in'         => '',
                    'clock_out'        => '',
                    'total_break_time' => '',
                    'total_time'       => '',
                ];
            }
        }

        // 8枚目のBladeが必要とするすべての変数をコンパクトにしてビューに引き渡す
        return view('attendance.index', compact(
            'formattedAttendanceRecords',
            'date',
            'previousMonth',
            'nextMonth'
        ));

        return redirect()->back();
    }
}
