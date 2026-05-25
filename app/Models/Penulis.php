<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penulis extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    protected $table = 'penulis';
    public $incrementing = false;
    protected $guarded = [];
    protected $keyType = 'string';
    public function publikasi()
    {
        return $this->belongsTo(Publikasi::class, 'publikasi_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'id_sdm', 'id_sdm');
    }
}
