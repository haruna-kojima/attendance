<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * 打刻画面（勤怠登録画面）の表示
     */
    public function showStamp()
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');

        // 今日の勤怠データを取得
        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();

        // モデル側のenum定義に合わせ、データベースの日本語ステータスを画面用にセット
        $user->attendance_status = $attendance ? $attendance->status : '勤務外';

        // 💡 現在の日時情報をUIと同じ形式（例: 2026年10月17日(土)）で出力
        $currentDate = Carbon::now();
        $weeks = ['日', '月', '火', '水', '木', '金', '土'];
        $formattedDate = $currentDate->format('Y年m月d日') . '(' . $weeks[$currentDate->dayOfWeek] . ')';
        $formattedTime = $currentDate->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 出勤・休憩・退勤の打刻保存処理
     */
    public function stamp(Request $request)
    {
        $user = auth()->user();
        $today = Carbon::today()->format('Y-m-d');
        $action = $request->input('action');

        // 今日の勤怠データを取得
        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();

        switch ($action) {
            case 'clock_in':
                // 「出勤」は1日に1回だけ押下できるガード
                if ($attendance) {
                    return redirect()->back()->with('error', '本日はすでに出勤しています。');
                }

                Attendance::create([
                    'user_id'     => $user->id,
                    'date'        => $today,
                    'clock_in_at' => Carbon::now(),
                    'status'      => '出勤中',
                ]);
                break;

            case 'break_in':
                // 出勤中のみ休憩可能
                if (!$attendance || $attendance->status !== '出勤中') {
                    return redirect()->back()->with('error', '出勤中ではないため休憩に入れません。');
                }

                // 💡 モデルクラス名「BreakLog」に対応した休憩追加処理
                BreakLog::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at'   => Carbon::now(),
                ]);
                $attendance->update(['status' => '休憩中']);
                break;

            case 'break_out':
                // 休憩中のみ休憩戻りが可能
                if (!$attendance || $attendance->status !== '休憩中') {
                    return redirect()->back()->with('error', '休憩中ではありません。');
                }

                // まだ戻り時刻が入っていない最新の休憩ログを特定して上書き更新
                $latestBreak = BreakLog::where('attendance_id', $attendance->id)
                    ->whereNull('break_out_at')
                    ->latest('id')
                    ->first();

                if ($latestBreak) {
                    $latestBreak->update(['break_out_at' => Carbon::now()]);
                }
                $attendance->update(['status' => '出勤中']);
                break;

            case 'clock_out':
                // 「退勤」ボタンは1日に1回だけ押下できるガード
                if (!$attendance || $attendance->status !== '出勤中') {
                    return redirect()->back()->with('error', '退勤できる状態ではありません。');
                }

                $attendance->update([
                    'clock_out_at' => Carbon::now(),
                    'status'       => '退勤済',
                ]);

                // 退勤」ボタンを押下した時に、「お疲れ様でした。」とメッセージをセッションに渡す
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

        // 遷移した際に現在の月を表示。前月・翌月ボタンのクエリに対応
        $dateStr = $request->query('date', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $dateStr)->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 月初から月末までの期間内の勤怠実績データを一括取得
        $attendances = Attendance::with('breaksLogs')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$date->copy()->startOfMonth()->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d')])
            ->get()
            ->keyBy('date');

        $formattedAttendanceRecords = [];

        // 💡 該当月の日数（28〜31日）分ループし、実績がない日も空白レコードとして美しく再現
        for ($i = 1; $i <= $date->daysInMonth; $i++) {
            $currentDayStr = $date->copy()->day($i)->format('Y-m-d');
            $attendance = $attendances->get($currentDayStr);

            if ($attendance) {
                // 💡 モデル側に集計処理が書いてあるため、コントローラは1行呼ぶだけで「極限までスリム」を維持
                $breakMinutes = $attendance->calculateTotalBreakMinutes();
                $totalBreakTime = $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : '';

                $formattedAttendanceRecords[] = [
                    'id'               => $attendance->id,
                    'date'             => $date->copy()->day($i)->format('m/d'),
                    'clock_in'         => $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
                    'clock_out'        => $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
                    'total_break_time' => $totalBreakTime,
                    'total_time'       => $attendance->calculateActualWorkingTime(), // モデルから取得
                ];
            } else {
                // F勤怠情報がないフィールド（欠勤日や未来の日付など）は空白にする
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

        return view('user.user-attendance-list', compact('formattedAttendanceRecords', 'date', 'previousMonth', 'nextMonth'));
    }

    /**
     * 勤怠詳細画面の表示
     */
    public function showDetail($id)
    {
        $attendance = Attendance::with('breaksLogs')->findOrFail($id);
        $user = auth()->user();

        // Blade画面が指定するデータ構造(配列)にトランスフォーム(詰め替え)して引き渡す
        $attendanceRecord = [
            'id'            => $attendance->id,
            'year'          => Carbon::parse($attendance->date)->format('Y年'),
            'date'          => Carbon::parse($attendance->date)->format('n月j日'),
            'new_clock_in'  => $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
            'new_clock_out' => $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
            'comment'       => $attendance->remarks,
            'breaks'        => [],
        ];

        // 休憩回数分ループさせて既存データを出力
        foreach ($attendance->breaksLogs as $break) {
            $attendanceRecord['breaks'][] = [
                'break_in'  => $break->break_in_at ? Carbon::parse($break->break_in_at)->format('H:i') : '',
                'break_out' => $break->break_out_at ? Carbon::parse($break->break_out_at)->format('H:i') : '',
            ];
        }

        // 現在この勤怠に対して「承認待ち」の修正申請データがあるかをチェック
        $application = AttendanceCorrection::where('attendance_id', $id)
            ->where('user_id', $user->id)
            ->where('approval_status', '承認待ち')
            ->first();

        // 承認待ちが存在する場合はステータスを切り替え、Blade側で「承認待ちのため修正はできません。」の警告表示やロックを連動
        $attendanceRecord['approval_status'] = $application ? '承認待ち' : '通常';

        return view('user.user-detail', compact('attendanceRecord', 'user', 'application'));
    }
}
