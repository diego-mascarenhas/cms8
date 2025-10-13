<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnterpriseStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('enterprise_statuses', function (Blueprint $table)
        {
            $table->tinyIncrements('id');
            $table->string('name');
            $table->unsignedTinyInteger('enterprise_type_id');
            $table->string('label_class')->default('bg-label-secondary');

            $table->foreign('enterprise_type_id')
                ->references('id')
                ->on('enterprise_types')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('enterprise_statuses');
    }
}
