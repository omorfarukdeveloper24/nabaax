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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('source');
            $table->text('message');
            $table->longText('trace')->nullable();
            $table->json('context')->nullable();
            $table->string('job_class')->nullable();
            $table->json('job_params')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->enum('status', ['open', 'retrying', 'critical', 'resolved'])
                  ->default('open');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamps();

            // indexes
            $table->index('status');
            $table->index('type');
            $table->index('source');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
