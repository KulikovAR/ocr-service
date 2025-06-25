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

        if (isset($data['result']) && is_array($data['result'])) {
            $processedData['extracted_data'] = $this->extractDocumentData($data['result'], $documentType);
            $processedData['confidence_score'] = $data['confidence_score'] ?? 0.0;
        }

        if (isset($data['errors']) && !empty($data['errors'])) {
            $processedData['recognition_status'] = 'completed_with_errors';
            $processedData['errors'] = $data['errors'];
        }

        return $processedData;
    }

    private function extractDocumentData(array $result, string $documentType): array
    {
        $extractedData = [];

        switch ($documentType) {
            case 'PASSPORT':
            case 'PASSPORT_REG':
                $extractedData = $this->extractPassportData($result);
                break;
            case 'DLIC':
                $extractedData = $this->extractDriverLicenseData($result);
                break;
            case 'SNILS':
                $extractedData = $this->extractSnilsData($result);
                break;
            case 'STS':
                $extractedData = $this->extractVehicleRegistrationData($result);
                break;
            default:
                $extractedData = $result;
        }

        return $extractedData;
    }

    private function extractPassportData(array $result): array
    {
        return [
            'series' => $result['series'] ?? null,
            'number' => $result['number'] ?? null,
            'issued_by' => $result['issued_by'] ?? null,
            'issue_date' => $result['issue_date'] ?? null,
            'department_code' => $result['department_code'] ?? null,
            'last_name' => $result['last_name'] ?? null,
            'first_name' => $result['first_name'] ?? null,
            'middle_name' => $result['middle_name'] ?? null,
            'birth_date' => $result['birth_date'] ?? null,
            'birth_place' => $result['birth_place'] ?? null,
            'gender' => $result['gender'] ?? null,
            'registration_address' => $result['registration_address'] ?? null,
        ];
    }

    private function extractDriverLicenseData(array $result): array
    {
        return [
            'series' => $result['series'] ?? null,
            'number' => $result['number'] ?? null,
            'issued_by' => $result['issued_by'] ?? null,
            'issue_date' => $result['issue_date'] ?? null,
            'expiry_date' => $result['expiry_date'] ?? null,
            'last_name' => $result['last_name'] ?? null,
            'first_name' => $result['first_name'] ?? null,
            'middle_name' => $result['middle_name'] ?? null,
            'birth_date' => $result['birth_date'] ?? null,
            'birth_place' => $result['birth_place'] ?? null,
            'categories' => $result['categories'] ?? [],
            'photo' => $result['photo'] ?? null,
        ];
    }

    private function extractSnilsData(array $result): array
    {
        return [
            'number' => $result['number'] ?? null,
            'last_name' => $result['last_name'] ?? null,
            'first_name' => $result['first_name'] ?? null,
            'middle_name' => $result['middle_name'] ?? null,
            'birth_date' => $result['birth_date'] ?? null,
            'gender' => $result['gender'] ?? null,
            'issue_date' => $result['issue_date'] ?? null,
        ];
    }

    private function extractVehicleRegistrationData(array $result): array
    {
        return [
            'series' => $result['series'] ?? null,
            'number' => $result['number'] ?? null,
            'issued_by' => $result['issued_by'] ?? null,
            'issue_date' => $result['issue_date'] ?? null,
            'vehicle_make' => $result['vehicle_make'] ?? null,
            'vehicle_model' => $result['vehicle_model'] ?? null,
            'year_of_manufacture' => $result['year_of_manufacture'] ?? null,
            'vin' => $result['vin'] ?? null,
            'engine_number' => $result['engine_number'] ?? null,
            'chassis_number' => $result['chassis_number'] ?? null,
            'body_number' => $result['body_number'] ?? null,
            'color' => $result['color'] ?? null,
            'engine_power' => $result['engine_power'] ?? null,
            'engine_displacement' => $result['engine_displacement'] ?? null,
            'fuel_type' => $result['fuel_type'] ?? null,
            'owner_name' => $result['owner_name'] ?? null,
            'owner_address' => $result['owner_address'] ?? null,
        ];
    }

    private function extractConfidences(array $rawData): array
    {
        $confidences = [];
        
        if (isset($rawData['confidences']) && is_array($rawData['confidences'])) {
            foreach ($rawData['confidences'] as $field => $confidence) {
                $confidences[$field] = is_numeric($confidence) ? (float) $confidence : 0.0;
            }
        }
        
        if (isset($rawData['confidence']) && is_array($rawData['confidence'])) {
            foreach ($rawData['confidence'] as $field => $confidence) {
                $confidences[$field] = is_numeric($confidence) ? (float) $confidence : 0.0;
            }
        }

        return $confidences;
    }

    private function extractVerifications(array $rawData): array
    {
        $verifications = [];
        
        if (isset($rawData['verifications']) && is_array($rawData['verifications'])) {
            foreach ($rawData['verifications'] as $field => $verification) {
                $verifications[$field] = [
                    'valid' => $verification['valid'] ?? false,
                    'message' => $verification['message'] ?? null,
                    'source' => $verification['source'] ?? null,
                ];
            }
        }
        
        if (isset($rawData['verification']) && is_array($rawData['verification'])) {
            foreach ($rawData['verification'] as $field => $verification) {
                $verifications[$field] = [
                    'valid' => $verification['valid'] ?? false,
                    'message' => $verification['message'] ?? null,
                    'source' => $verification['source'] ?? null,
                ];
            }
        }

        return $verifications;
    }

    public function validateRequiredFields(array $data, string $documentType): array
    {
        $requiredFields = $this->getRequiredFields($documentType);
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    private function getRequiredFields(string $documentType): array
    {
        switch ($documentType) {
            case 'PASSPORT':
            case 'PASSPORT_REG':
                return ['Series', 'Number', 'LastName', 'FirstName', 'BirthDate'];
            case 'SNILS':
                return ['Number', 'Lastname', 'Firstname', 'BirthDate'];
            case 'DLIC':
                return ['Series', 'Number', 'LastName', 'FirstName', 'BirthDate'];
            case 'STS':
                return ['reg_number', 'vin', 'brand_rus', 'model_rus'];
            default:
                return [];
        }
    }
} 