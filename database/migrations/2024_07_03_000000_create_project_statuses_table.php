<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProjectStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_statuses', function (Blueprint $table)
        {
            $table->tinyIncrements('id');
            $table->string('name');
            $table->string('label_class');
        });

        DB::table('project_statuses')->insert([
            'id' => 14,
            'name' => 'BONIFIED',
            'label_class' => 'bg-label-info',
        ]);

        if (DB::getDriverName() === 'pgsql')
        {
            DB::statement("SELECT setval(pg_get_serial_sequence('project_statuses', 'id'), (SELECT MAX(id) FROM project_statuses))");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('project_statuses');
    }
}
