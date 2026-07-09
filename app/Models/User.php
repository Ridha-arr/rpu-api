<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $connection = 'mysql';
    protected $guarded = [];
    protected $appends = ['level'];

    public function getLevelAttribute()
    {
        if ($this->role === 'reviewer') {
            return 1;
        }
        if ($this->role === 'admin') {
            return 2;
        }
        return 3;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

    function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }


    function publikasi()
    {
        return $this->hasMany(Publikasi::class, 'user_id', 'id');
    }

    public function pendidikan()
    {
        return $this->hasMany(Pendidikan::class, 'user_id', 'id')->orderby('nama_jenjang', 'desc');
    }
}
