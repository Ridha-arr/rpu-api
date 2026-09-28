<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToPenulisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penulis', function (Blueprint $table) {
            $table->integer('urutan')->nullable()->after('id_sdm');
            $table->string('peran')->nullable()->after('urutan');
            $table->boolean('corresponding_author')->default(false)->after('peran');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('penulis', function (Blueprint $table) {
            $table->dropColumn(['urutan', 'peran', 'corresponding_author']);
        });
    }
}
