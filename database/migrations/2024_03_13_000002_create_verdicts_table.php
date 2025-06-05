<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verdicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained();
            $table->foreignId('arbitrator_id')->constrained('users');
            $table->enum('decision', ['refund', 'partial_refund', 'no_refund']);
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('notes');
            $table->json('evidence_reviewed');
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('verdicts');
    }
}; 