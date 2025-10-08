<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->string('whatsapp_api_key')->nullable()->after('group_id');
        });
    }

    public function down()
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_api_key');
        });
    }
};
