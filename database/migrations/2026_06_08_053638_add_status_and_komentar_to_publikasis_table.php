<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusAndKomentarToPublikasisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('publikasis', function (Blueprint $table) {
            $table->integer('status')->default(0)->after('asal_data');
            $table->text('komentar')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('publikasis', function (Blueprint $table) {
            $table->dropColumn(['status', 'komentar']);
        });
    }
}
