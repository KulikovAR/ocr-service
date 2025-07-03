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
        Schema::create('recognition_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('document_id');
            $table->string('document_type');
            $table->integer('status_code');
            $table->float('processing_time')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'created_at']);
            $table->index(['status_code', 'created_at']);

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recognition_analytics');
    }
};
