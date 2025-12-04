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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->longText('content')->nullable();
            $table->enum('type', ['text', 'image', 'video', 'audio', 'link', 'poll'])->default('text');
            $table->enum('visibility', ['public', 'followers', 'private', 'group'])->default('public');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
