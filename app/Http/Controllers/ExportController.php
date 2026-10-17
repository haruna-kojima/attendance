<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class ExportController extends Controller
{
    /**
     * 指定スタッフ・指定年月の勤怠データをCSV出力する（FN045）
     */
    public function exportCsv(Request $request)
    {
        // 6枚目の隠しフィールドから送信されてくるパラメータを取得
        $userId = $request->input('user_id');
        $yearMonth = $request->input('year_month');

        $user = User::findOrFail($userId);
        $date = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();

        // 💡 リレーション名を実際の定義「breaksLogs」に完全一致させて500エラーを防ぎます
        $attendances = Attendance::with('breaksLogs')->where('user_id', $user->id)
            ->whereBetween('date', [
                $date->copy()->startOfMonth()->format('Y-m-d'),
                $date->copy()->endOfMonth()->format('Y-m-d')
            ])
            ->get()->keyBy('date');

        // メモリを圧迫しないよう、StreamedResponseを使ってCSVを1行ずつ安全に出力（実務のベストプラクティス）
        $response = new StreamedResponse(function () use ($date, $attendances) {
            $handle = fopen('php://output', 'w');

            // Excelでの文字化け（BOM）を完全に防止する魔法のコード
            fwrite($handle, "\xEF\xBB\xBF");

            // CSVのヘッダー（1行目）を出力
            fputcsv($handle, ['日付', '出勤時間', '退勤時間', '休憩時間', '労働時間']);

            // 1日から末日までループを回して出力
            for ($i = 1; $i <= $date->daysInMonth; $i++) {
                $currentDayStr = $date->copy()->day($i)->format('Y-m-d');
                $attendance = $attendances->get($currentDayStr);

                if ($attendance) {
                    $breakMinutes = $attendance->calculateTotalBreakMinutes();
                    fputcsv($handle, [
                        $date->copy()->day($i)->format('m/d'),
                        $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
                        $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
                        $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : '00:00',
                        $attendance->calculateActualWorkingTime() ?: '00:00' // モデル側のUnitテスト済みロジックを再利用
                    ]);
                } else {
                    // ⚠️ データがない日は日付枠だけ残して他を空白・00:00で出力
                    fputcsv($handle, [$date->copy()->day($i)->format('m/d'), '', '', '00:00', '00:00']);
                }
            }
            fclose($handle);
        });

        // ブラウザに「これはダウンロード用のCSVファイルだよ」と伝えるヘッダー情報
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $user->name . '_' . $yearMonth . '_attendance.csv"');

        return $response;
    }
}
