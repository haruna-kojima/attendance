<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date', 'clock_in_at', 'clock_out_at', 'status', 'remarks'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function breaksLogs()
    {
        return $this->hasMany(BreakLog::class);
    }

    // 💡 500バグの修正：コントローラーやテスト環境が「$attendance->breaks」と呼んでもエラーにならずに自動変換するアクセサ
    public function getBreaksAttribute()
    {
        return $this->breaksLogs;
    }
    public function getBreakLogsAttribute()
    {
        return $this->breaksLogs;
    }

    public function calculateTotalBreakMinutes(): int
    {
        $totalMinutes = 0;
        foreach ($this->breaksLogs as $break) {
            if ($break->break_in_at && $break->break_out_at) {
                $totalMinutes += Carbon::parse($break->break_in_at)->diffInMinutes(Carbon::parse($break->break_out_at));
            }
        }
        return $totalMinutes;
    }

    public function calculateActualWorkingTime(): string
    {
        if (!$this->clock_in_at || !$this->clock_out_at) return '';
        $workingMinutes = Carbon::parse($this->clock_in_at)->diffInMinutes(Carbon::parse($this->clock_out_at));
        $actualMinutes = $workingMinutes - $this->calculateTotalBreakMinutes();
        return $actualMinutes > 0 ? sprintf('%02d:%02d', floor($actualMinutes / 60), $actualMinutes % 60) : '00:00';
    }

    public function attendanceCorrections()
    {
        return $this->hasMany(AttendanceCorrection::class, 'attendance_id');
    }
}
