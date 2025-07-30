<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_recognition_tasks', function (Blueprint $table) {
            $table->dropColumn('callback_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_recognition_tasks', function (Blueprint $table) {
            $table->string('callback_url')->nullable();
        });
    }
};
