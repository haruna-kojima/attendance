<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 
     * 一括画面登録を許可するデータベースの項目
　   */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'email_verified_at',
    ];

    /**
     * 変換時に機密項目を画面に漏洩させない
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * datetimeで日付オブジェクト（carbon）に変換
     * hashedでパスワードを暗号化
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function getAdminStatusAttribute(): bool
    {
        /**
         * roleがadminの場合、trueを返す
         */
        return $this->role === 'admin';
    }
}
