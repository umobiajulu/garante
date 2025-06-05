<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->unique();
            $table->enum('business_type', ['sole_proprietorship', 'partnership', 'limited_company']);
            $table->string('address');
            $table->string('state');
            $table->string('city');
            $table->foreignId('owner_id')->constrained('users');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('registration_document_url');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->integer('trust_score')->default(100);
            $table->timestamps();
        });

        Schema::create('business_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['owner', 'manager', 'staff'])->default('staff');
            $table->timestamps();

            $table->unique(['business_id', 'profile_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_members');
        Schema::dropIfExists('businesses');
    }
}; 