<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Publikasi extends Model
{
    protected $connection = 'mysql';
    public $incrementing = false;

    protected $keyType = 'string';
    use HasFactory;
    protected $guarded = [];

    public function jenisPublikasi()
    {
        return $this->belongsTo(JenisPublikasi::class, 'jenis_publikasi_id');
    }
    public function detailPublikasi()
    {
        return $this->hasOne(DetailPublikasi::class, 'publikasi_id');
    }

    public function penulis()
    {
        return $this->hasMany(Penulis::class, 'publikasi_id');
    }

    public function fileReview()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where('jenis', 'review');
    }

    public function fileTurnitin()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where('jenis', 'turnitin');
    }

    public function fileKoresponden()
    {
        return $this->hasMany(FilePublikasi::class, 'table_id', 'id')->where('jenis', 'koresponden');
    }
}
