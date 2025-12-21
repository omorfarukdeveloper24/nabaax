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
        Schema::create('admin_pay_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');        
            $table->string('payment_name');                 
            $table->string('tnx')->unique();                
            $table->decimal('amount', 15, 2);             
            $table->decimal('balance', 15, 2);               
            $table->string('method');                        
            $table->enum('type', ['debit', 'credit']);       
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_pay_histories');
    }
};
