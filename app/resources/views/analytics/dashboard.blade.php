@extends('layouts.app')

@section('title', 'Аналитика распознавания документов')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Аналитика распознавания документов</h1>
        <p class="text-gray-600">Статистика и метрики работы системы распознавания</p>
    </div>

    <!-- Статистика за последние периоды -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Последние 24 часа -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Последние 24 часа</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Всего запросов:</span>
                    <span class="font-semibold">{{ $stats['last_24_hours']['total_requests'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Успешных:</span>
                    <span class="font-semibold text-green-600">{{ $stats['last_24_hours']['successful_requests'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Среднее время:</span>
                    <span class="font-semibold">{{ number_format(($stats['last_24_hours']['avg_processing_time'] ?? 0) / 1000, 2) }} сек</span>
                </div>
            </div>
        </div>

        <!-- Последние 7 дней -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Последние 7 дней</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Всего запросов:</span>
                    <span class="font-semibold">{{ $stats['last_7_days']['total_requests'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Успешных:</span>
                    <span class="font-semibold text-green-600">{{ $stats['last_7_days']['successful_requests'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Среднее время:</span>
                    <span class="font-semibold">{{ number_format(($stats['last_7_days']['avg_processing_time'] ?? 0) / 1000, 2) }} сек</span>
                </div>
            </div>
        </div>

        <!-- Последние 30 дней -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Последние 30 дней</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Всего запросов:</span>
                    <span class="font-semibold">{{ $stats['last_30_days']['total_requests'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Успешных:</span>
                    <span class="font-semibold text-green-600">{{ $stats['last_30_days']['successful_requests'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Среднее время:</span>
                    <span class="font-semibold">{{ number_format(($stats['last_30_days']['avg_processing_time'] ?? 0) / 1000, 2) }} сек</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Распределение по типам документов -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Распределение по типам документов</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тип документа</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Всего запросов</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Успешных</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Процент успеха</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($stats['document_type_distribution'] ?? [] as $type)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $type->document_type }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $type->count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $type->successful_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($type->count > 0)
                                {{ number_format(($type->successful_count / $type->count) * 100, 1) }}%
                            @else
                                0%
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                            Нет данных для отображения
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Топ ошибок -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Топ ошибок за последние 30 дней</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ошибка</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Количество</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($stats['error_summary'] ?? [] as $error)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="max-w-xs truncate" title="{{ $error->error_text }}">
                                {{ $error->error_text }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $error->count }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-center text-sm text-gray-500">
                            Ошибок не обнаружено
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection 