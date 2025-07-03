<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Document Recognition API",
 *     description="API для распознавания документов через внешний сервис bescan.ru",
 *     @OA\Contact(
 *         email="support@cargis.pro",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8080/api/v1",
 *     description="Local Development Server"
 * )
 *
 * @OA\Server(
 *     url="https://api.cargis.pro/api/v1",
 *     description="Production Server"
 * )
 *
 * @OA\Tag(
 *     name="Document Recognition",
 *     description="Операции с распознаванием документов"
 * )
 *
 * @OA\Tag(
 *     name="Analytics",
 *     description="Операции с аналитикой распознавания документов"
 * )
 *
 * @OA\Tag(
 *     name="Health",
 *     description="Проверка состояния сервиса"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class SwaggerController extends Controller
{
    // Этот класс используется только для Swagger документации
} 