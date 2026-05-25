<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublikasisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('publikasis', function (Blueprint $table) {
        $table->uuid('id')->primary();

            $table->bigInteger('id_kategori_kegiatan');

            $table->boolean('a_klaim_bkd')->default(false);
            $table->timestamp('wkt_klaim_bkd')->nullable();

            $table->text('judul');

            $table->string('quartile')->nullable();

            $table->foreignId('jenis_publikasi_id')->constrained('jenis_publikasis')->onDelete('cascade');

            $table->date('tanggal');

            $table->text('kategori_kegiatan');

            $table->string('asal_data');

            $table->json('bidang_keilmuan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('publikasis');
    }
}
