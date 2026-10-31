<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->route('attendanceRecord');
        $recordId = is_object($record) ? $record->id : $record;

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances', 'date')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                })->ignore($recordId),
            ],
            'clock_in' => 'sometimes|required|date_format:H:i:s',
            'clock_out' => [
                'nullable',
                'date_format:H:i:s',
                function ($attribute, $value, $fail) use ($record) {
                    $clockIn = $this->input('clock_in') ?? ($record->clock_in_at ? Carbon::parse($record->clock_in_at)->format('H:i:s') : null);
                    if ($clockIn && $value <= $clockIn) {
                        $fail('退勤時刻は出勤時刻より後の時刻を指定してください。');
                    }
                }
            ],
            'comment' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
