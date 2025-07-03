<?php

namespace App\Services;

class DocumentDataProcessor
{
    public function processRecognitionData(array $data, string $documentType): array
    {
        $processedData = [
            'document_type' => $documentType,
            'recognition_status' => 'completed',
            'extracted_data' => [],
            'confidence_score' => 0.0,
            'processing_time' => $data['processing_time'] ?? 0,
        ];

        // Обрабатываем новый формат данных от API
        if (isset($data['documents']) && is_array($data['documents']) && !empty($data['documents'])) {
            $document = $data['documents'][0]; // Берем первый документ
            $documentData = $document['data'] ?? [];
            $metadata = $document['metadata'] ?? [];
            
            $processedData['extracted_data'] = $this->extractDocumentData($documentData, $documentType);
            
            // Вычисляем средний confidence score из metadata
            if (isset($metadata['confidences']) && is_array($metadata['confidences'])) {
                $confidences = array_values($metadata['confidences']);
                $processedData['confidence_score'] = !empty($confidences) ? array_sum($confidences) / count($confidences) : 0.0;
            }
            
            // Добавляем дополнительную информацию
            $processedData['metadata'] = [
                'verifications' => $metadata['verifications'] ?? [],
                'external_integrations' => $metadata['external_integrations'] ?? [],
                'broken_reasons' => $document['broken_reasons'] ?? [],
                'broken_reasons_ru' => $document['broken_reasons_ru'] ?? [],
            ];
        }

        // Проверяем на ошибки
        if (isset($data['documents']) && is_array($data['documents'])) {
            foreach ($data['documents'] as $document) {
                if (!empty($document['broken_reasons']) || !empty($document['broken_reasons_ru'])) {
                    $processedData['recognition_status'] = 'completed_with_errors';
                    $processedData['errors'] = array_merge(
                        $document['broken_reasons'] ?? [],
                        $document['broken_reasons_ru'] ?? []
                    );
                    break;
                }
            }
        }

        return $processedData;
    }

    private function extractDocumentData(array $result, string $documentType): array
    {
        return match ($documentType) {
            'PASSPORT', 'PASSPORT_REG' => $this->extractPassportData($result),
            'DLIC', 'DRIVER_LICENSE' => $this->extractDriverLicenseData($result),
            'SNILS' => $this->extractSnilsData($result),
            'STS', 'VEHICLE_REGISTRATION' => $this->extractVehicleRegistrationData($result),
            default => $result,
        };
    }

    private function extractPassportData(array $result): array
    {
        return [
            'series' => $result['Series'] ?? null,
            'number' => $result['Number'] ?? null,
            'issued_by' => $result['IssuedBy'] ?? null,
            'issue_date' => $result['IssueDate'] ?? null,
            'department_code' => $result['IssueId'] ?? null,
            'last_name' => $result['LastName'] ?? null,
            'first_name' => $result['FirstName'] ?? null,
            'middle_name' => $result['MiddleName'] ?? null,
            'birth_date' => $result['BirthDate'] ?? null,
            'birth_place' => $result['BirthPlace'] ?? null,
            'gender' => $result['Gender'] ?? null,
            'registration_address' => $result['Address'] ?? null,
            'mrz1' => $result['MRZ1'] ?? null,
            'mrz2' => $result['MRZ2'] ?? null,
            'has_photo' => $result['HasPhoto'] ?? null,
            'has_owner_signature' => $result['HasOwnerSignature'] ?? null,
        ];
    }

    private function extractDriverLicenseData(array $result): array
    {
        return [
            'series' => $result['Series'] ?? null,
            'number' => $result['Number'] ?? null,
            'issued_by' => $result['IssuedBy'] ?? null,
            'issue_date' => $result['IssueDate'] ?? null,
            'expiry_date' => $result['ExpiryDate'] ?? null,
            'last_name' => $result['LastName'] ?? null,
            'first_name' => $result['FirstName'] ?? null,
            'middle_name' => $result['MiddleName'] ?? null,
            'birth_date' => $result['BirthDate'] ?? null,
            'birth_place' => $result['BirthPlace'] ?? null,
            'gender' => $result['Gender'] ?? null,
            'categories' => $result['Categories'] ?? [],
            'photo' => $result['Photo'] ?? null,
            'mrz1' => $result['MRZ1'] ?? null,
            'mrz2' => $result['MRZ2'] ?? null,
            'mrz3' => $result['MRZ3'] ?? null,
        ];
    }

    private function extractSnilsData(array $result): array
    {
        return [
            'number' => $result['Number'] ?? null,
            'last_name' => $result['LastName'] ?? null,
            'first_name' => $result['FirstName'] ?? null,
            'middle_name' => $result['MiddleName'] ?? null,
            'birth_date' => $result['BirthDate'] ?? null,
            'gender' => $result['Gender'] ?? null,
            'issue_date' => $result['IssueDate'] ?? null,
            'issued_by' => $result['IssuedBy'] ?? null,
            'registration_date' => $result['RegistrationDate'] ?? null,
        ];
    }

    private function extractVehicleRegistrationData(array $result): array
    {
        return [
            'series' => $result['Series'] ?? null,
            'number' => $result['Number'] ?? null,
            'issued_by' => $result['IssuedBy'] ?? null,
            'issue_date' => $result['IssueDate'] ?? null,
            'vehicle_make' => $result['VehicleMake'] ?? null,
            'vehicle_model' => $result['VehicleModel'] ?? null,
            'year_of_manufacture' => $result['YearOfManufacture'] ?? null,
            'vin' => $result['VIN'] ?? null,
            'engine_number' => $result['EngineNumber'] ?? null,
            'chassis_number' => $result['ChassisNumber'] ?? null,
            'body_number' => $result['BodyNumber'] ?? null,
            'color' => $result['Color'] ?? null,
            'engine_power' => $result['EnginePower'] ?? null,
            'engine_displacement' => $result['EngineDisplacement'] ?? null,
            'fuel_type' => $result['FuelType'] ?? null,
            'owner_name' => $result['OwnerName'] ?? null,
            'owner_address' => $result['OwnerAddress'] ?? null,
            'registration_date' => $result['RegistrationDate'] ?? null,
            'expiry_date' => $result['ExpiryDate'] ?? null,
            'vehicle_type' => $result['VehicleType'] ?? null,
            'weight' => $result['Weight'] ?? null,
            'max_weight' => $result['MaxWeight'] ?? null,
        ];
    }
}
