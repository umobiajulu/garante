<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verdict_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'processed', 'completed'])->default('pending');
            $table->text('proof_of_payment')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('restitutions');
    }
}; 