<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('verdicts', function (Blueprint $table) {
            $table->foreignId('guarantee_id')->after('dispute_id')->constrained();
        });
    }

    public function down()
    {
        Schema::table('verdicts', function (Blueprint $table) {
            $table->dropForeign(['guarantee_id']);
            $table->dropColumn('guarantee_id');
        });
    }
}; 