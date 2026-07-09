<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameAdminToRoleInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE users CHANGE admin role VARCHAR(255) NOT NULL DEFAULT 'user'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE users CHANGE role admin VARCHAR(255) NOT NULL DEFAULT 'user'");
        });
    }
}
