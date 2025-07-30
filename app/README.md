# Микросервис распознавания документов

Микросервис для распознавания документов с использованием внешнего OCR-сервиса beorg.ru. Поддерживает распознавание паспорта РФ, СНИЛС, водительского удостоверения и свидетельства о регистрации транспортного средства.

## Технологический стек

- **PHP 8.3+**
- **Laravel 12**
- **MySQL 8.0**
- **Nginx**
- **Docker & Docker Compose**
- **Redis** (для очередей)
- **Kafka** (для отправки результатов распознавания)
- **Graylog** (для логирования)

## Быстрый старт

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd osr-service
```

### 2. Настройка окружения

```bash
cp .env.example .env
```

Отредактируйте `.env` файл:

```env
# Основные настройки

# Внешний API (bescan)
BESCAN_BASE_URL=https://api.bescan.ru
BESCAN_TOKEN=your_token
BESCAN_MACHINE_UID=your_machine_uid
BESCAN_PROJECT_ID=DEMO

# Graylog
GRAYLOG_HOST=graylog
GRAYLOG_PORT=12201
GRAYLOG_SOURCE=ocr-service

# Kafka
KAFKA_ENABLED=true
KAFKA_BROKERS=localhost:9092
KAFKA_CLIENT_ID=osr-service
KAFKA_TOPIC=document-recognition-results

# Логирование
LOG_CHANNEL=production
```

### 3. Запуск сервисов

```bash
docker-compose up -d
```

### 4. Установка зависимостей и миграции

```bash
docker-compose exec app composer install --no-dev --optimize-autoloader

docker-compose exec app php artisan key:generate

# Запуск миграций
docker-compose exec app php artisan migrate

# Очистка кэша
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
```

### 5. Проверка работоспособности

```bash
# Health check
curl http://localhost/health

# Должен вернуть:
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00.000000Z",
  "checks": {
    "database": {"status": "healthy", "message": "Database connection successful"},
    "external_api": {"status": "healthy", "message": "External API is accessible"},
    "queue": {"status": "healthy", "message": "Queue system is configured"}
  },
  "error_flag": false
}
```

## API Спецификация

### POST /process_document

Отправляет документ на распознавание.

**Входные данные:**
```json
{
  "id": "string",           // ID документа
  "doc_type": "string",     // Тип документа: PASSPORT, PASSPORT_REG, SNILS, DLIC, STS
  "files": ["string"],      // Массив base64 файлов
  "callback": "string"      // URL для callback (опционально)
}
```

**Примечание:** Если `callback` не указан, результат распознавания отправляется в Kafka топик `document-recognition-results`.
```

**Выходные данные:**
```json
{
  "document_id": "string"   // Идентификатор задачи
}
```

**Пример запроса:**
```bash
curl -X POST http://localhost/process_document \
  -H "Content-Type: application/json" \
  -d '{
    "id": "doc_123",
    "doc_type": "PASSPORT",
    "files": ["base64_encoded_file_content"],
    "callback": "https://your-service.com/webhook"
  }'
```

### GET /status/{document_id}

Получает статус обработки документа.

**Выходные данные:**
```json
{
  "document_id": "string",
  "status": "string",       // pending, processing, completed, failed
  "document_type": "string",
  "data": {},              // Результат распознавания (если completed)
  "confidences": {},       // Точность распознавания полей
  "verifications": {}      // Результаты проверок
}
```

**Пример запроса:**
```bash
curl http://localhost/status/doc_123
```

### GET /analytics

Административный раздел для мониторинга (требует OAuth 2.0 авторизации).

## Поддерживаемые форматы документов

### Входные данные
- **Форматы:** JPEG, TIFF, PNG, AVIF, WEBP, PDF
- **Размер:** 10 Кб - 5 Мб на файл
- **Количество страниц:** до 20
- **Размеры изображения:** 1000x1000 - 5000x5000 пикселей

### Типы документов

#### Паспорт РФ
```json
{
  "issuedBy": "string",
  "issueDate": "string",
  "issueId": "string",
  "series": "string",
  "number": "string",
  "gender": "string",
  "lastName": "string",
  "firstName": "string",
  "middleName": "string",
  "birthDate": "string",
  "birthPlace": "string",
  "hasPhoto": "boolean",
  "hasOwnerSignature": "boolean",
  "MRZ1": "string",
  "MRZ2": "string"
}
```

#### СНИЛС
```json
{
  "number": "string",
  "gender": "string",
  "lastname": "string",
  "firstname": "string",
  "middlename": "string",
  "birthDate": "string",
  "birthPlace": "string",
  "registrationDate": "string"
}
```

#### Водительское удостоверение
```json
{
  "gender": "string",
  "lastName": "string",
  "firstName": "string",
  "middleName": "string",
  "birthDate": "string",
  "birthPlace": "string",
  "issueDate": "string",
  "endDate": "string",
  "issuedBy": "string",
  "placeOfResidence": "string",
  "series": "string",
  "number": "string",
  "categories": "string",
  "specialMarks": "string"
}
```

#### СРТС
```json
{
  "reg_number": "string",
  "vin": "string",
  "brandRus": "string",
  "modelRus": "string",
  "brandEng": "string",
  "modelEng": "string",
  "vehicleType": "string",
  "vehicleCategory": "string",
  "releaseYear": "string",
  "engineModel": "string",
  "engineNumber": "string",
  "vehicleChassis": "string",
  "vehicleBody": "string",
  "color": "string",
  "enginePower": "string",
  "engineVolume": "string",
  "ecologicClass": "string",
  "passportSeries": "string",
  "passportNumber": "string",
  "maxMass": "string",
  "mass": "string",
  "lastnameRu": "string",
  "firstnameRu": "string",
  "middlenameRu": "string",
  "federationSubject": "string",
  "area": "string",
  "locality": "string",
  "street": "string",
  "houseNumber": "string",
  "buildingNumber": "string",
  "apartmentNumber": "string",
  "specialMarks": "string",
  "departmentCode": "string",
  "date": "string"
}
```

## Мониторинг и логирование

### Graylog интеграция

Все запросы и ответы логируются в Graylog с параметром `source`:
- Продакшн: `ocr-service`
- Тестовая среда: `test-ocr-service`

### Health Check

```bash
curl http://localhost/health
```

Флаг ошибки устанавливается при:
- Статусах 401, 402 от внешнего API
- 5+ последовательных ошибок
- Проблемах с БД или внутренними ошибками

### Аналитика

Данные мониторинга сохраняются на 3 месяца и включают:
- Идентификатор запроса
- Время запроса
- Статус-код ответа
- Тип документа
- Точность распознавания
- Флаг успешности
- Текст ошибки

## Команды для эксплуатации

### Очистка старых данных
```bash
# Просмотр что будет удалено
docker-compose exec app php artisan analytics:cleanup --dry-run

# Удаление записей старше 3 месяцев
docker-compose exec app php artisan analytics:cleanup
```
