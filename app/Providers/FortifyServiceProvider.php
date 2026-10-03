<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
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
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(function () {
            $path = request()->path();
            if (str_contains($path, 'admin')) {
                return view('admin.admin-login'); // resources/views/admin/admin-login.blade.php
            }

            return view('user.user-login'); // resources/views/user/user-login.blade.php
        });
        Fortify::registerView(function () {
            return view('user.register'); // resources/views/auth/register.blade.php
        });

        $this->app->singleton(
            \Laravel\Fortify\Http\Responses\LoginResponse::class,
            function () {
                return new class implements \Laravel\Fortify\Contracts\LoginResponse {

                    public function toResponse($request)
                    {
                        // 4. 今ログインしたユーザーの「role（権限）」が何かをチェックして変数に入れます
                        $role = auth()->user()->role;

                        // 5. もし権限が「admin（管理者）」だったら、管理者用の一覧画面へ飛ばす
                        if ($role === 'admin') {
                            return redirect('/admin/dashboard');
                        }

                        // 6. 管理者以外（generalなど）だったら、一般ユーザーの打刻画面へ飛ばす
                        return redirect('/attendance');
                    }
                };
            }
        );

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
