<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('enterprise_statuses')
            ->where('enterprise_type_id', 1)
            ->where('id', 1)
            ->update(['name' => 'Inactivo']);

        DB::table('enterprise_statuses')
            ->where('enterprise_type_id', 1)
            ->where('id', 2)
            ->update(['name' => 'Activo']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('enterprise_statuses')
            ->where('enterprise_type_id', 1)
            ->where('id', 1)
            ->update(['name' => 'Inactiva']);

        DB::table('enterprise_statuses')
            ->where('enterprise_type_id', 1)
            ->where('id', 2)
            ->update(['name' => 'Activa']);
    }
};
