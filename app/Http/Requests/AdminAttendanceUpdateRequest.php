<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AdminAttendanceUpdateRequest extends FormRequest
{
    /**
     * 💡 管理者権限チェック（強固なガード）
     * ログインしており、かつロールが「admin」のユーザーのみこの処理を許可します
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * ① 単一項目の基本制限ルール（FN038）
     */
    public function rules(): array
    {
        return [
            'new_clock_in'    => 'required|date_format:H:i',
            'new_clock_out'   => 'required|date_format:H:i',
            'new_break_in.*'  => 'nullable|date_format:H:i',
            'new_break_out.*' => 'nullable|date_format:H:i',
            'comment'         => 'required|string',
        ];
    }

    /**
     * ② 単一項目エラーメッセージの定義
     */
    public function messages(): array
    {
        return [
            'comment.required' => '備考を記入してください',
        ];
    }

    /**
     * ③ 時間の前後関係をチェックする「相関バリデーション｣
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // 基本チェック（未入力や時間形式エラーなど）が既にある場合は比較処理をスキップ
            if ($validator->errors()->any()) {
                return;
            }

            // 画面からの入力値を比較用にCarbonインスタンスに変換
            $clockIn  = Carbon::createFromFormat('H:i', $this->new_clock_in);
            $clockOut = Carbon::createFromFormat('H:i', $this->new_clock_out);

            // ❌ 1. 出勤時間が退勤時間より後、または退勤が出勤より前
            if ($clockIn->greaterThanOrEqualTo($clockOut)) {
                $validator->errors()->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');
                $validator->errors()->add('new_clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩の配列データを取得
            $breakIns  = $this->new_break_in ?? [];
            $breakOuts = $this->new_break_out ?? [];

            foreach ($breakIns as $index => $breakInStr) {
                if (empty($breakInStr)) continue;

                $breakIn = Carbon::createFromFormat('H:i', $breakInStr);
                $breakOutStr = $breakOuts[$index] ?? null;
                $breakOut = $breakOutStr ? Carbon::createFromFormat('H:i', $breakOutStr) : null;

                // ❌ 2. 休憩開始時間が出勤時間より前、または退勤時間より後
                if ($breakIn->lessThan($clockIn) || $breakIn->greaterThan($clockOut)) {
                    $validator->errors()->add("new_break_in.{$index}", '休憩時間が不適切な値です');
                }

                if ($breakOut) {
                    // ❌ 3. 休憩終了時間が退勤時間より後
                    if ($breakOut->greaterThan($clockOut)) {
                        $validator->errors()->add("new_break_out.{$index}", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                }
            }
        });
    }
}
