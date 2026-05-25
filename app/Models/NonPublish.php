<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonPublish extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    function fileReview()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'laporan', 'jenis' => 'review']);
    }
    function fileTurnitin()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'laporan', 'jenis' => 'turnitin']);
    }
    function fileKoresponden()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'laporan', 'jenis' => 'koresponden']);
    }
    function verifikasi()
    {
        return $this->hasOne(Verifikasi::class, 'table_id', 'id');
    }
}
