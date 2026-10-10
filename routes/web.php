<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\ExportController;
use App\Http\Middleware\AdminMiddleware;

Route::get('/', function () {
    return redirect('/attendance');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'showStamp'])->name('attendance.stamp');
    Route::post('/attendance', [AttendanceController::class, 'stamp'])->name('attendance.stamp.submit');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'showDetail'])->name('attendance.detail');
    Route::post('/attendance/detail/{id}', [AttendanceCorrectionController::class, 'applyCorrection'])->name('attendance.correction.submit');
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'showUserApplications'])->name('correction.user-list');
});

Route::middleware(['auth', AdminMiddleware::class])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'showDaily'])->name('attendance.daily');
    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'showAdminDetail'])->name('attendance.admin-detail');
    Route::post('/attendance/{id}', [AdminAttendanceController::class, 'updateDirectly'])->name('attendance.update-directly');
    Route::get('/staff/list', [AdminAttendanceController::class, 'showStaffList'])->name('staff.list');
    Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'showStaffMonthly'])->name('attendance.staff-monthly');
});

Route::middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'showAdminApplications'])->name('correction.admin-list');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendanceCorrectionController::class, 'showApproveDetail'])->name('correction.approve-detail');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendanceCorrectionController::class, 'approve'])->name('correction.approve');
    Route::post('/export', [ExportController::class, 'exportCsv'])->name('export.csv');
});
