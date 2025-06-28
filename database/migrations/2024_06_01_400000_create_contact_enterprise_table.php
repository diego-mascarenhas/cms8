<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactEnterpriseTable extends Migration
{
    public function up()
    {
        Schema::create('contact_enterprise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('enterprise_id')->constrained()->onDelete('cascade');
            $table->string('position')->nullable();
            $table->foreignId('superior_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_enterprise');
    }
}
