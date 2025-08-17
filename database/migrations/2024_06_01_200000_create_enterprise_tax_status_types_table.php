<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('enterprise_tax_status_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('enterprise_tax_status_types');
    }
};
