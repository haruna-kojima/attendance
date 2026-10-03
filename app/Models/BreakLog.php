<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Break extends Model
{
    use HasFactory;

    protected $table = 'breaks';

    protected $fillable = [
        'attendance_id',
        'break_in_at',
        'break_out_at',
    ];

    // どの勤怠データに紐づく休憩か (多対1)
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
