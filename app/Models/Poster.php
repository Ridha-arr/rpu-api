<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poster extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    function fileReview(){
        return $this->hasMany(FilePublikasi::class,'table_id','id')->where(['publikasi'=>'poster','jenis'=>'review']);
    }
    function fileTurnitin()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'poster', 'jenis' => 'turnitin']);
    }
    function fileKoresponden()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where(['publikasi' => 'poster', 'jenis' => 'koresponden']);
    }
    function verifikasi()
    {
        return $this->hasOne(Verifikasi::class, 'table_id', 'id');
    }
}
