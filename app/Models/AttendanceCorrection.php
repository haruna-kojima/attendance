<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'new_clock_in',
        'new_clock_out',
        'new_breaks',
        'approval_status',
        'comment',
    ];

    // 勤怠実績とのリレーション
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    // 申請者（ユーザー）とのリレーション
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 休憩の修正申請（子データ）とのリレーション
    public function breakCorrections()
    {
        return $this->hasMany(BreakCorrection::class, 'attendance_correction_id');
    }
}
