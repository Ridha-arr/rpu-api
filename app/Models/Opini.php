<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opini extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    function fileReview()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'opini', 'jenis' => 'review']);
    }
    function fileTurnitin()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'opini', 'jenis' => 'turnitin']);
    }
    function fileKoresponden()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'opini', 'jenis' => 'koresponden']);
    }
    function verifikasi()
    {
        return $this->hasOne(Verifikasi::class, 'table_id', 'id');
    }
}
