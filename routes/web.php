<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\ExportController;
use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect('/attendance');
});

// 未ログイン（ゲスト）専用ルート
Route::middleware(['guest'])->group(function () {
    Route::get('/admin/login', function () {
        return view('admin.admin-login');
    })->name('admin.login.form');
});

// 1. 一般ユーザー用(ログイン・メール認証済み)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'showStamp'])->name('attendance.stamp');
    Route::post('/attendance', [AttendanceController::class, 'stamp'])->name('attendance.stamp.submit');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'showDetail'])->name('attendance.detail');
    Route::post('/attendance/detail/{id}', [AttendanceCorrectionController::class, 'applyCorrection'])->name('attendance.correction.submit');
    Route::get('/attendance/report', [AttendanceController::class, 'showReport'])->name('attendance.report');
});

// 2. 管理者ユーザー用 (role: admin)
Route::middleware(['auth', App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'showDaily'])->name('attendance.daily');
    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'showAdminDetail'])->name('attendance.admin-detail');
    Route::post('/attendance/{id}', [AdminAttendanceController::class, 'updateDirectly'])->name('attendance.update-directly');
    Route::get('/staff/list', [AdminAttendanceController::class, 'showStaffList'])->name('staff.list');
    Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'showStaffMonthly'])->name('attendance.staff-monthly');
});

// 3. 一般・管理者 共通パスルート (ミドルウェアで上書きされないように外に定義)
Route::middleware(['auth'])->group(function () {
    Route::post('/export', [ExportController::class, 'exportCsv'])->name('admin.attendance.staff.csv');
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'index'])->name('correction.list');

    Route::get('/application/{id}', function ($id) {
        return app(AttendanceController::class)->showDetail($id);
    })->name('attendance.application-detail.legacy');

    Route::get('/stamp_correction_request/approve/{id}', function ($id) {
        return app(AdminAttendanceController::class)->showApprovalDetail($id);
    })->name('correction.approve-detail');

    Route::post('/stamp_correction_request/approve/{id}', [AttendanceCorrectionController::class, 'approve'])->name('correction.approve');

    Route::get('/attendance/{id}', function ($id) {
        if (!is_numeric($id)) {
            return redirect('/attendance');
        }

        if (auth()->user()->role === 'admin') {
            return app(AdminAttendanceController::class)->showAdminDetail($id);
        }
        return app(AttendanceController::class)->showDetail($id);
    })->name('attendance.shortcut');

    Route::post('/attendance/{id}', function (Request $request, $id) {
        if (auth()->user()->role === 'admin') {
            return app(AdminAttendanceController::class)->updateDirectly($request, $id);
        }
        return app(AttendanceCorrectionController::class)->applyCorrection($request, $id);
    })->name('attendance.correction.submit');
});

Route::post('/admin/login', function (Request $request) {
    $request->validate(
        ['email' => 'required|email', 'password' => 'required'],
        ['email.required' => 'メールアドレスを入力してください', 'password.required' => 'パスワードを入力してください']
    );

    $user = \App\Models\User::where('email', $request->email)->where('role', 'admin')->first();
    if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return back()->withErrors(['email' => 'ログイン情報が登録されていません']);
    }

    auth()->login($user);
    return redirect('/admin/attendance/list');
});

Route::post('/admin/logout', function (\Illuminate\Http\Request $request) {
    // Fortifyの標準ログアウト処理をプログラムから直接実行します
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin/login'); // 管理者ログイン画面へリダイレクト
});
