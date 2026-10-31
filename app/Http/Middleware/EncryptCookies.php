<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * クッキーを暗号化する
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
