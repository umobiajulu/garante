<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantee_id')->constrained();
            $table->foreignId('initiated_by')->constrained('users');
            $table->string('reason');
            $table->text('description');
            $table->json('evidence');
            $table->json('defense')->nullable();
            $table->text('defense_description')->nullable();
            $table->enum('status', ['pending', 'in_review', 'resolved'])->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('disputes');
    }
}; 