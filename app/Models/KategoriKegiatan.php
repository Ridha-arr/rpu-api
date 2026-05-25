<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriKegiatan extends Model
{
    protected $connection = 'mysql';
    use HasFactory;
    protected $guarded = [];
    public $timestamps = false;
}
