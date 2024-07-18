<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('billing_id');
            $table->unsignedTinyInteger('type_id');
            $table->enum('operation', ['Buy', 'Sell'])->default('Sell');
            $table->string('number')->default(1);
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->decimal('gross_amount', 10, 2)->unsigned();
            $table->decimal('discount', 10, 2)->unsigned()->nullable();
            $table->decimal('total_amount', 10, 2)->unsigned();
            $table->decimal('balance', 10, 2)->unsigned();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('type_id')->references('id')->on('invoice_types')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
