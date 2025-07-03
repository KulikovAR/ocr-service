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
        Schema::table('recognition_analytics', function (Blueprint $table) {
            // Добавляем недостающие поля
            $table->decimal('max_confidence', 8, 4)->nullable()->after('status_code');
            $table->decimal('min_confidence', 8, 4)->nullable()->after('max_confidence');
            $table->boolean('recognition_success')->default(false)->after('min_confidence');
            $table->text('error_text')->nullable()->after('recognition_success');
            $table->integer('processing_time_ms')->nullable()->after('error_text');
            $table->json('metadata')->nullable()->after('processing_time_ms');
            
            // Переименовываем существующие поля для соответствия модели
            $table->renameColumn('processing_time', 'processing_time_old');
            $table->renameColumn('error_message', 'error_message_old');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recognition_analytics', function (Blueprint $table) {
            // Удаляем добавленные поля
            $table->dropColumn([
                'max_confidence',
                'min_confidence', 
                'recognition_success',
                'error_text',
                'processing_time_ms',
                'metadata'
            ]);
            
            // Возвращаем старые названия полей
            $table->renameColumn('processing_time_old', 'processing_time');
            $table->renameColumn('error_message_old', 'error_message');
        });
    }
};
