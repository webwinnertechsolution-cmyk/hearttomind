<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('display_order')->default(0)->after('status');
        });
        Schema::table('albams', function (Blueprint $table) {
            $table->integer('display_order')->default(0)->after('status');
        });
        Schema::table('play_lists', function (Blueprint $table) {
            $table->integer('display_order')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
        Schema::table('albams', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
        Schema::table('play_lists', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
