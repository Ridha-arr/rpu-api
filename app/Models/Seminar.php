<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seminar extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'tanggal'  => 'date:Y-m-d',
    ];
    function fileReview(){
        return $this->hasMany(FilePublikasi::class,'table_id','id')->where(['publikasi'=>'seminar','jenis'=>'review']);
    }
    function fileTurnitin(){
        return $this->hasMany(FilePublikasi::class,'table_id','id')->where(['publikasi'=>'seminar','jenis'=>'turnitin']);
    }
    function fileKoresponden(){
        return $this->hasMany(FilePublikasi::class,'table_id','id')->where(['publikasi'=>'seminar','jenis'=> 'koresponden']);
    }
    function verifikasi(){
        return $this->hasOne(Verifikasi::class,'table_id','id');
    }
}
