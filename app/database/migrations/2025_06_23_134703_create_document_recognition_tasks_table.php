<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_recognition_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('external_task_id')->nullable()->index();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('callback_url')->nullable();
            $table->json('metadata')->nullable();
            $table->json('result_data')->nullable();
            $table->string('document_type');
            $table->unsignedTinyInteger('attempts_count')->default(0);
            $table->timestamp('last_check_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_recognition_tasks');
    }
};
