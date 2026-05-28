<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSintaAkredToDetailPublikasi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('detail_publikasis', function (Blueprint $table) {
            $table->integer('sinta_akred')->nullable()->after('quartile');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('detail_publikasis', function (Blueprint $table) {
            $table->dropColumn('sinta_akred');
        });
    }
}
