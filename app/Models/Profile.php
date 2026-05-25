<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    function prodi()
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'id');
    }
    function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id', 'id');
    }
}
