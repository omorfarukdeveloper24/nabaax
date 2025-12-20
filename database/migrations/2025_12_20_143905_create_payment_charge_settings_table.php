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
        Schema::create('payment_charge_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('min_deposit')->default(0);
            $table->integer('min_withdraw')->default(0);
            $table->integer('transfer_limit')->default(0);
            $table->integer('first_gen_bonus')->default(0);
            $table->integer('multi_gen_bonus')->default(0); 
            $table->integer('partner_own_bonus')->default(0);
            $table->integer('partner_min_balance')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_charge_settings');
    }
};
