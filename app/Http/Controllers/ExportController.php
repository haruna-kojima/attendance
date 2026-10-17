<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * 管理者用 スタッフ別月次勤怠データのCSVダウンロード機能
     */
    public function exportCsv(Request $request, $id): StreamedResponse
    {
        $user = User::findOrFail($id);

        $dateStr = (string) $request->query('date', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $dateStr)->startOfMonth();

        $attendances = Attendance::with('breaksLogs')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $date->copy()->startOfMonth()->format('Y-m-d'),
                $date->copy()->endOfMonth()->format('Y-m-d')
            ])
            ->get()
            ->keyBy('date');

        $response = new StreamedResponse(function () use ($date, $attendances): void {
            $stream = fopen('php://output', 'w');

            fwrite($stream, pack('C*', 0xEF, 0xBB, 0xBF));

            fputcsv($stream, ['日付', '出勤時間', '退勤時間', '休憩時間', '労働時間']);

            for ($i = 1; $i <= $date->daysInMonth; $i++) {
                $currentDayStr = $date->copy()->day($i)->format('Y-m-d');
                $attendance = $attendances->get($currentDayStr);

                if ($attendance) {
                    $breakMinutes = $attendance->calculateTotalBreakMinutes();
                    $totalBreakTime = $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : '00:00';

                    fputcsv($stream, [
                        $date->copy()->day($i)->format('m/d'),
                        $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '',
                        $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '',
                        $totalBreakTime,
                        $attendance->calculateActualWorkingTime() ?: '00:00'
                    ]);
                } else {
                    fputcsv($stream, [
                        $date->copy()->day($i)->format('m/d'),
                        '',
                        '',
                        '',
                        ''
                    ]);
                }
            }

            fclose($stream);
        });

        $filename = sprintf('%s_%s_勤怠表.csv', $user->name, $dateStr);

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
