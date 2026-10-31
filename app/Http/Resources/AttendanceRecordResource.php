<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $breakMinutes = method_exists($this->resource, 'calculateTotalBreakMinutes') ? $this->calculateTotalBreakMinutes() : 0;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user ? $this->user->name : null,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'date' => $this->date,
            'clock_in' => $this->clock_in_at ? Carbon::parse($this->clock_in_at)->format('H:i:s') : null,
            'clock_out' => $this->clock_out_at ? Carbon::parse($this->clock_out_at)->format('H:i:s') : null,
            'total_time' => method_exists($this->resource, 'calculateActualWorkingTime') ? $this->calculateActualWorkingTime() : null,
            'total_break_time' => $breakMinutes > 0 ? sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60) : '00:00',
            'breaks' => $this->whenLoaded('breaksLogs', function () {
                return $this->breaksLogs->map(function ($b) {
                    return [
                        'id' => $b->id,
                        'break_in' => $b->break_in_at ? Carbon::parse($b->break_in_at)->format('H:i:s') : null,
                        'break_out' => $b->break_out_at ? Carbon::parse($b->break_out_at)->format('H:i:s') : null,
                    ];
                });
            }),
            'applications' => $this->whenLoaded('attendanceCorrections', function () {
                return $this->attendanceCorrections;
            }),
            'comment' => $this->remarks,
        ];
    }
}
