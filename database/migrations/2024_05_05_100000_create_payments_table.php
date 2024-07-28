<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enterprise_id')->nullable();
            $table->enum('transaction_type', ['I', 'E'])->default('I'); // 'I' for income, 'E' for expense
            $table->date('date');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedSmallInteger('account_id');
            $table->unsignedTinyInteger('type_id');
            $table->decimal('amount', 10, 2);
            $table->text('remarks')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->foreign('enterprise_id')->references('id')->on('enterprises')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
