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
});

// 2. 管理者ユーザー用 (role: admin)
Route::middleware(['auth', App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'showDaily'])->name('attendance.daily');
    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'showAdminDetail'])->name('attendance.admin-detail');
    Route::post('/attendance/{id}', [AdminAttendanceController::class, 'updateDirectly'])->name('attendance.update-directly');
    Route::get('/staff/list', [AdminAttendanceController::class, 'showStaffList'])->name('staff.list');
    Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'showStaffMonthly'])->name('attendance.staff-monthly');
    Route::get('/attendance/staff/{id}/csv', [ExportController::class, 'exportCsv'])->name('attendance.staff.csv');
});

// 3. 一般・管理者 共通パスルート (ミドルウェアで上書きされないように外に定義)
Route::middleware(['auth'])->group(function () {
    // パスを共通化し、コントローラのindexメソッド内でrole判定して画面を出し分けます
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'index'])->name('correction.list');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendanceCorrectionController::class, 'showApproveDetail'])->name('correction.approve-detail');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendanceCorrectionController::class, 'approve'])->name('correction.approve');
    Route::get('/attendance/{id}', function ($id) {
        if (auth()->user()->role === 'admin') {
            return app(AdminAttendanceController::class)->showAdminDetail($id);
        }
        // 万が一一般ユーザーがこのパスを踏んだ場合は、通常の一覧へ安全に送り返します
        return redirect('/attendance/list');
    })->name('attendance.shortcut');
});

// 管理者ログインバイパス (POST)
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
