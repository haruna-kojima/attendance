<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakLog;
use App\Models\AttendanceCorrection;
use App\Http\Requests\AttendanceCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $applications = [];

        if ($user->role === 'admin') {
            $requests = AttendanceCorrection::with(['user', 'attendance'])
                ->latest()
                ->get();

            foreach ($requests as $req) {
                $targetDate = $req->attendance ? $req->attendance->date : ($req->created_at ? $req->created_at->format('Y-m-d') : '');

                $applications[] = [
                    'id'               => $req->id,
                    'approval_status'  => $req->approval_status,
                    'date'             => $targetDate,
                    'comment'          => $req->comment,
                    'application_date' => $req->created_at ? $req->created_at->format('Y-m-d') : '',
                    'user_name'        => $req->user ? $req->user->name : '',
                    'name'             => $req->user ? $req->user->name : '',
                    'user'             => $req->user ? [
                        'name' => $req->user->name
                    ] : null,
                ];
            }

            return view('admin.admin-application-list', compact('applications', 'user'));
        } else {
            $requests = AttendanceCorrection::with('attendance')
                ->where('user_id', $user->id)
                ->latest()
                ->get();

            foreach ($requests as $req) {
                $targetDate = $req->attendance ? $req->attendance->date : ($req->created_at ? $req->created_at->format('Y-m-d') : '');

                $formattedApplications[] = [
                    'id'               => $req->id,
                    'approval_status'  => $req->approval_status,
                    'date'             => $targetDate,
                    'comment'          => $req->comment,
                    'application_date' => $req->created_at ? $req->created_at->format('Y-m-d') : '',
                    'user_name'        => $user->name,
                    'name'             => $user->name,
                ];
            }

            return view('user.user-application-list', compact('formattedApplications', 'user'));
        }
    }

    public function applyCorrection(AttendanceCorrectionRequest $request, $attendanceId): RedirectResponse
    {
        $user = auth()->user();
        $attendance = Attendance::where('user_id', $user->id)->findOrFail($attendanceId);

        DB::transaction(function () use ($request, $user, $attendance): void {
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
                'new_breaks'      => json_encode($breakData),
                'approval_status' => '承認待ち',
                'comment'         => $request->comment,
            ]);
        });

        return redirect()->route('correction.list')->with('success', '修正申請を送信しました。');
    }

    public function approve(Request $request, $attendance_correct_request_id): RedirectResponse
    {
        $correction = AttendanceCorrection::findOrFail($attendance_correct_request_id);
        $attendance = Attendance::findOrFail($correction->attendance_id);

        DB::transaction(function () use ($correction, $attendance): void {
            $dateStr = $attendance->date;

            $attendance->update([
                'clock_in_at'  => Carbon::parse($dateStr . ' ' . $correction->new_clock_in),
                'clock_out_at' => Carbon::parse($dateStr . ' ' . $correction->new_clock_out),
                'remarks'      => $correction->comment,
            ]);

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

        return redirect()->route('correction.list')->with('success', '申請を承認しました。');
    }
}
