<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use App\Http\Requests\AttendanceCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceCorrectionController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // 👑 管理者ユーザー（roleがadmin）の場合 (FN047)
        if ($user->role === 'admin') {
            // 評価対象の「承認待ち」の申請データをすべて取得
            $pendingRequests = AttendanceCorrection::with(['user', 'attendance'])
                ->where('approval_status', '承認待ち')
                ->latest()
                ->get();

            // 評価対象の「承認済み」の申請データをすべて取得
            $approvedRequests = AttendanceCorrection::with(['user', 'attendance'])
                ->where('approval_status', '承認済み')
                ->latest()
                ->get();

            // 管理者用のブレード（固定名）へデータを手渡します
            return view('admin.stamp-correction-request-list', compact('pendingRequests', 'approvedRequests'));
        }

        // 👤 一般ユーザー（roleがgeneral）の場合 (FN031)
        else {
            // 自分の「承認待ち」の申請データを取得
            $pendingRequests = AttendanceCorrection::with('attendance')
                ->where('user_id', $user->id)
                ->where('approval_status', '承認待ち')
                ->latest()
                ->get();

            // 自分の「承認済み」の申請データを取得
            $approvedRequests = AttendanceCorrection::with('attendance')
                ->where('user_id', $user->id)
                ->where('approval_status', '承認済み')
                ->latest()
                ->get();

            // 一般ユーザー用のブレード（固定名）へデータを手渡します
            return view('user.stamp-correction-request-list', compact('pendingRequests', 'approvedRequests'));
        }
    }
    /**
     * 一般ユーザーからの修正申請の保存 
     */
    public function applyCorrection(AttendanceCorrectionRequest $request, $attendanceId)
    {
        $user = auth()->user();
        $attendance = Attendance::where('user_id', $user->id)->findOrFail($attendanceId);

        DB::transaction(function () use ($request, $user, $attendance) {

            // 複数回飛んでくる休憩データを、別のテーブルではなく
            // 配列（Json）として美しく成形して、1つのカラムにまとめて退避させます。
            $breakData = [];
            foreach (($request->new_break_in ?? []) as $index => $breakInStr) {
                if (empty($breakInStr)) continue;
                $breakData[] = [
                    'break_in'  => $breakInStr,
                    'break_out' => $request->new_break_out[$index] ?? null,
                ];
            }

            AttendanceCorrection::create([
                'attendance_id'   => $attendance->id,
                'user_id'         => $user->id,
                'new_clock_in'    => $request->new_clock_in,
                'new_clock_out'   => $request->new_clock_out,
                'new_breaks'      => json_encode($breakData), // 💡 確定テーブルのJson型カラムへ保存
                'approval_status' => '承認待ち',
                'comment'         => $request->comment,
            ]);
        });

        return redirect()->route('correction.list')->with('success', '修正申請を送信しました。');
    }

    /**
     * 管理者による申請の承認処理 
     */
    public function approve(Request $request, $attendance_correct_request_id)
    {
        // ルーティング（routes/web.php）で定義した長名パラメータ名に完全一致させます
        $correction = AttendanceCorrection::findOrFail($attendance_correct_request_id);
        $attendance = Attendance::findOrFail($correction->attendance_id);

        DB::transaction(function () use ($correction, $attendance) {
            $dateStr = $attendance->date;

            $attendance->update([
                'clock_in_at'  => Carbon::parse($dateStr . ' ' . $correction->new_clock_in),
                'clock_out_at' => Carbon::parse($dateStr . ' ' . $correction->new_clock_out),
                'remarks'      => $correction->comment,
            ]);

            // 💡 Json化して退避させていた休憩データをデコード（復元）して本番の休憩テーブルへ反映します
            BreakLog::where('attendance_id', $attendance->id)->delete();

            $newBreaks = json_decode($correction->new_breaks, true) ?? [];
            foreach ($newBreaks as $break) {
                if (!empty($break['break_in'])) {
                    BreakLog::create([
                        'attendance_id' => $attendance->id,
                        'break_in_at'   => Carbon::parse($dateStr . ' ' . $break['break_in']),
                        'break_out_at'  => !empty($break['break_out']) ? Carbon::parse($dateStr . ' ' . $break['break_out']) : null,
                    ]);
                }
            }

            $correction->update(['approval_status' => '承認済み']);
        });

        // 💡 指定の共通ルート名（correction.list）を使ってスマートに転送
        return redirect()->route('correction.list')->with('success', '申請を承認しました。');
    }
}
