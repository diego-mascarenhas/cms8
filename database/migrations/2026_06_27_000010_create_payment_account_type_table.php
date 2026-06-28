<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_account_payment_type', function (Blueprint $table)
        {
            $table->id();
            $table->unsignedBigInteger('payment_account_id');
            $table->unsignedTinyInteger('payment_type_id');
            $table->timestamps();

            $table->unique(['payment_account_id', 'payment_type_id'], 'payment_account_payment_type_unique');
            $table->foreign('payment_account_id')
                ->references('id')
                ->on('payment_accounts')
                ->cascadeOnDelete();
            $table->foreign('payment_type_id')
                ->references('id')
                ->on('payment_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_payment_type');
    }
};
