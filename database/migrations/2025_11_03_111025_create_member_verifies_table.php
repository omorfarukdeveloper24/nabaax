<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_verifies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->enum('type', ['nid', 'birth', 'passport', 'driving']);
            $table->string('nid_number')->nullable();
            $table->string('birth_number')->nullable();
            $table->string('nid_front_image')->nullable();
            $table->string('nid_back_image')->nullable();
            $table->string('birth_image')->nullable();
            $table->string('passport_image')->nullable();
            $table->string('driving_front_image')->nullable();
            $table->string('driving_back_image')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_verifies');
    }
};
