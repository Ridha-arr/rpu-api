<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToProsidingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('prosidings', function (Blueprint $table) {
            $table->string('tema',500);
            $table->string('editor');
            $table->string('nama_pertemuan',500);
            $table->string('tempat_pertemuan',500);
            $table->string('halaman');
            $table->string('tanggal');
            $table->string('penerbit');
            $table->string('tempat_terbit');
            $table->string('issn');
            $table->string('email');
            $table->string('file')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prosidings', function (Blueprint $table) {
            //
        });
    }
}
