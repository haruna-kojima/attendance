<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

// 一般ユーザー用ルート（認証済み＆メール認証済み）
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/attendance/stamp', [AttendanceController::class, 'showStamp'])->name('attendance.stamp');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::post('/attendance/break-in', [AttendanceController::class, 'breakIn'])->name('attendance.break-in');
    Route::post('/attendance/break-out', [AttendanceController::class, 'breakOut'])->name('attendance.break-out');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
});
