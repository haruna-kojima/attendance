<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Http\Requests\RegisterRequest;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // 💡 実際のバリデーションは、上のServiceProvider側で「RegisterRequest」が
        // 先行して実行・エラー返却を行うため、ここでは安全にインサート処理だけを記述します。

        app(RegisterRequest::class)->validateResolved();

        return User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make($input['password']),
            'role'     => 'general',
        ]);
    }
}
