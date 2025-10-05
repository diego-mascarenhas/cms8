<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('payment_types'))
        {
            Schema::create('payment_types', function (Blueprint $table)
            {
                $table->tinyIncrements('id');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Add FK in core table only if it exists
        if (Schema::hasTable('enterprises') && Schema::hasColumn('enterprises', 'payment_type_id'))
        {
            Schema::table('enterprises', function (Blueprint $table)
            {
                try {
                    $table->foreign('payment_type_id')
                        ->references('id')
                        ->on('payment_types')
                        ->onDelete('cascade');
                } catch (\Throwable $e) {
                    // ignore if FK already exists
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('enterprises'))
        {
            Schema::table('enterprises', function (Blueprint $table)
            {
                try {
                    $table->dropForeign('enterprises_payment_type_id_foreign');
                } catch (\Throwable $e) {
                    // ignore
                }
            });
        }

        Schema::dropIfExists('payment_types');
    }
};


