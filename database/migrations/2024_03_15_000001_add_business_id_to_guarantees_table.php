<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('guarantees', function (Blueprint $table) {
            $table->foreignId('business_id')->after('seller_id')->constrained();
        });
    }

    public function down()
    {
        Schema::table('guarantees', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropColumn('business_id');
        });
    }
}; 