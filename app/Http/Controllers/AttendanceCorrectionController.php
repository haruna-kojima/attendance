<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use App\Models\BreakCorrection;
use App\Http\Requests\AttendanceCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceCorrectionController extends Controller
{
    /**
     * 一般ユーザーからの修正申請の保存
     */
    public function applyCorrection(AttendanceCorrectionRequest $request, $attendanceId)
    {
        $user = auth()->user();
        $attendance = Attendance::where('user_id', $user->id)->findOrFail($attendanceId);

        DB::transaction(function () use ($request, $user, $attendance) {
            $correction = AttendanceCorrection::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'new_clock_in' => $request->new_clock_in,
                'new_clock_out' => $request->new_clock_out,
                'approval_status' => '承認待ち',
                'comment' => $request->comment,
            ]);

            foreach (($request->new_break_in ?? []) as $index => $breakInStr) {
                if (empty($breakInStr)) continue;
                BreakCorrection::create([
                    'attendance_correction_id' => $correction->id,
                    'new_break_in' => $breakInStr,
                    'new_break_out' => $request->new_break_out[$index] ?? null,
                ]);
            }
        });

        return redirect()->route('attendance.index')->with('success', '修正申請を送信しました。');
    }

    /**
     * 管理者による申請の承認処理
     */
    public function approve(Request $request, $id)
    {
        $correction = AttendanceCorrection::with('breakCorrections')->findOrFail($id);
        $attendance = Attendance::findOrFail($correction->attendance_id);

        DB::transaction(function () use ($correction, $attendance) {
            $dateStr = $attendance->date;

            $attendance->update([
                'clock_in_at' => Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $correction->new_clock_in),
                'clock_out_at' => Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $correction->new_clock_out),
                'remarks' => $correction->comment,
            ]);

            BreakLog::where('attendance_id', $attendance->id)->delete();
            foreach ($correction->breakCorrections as $breakCorrection) {
                BreakLog::create([
                    'attendance_id' => $attendance->id,
                    'break_in_at' => $dateStr . ' ' . $breakCorrection->new_break_in . ':00',
                    'break_out_at' => $breakCorrection->new_break_out ? $dateStr . ' ' . $breakCorrection->new_break_out . ':00' : null,
                ]);
            }

            $correction->update(['approval_status' => '承認済み']);
        });

        return redirect('/stamp_correction_request/list')->with('success', '申請を承認しました。');
    }
}
