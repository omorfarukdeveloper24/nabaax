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
        Schema::create('application_dates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('school_id');
            $table->string('teacher_id')->nullable();
            $table->string('student_id')->nullable();
            $table->string('dates');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_dates');
    }
};
