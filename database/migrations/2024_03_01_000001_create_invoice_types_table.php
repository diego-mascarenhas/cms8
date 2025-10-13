<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('invoice_types'))
        {
            Schema::create('invoice_types', function (Blueprint $table)
            {
                $table->tinyIncrements('id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('invoice_types');
    }
};
