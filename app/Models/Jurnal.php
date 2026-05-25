<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    function fileReview()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'jurnal', 'jenis' => 'review']);
    }
    function fileTurnitin()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'jurnal', 'jenis' => 'turnitin']);
    }
    function fileKoresponden()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'jurnal', 'jenis' => 'koresponden']);
    }
    function user(){
        return $this->belongsTo(User::class);
    }
    function verifikasi()
    {
        return $this->hasOne(Verifikasi::class, 'table_id', 'id');
    }
}
