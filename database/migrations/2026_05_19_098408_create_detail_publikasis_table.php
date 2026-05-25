<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailPublikasisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detail_publikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('publikasi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jenis_publikasi_id')->nullable()->constrained()->nullOnDelete();
            $table->string('judul')->nullable();
            $table->text('judul_artikel')->nullable();
            $table->text('judul_asli')->nullable();
            $table->string('nama_jurnal')->nullable();

            $table->date('tanggal')->nullable();
            $table->string('edisi')->nullable();

            $table->integer('volume')->default(0);
            $table->integer('nomor')->default(0);

            $table->string('halaman')->nullable();
            $table->integer('jumlah_halaman')->default(0);

            $table->string('penerbit')->nullable();

            $table->boolean('seminar')->default(false);
            $table->boolean('prosiding')->default(false);

            $table->string('nomor_paten')->nullable();
            $table->string('pemberi_paten')->nullable();

            $table->string('doi')->nullable();
            $table->string('isbn')->nullable();
            $table->string('issn')->nullable();
            $table->string('e_issn')->nullable();

            $table->text('tautan')->nullable();
            $table->text('keterangan')->nullable();

            $table->string('id_litabmas')->nullable();

            $table->integer('id_kategori_capaian_luaran')->default(0);
            $table->integer('quartile')->default(0);

            $table->string('kategori_kegiatan')->nullable();
            $table->string('jenis_publikasi')->nullable();

            $table->string('judul_litabmas')->nullable();
            $table->string('kategori_capaian_luaran')->nullable();

            $table->foreignId('kategori_kegiatan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('asal_data')->nullable();

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
        Schema::dropIfExists('detail_publikasis');
    }
}
