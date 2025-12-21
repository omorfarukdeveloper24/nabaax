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
        Schema::create('customer_pay_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id'); 
            $table->string('payment_name');              
            $table->string('tnx')->unique();         
            $table->decimal('amount', 15, 2);        
            $table->decimal('balance', 15, 2);       
            $table->string('method');      
            $table->enum('type', ['debit', 'credit']);          
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_pay_histories');
    }
};
