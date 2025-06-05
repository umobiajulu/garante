<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('guarantees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('buyer_id')->constrained('users');
            $table->text('service_description');
            $table->decimal('price', 12, 2);
            $table->json('terms');
            $table->enum('status', [
                'draft',
                'pending',
                'accepted',
                'in_progress',
                'completed',
                'cancelled',
                'disputed'
            ])->default('draft');
            $table->integer('progress')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('guarantees');
    }
}; 