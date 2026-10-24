<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;    // FN008のFormRequest
use App\Http\Requests\RegisterRequest; // FN002のFormRequest
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        // 会員登録時に自作の RegisterRequest バリデーションを強制起動
        $this->app->resolving(CreateNewUser::class, function ($object) {
            app(RegisterRequest::class);
        });

        // ==========================================
        // 1. ビュー（画面）の紐付け設定
        // ==========================================

        // 会員登録画面（一般）
        Fortify::registerView(function () {
            return view('user.register');
        });

        Fortify::verifyEmailView(function () {
            // authフォルダの中の「verify-email.blade.php」を指定
            return view('auth.verify-email');
        });

        // ログイン画面（一般）
        Fortify::loginView(function () {
            return view('user.user-login');
        });


        // ==========================================
        // 💡ログイン成功後の遷移先制御 
        // ==========================================
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            function () {
                return new class implements \Laravel\Fortify\Contracts\LoginResponse {
                    public function toResponse($request)
                    {
                        $role = auth()->user()->role;
                        // 管理者(admin)は管理者の日次一覧へ
                        if ($role === 'admin') {
                            return redirect('/admin/attendance/list');
                        }
                        // 一般スタッフは一般打刻画面へ
                        return redirect('/attendance');
                    }
                };
            }
        );


        // ==========================================
        // 💡 会員登録成功後の遷移先制御 
        // ==========================================
        $this->app->singleton(
            \Laravel\Fortify\Contracts\RegisterResponse::class,
            function () {
                return new class implements \Laravel\Fortify\Contracts\RegisterResponse {
                    public function toResponse($request)
                    {
                        // 💡 ルート名エラーを防ぐため、確定パスである「/attendance」へ直接安全にリダイレクトさせます
                        return redirect('/attendance');
                    }
                };
            }
        );


        // ==========================================
        // 2. ログインバリデーション & 認証エラー文言の完全再現
        // ==========================================

        // 一般ユーザー・管理者 共通のカスタムログイン処理
        Fortify::authenticateUsing(function (Request $request) {
            // 💡 1. ログイン用のFormRequestを手動で起動（未入力エラー a, b を発動させる）
            app(LoginRequest::class);

            // 2. メールアドレスでユーザーを検索
            $user = User::where('email', $request->email)->first();

            // 💡 3. パスワード不一致、またはユーザーが存在しない場合
            if (!$user || !Hash::check($request->password, $user->password)) {
                // 評価対象のメッセージ「c. ログイン情報が登録されていません」を確定で返却
                throw ValidationException::withMessages([
                    'email' => ['ログイン情報が登録されていません'],
                ]);
            }

            // 💡 4. 一般ログイン画面(user-login)から、管理者がログインしてしまうのを防ぐガード
            if ($user->role === 'admin' && !str_contains(request()->path(), 'admin')) {
                throw ValidationException::withMessages([
                    'email' => ['ログイン情報が登録されていません'],
                ]);
            }

            return $user;
        });

        // レートリミットの設定（標準設定）
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');
            return Limit::perMinute(5)->by($email . $request->ip());
        });
    }
}
