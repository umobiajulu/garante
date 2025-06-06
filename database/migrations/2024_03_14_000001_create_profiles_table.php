<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('nin')->nullable();
            $table->string('bvn')->nullable();
            $table->boolean('nin_verified')->default(false);
            $table->boolean('bvn_verified')->default(false);
            $table->string('nin_phone')->nullable();
            $table->string('bvn_phone')->nullable();
            $table->date('nin_dob')->nullable();
            $table->date('bvn_dob')->nullable();
            $table->string('address');
            $table->string('state');
            $table->string('city');
            $table->string('profession');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('id_document_url')->nullable();
            $table->string('address_document_url')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('profiles');
    }
}; 