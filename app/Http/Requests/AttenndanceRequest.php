<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 画面のボタンから「どの処理か」を識別する action（clock-in, clock-out 等）が送られてくる前提
        return [
            'action' => 'required|in:clock-in,clock-out,break-in,break-out',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = auth()->user();
            $today = Carbon::today()->format('Y-m-d');

            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            $currentStatus = $attendance ? $attendance->status : '勤務外';
            $action = $this->input('action');

            // 各アクションごとのステータス矛盾チェック
            if ($action === 'clock-in' && $currentStatus !== '勤務外') {
                $validator->errors()->add('attendance_error', '本日はすでに出勤しているか、出勤できない状態です。');
            }
            if ($action === 'break-in' && $currentStatus !== '出勤中') {
                $validator->errors()->add('attendance_error', '出勤中のみ休憩に入ることができます。');
            }
            if ($action === 'break-out' && $currentStatus !== '休憩中') {
                $validator->errors()->add('attendance_error', '休憩中のみ戻ることができます。');
            }
            if ($action === 'clock-out' && $currentStatus !== '出勤中') {
                $validator->errors()->add('attendance_error', '退勤処理ができない状態です。');
            }
        });
    }
}
