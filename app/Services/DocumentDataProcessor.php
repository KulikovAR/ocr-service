<?php

namespace App\Services;

class DocumentDataProcessor
{
    public function processRecognitionData(array $data, string $documentType): array
    {
        if (!is_array($data['documents']) || empty($data['documents'])) {
            return [];
        }

        $processedData = [
            'document_type' => $documentType,
            'recognition_status' => 'completed',
            'extracted_data' => [],
            'confidence_score' => 0.0,
            'processing_time' => $data['processing_time'] ?? 0,
        ];


        $document = $data['documents'][0];
        $documentData = $document['data'] ?? [];
        $metadata = $document['metadata'] ?? [];

        $processedData['extracted_data'] = $this->extractDocumentData($documentData, $documentType);

        if (isset($metadata['confidences']) && is_array($metadata['confidences'])) {
            $confidences = array_values($metadata['confidences']);
            $processedData['confidence_score'] = !empty($confidences) ? array_sum($confidences) / count($confidences) : 0.0;
        }

        $processedData['metadata'] = [
            'verifications' => $metadata['verifications'] ?? [],
            'external_integrations' => $metadata['external_integrations'] ?? [],
            'broken_reasons' => $document['broken_reasons'] ?? [],
            'broken_reasons_ru' => $document['broken_reasons_ru'] ?? [],
        ];

        foreach ($data['documents'] as $document) {
            if (!empty($document['broken_reasons']) || !empty($document['broken_reasons_ru'])) {
                $processedData['recognition_status'] = 'completed_with_errors';
                $processedData['errors'] = [...$document['broken_reasons'] ?? [], ...$document['broken_reasons_ru'] ?? []];
                break;
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
            'issuedBy' => $result['IssuedBy'] ?? null,
            'issueDate' => $result['IssueDate'] ?? null,
            'issueId' => $result['IssueId'] ?? null,
            'series' => $result['Series'] ?? null,
            'number' => $result['Number'] ?? null,
            'gender' => $result['Gender'] ?? null,
            'lastName' => $result['LastName'] ?? null,
            'firstName' => $result['FirstName'] ?? null,
            'middleName' => $result['MiddleName'] ?? null,
            'birthDate' => $result['BirthDate'] ?? null,
            'birthPlace' => $result['BirthPlace'] ?? null,
            'hasPhoto' => $result['HasPhoto'] ?? null,
            'hasOwnerSignature' => $result['HasOwnerSignature'] ?? null,
            'address' => $result['Address'] ?? null,
            'MRZ1' => $result['MRZ1'] ?? null,
            'MRZ2' => $result['MRZ2'] ?? null,
        ];
    }

    private function extractDriverLicenseData(array $result): array
    {
        return [
            'series' => $result['Series'] ?? null,
            'number' => $result['Number'] ?? null,
            'issuedBy' => $result['IssuedBy'] ?? null,
            'issueDate' => $result['IssueDate'] ?? null,
            'expiryDate' => $result['ExpiryDate'] ?? null,
            'lastName' => $result['LastName'] ?? null,
            'firstName' => $result['FirstName'] ?? null,
            'middleName' => $result['MiddleName'] ?? null,
            'birthDate' => $result['BirthDate'] ?? null,
            'birthPlace' => $result['BirthPlace'] ?? null,
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
            'lastName' => $result['LastName'] ?? null,
            'firstName' => $result['FirstName'] ?? null,
            'middleName' => $result['MiddleName'] ?? null,
            'birthDate' => $result['BirthDate'] ?? null,
            'gender' => $result['Gender'] ?? null,
            'issueDate' => $result['IssueDate'] ?? null,
            'issuedBy' => $result['IssuedBy'] ?? null,
            'registrationDate' => $result['RegistrationDate'] ?? null,
        ];
    }

    private function extractVehicleRegistrationData(array $result): array
    {
        return [
            'series' => $result['Series'] ?? null,
            'number' => $result['Number'] ?? null,
            'issuedBy' => $result['IssuedBy'] ?? null,
            'issueDate' => $result['IssueDate'] ?? null,
            'vehicleMake' => $result['VehicleMake'] ?? null,
            'vehicleModel' => $result['VehicleModel'] ?? null,
            'yearOfManufacture' => $result['YearOfManufacture'] ?? null,
            'vin' => $result['VIN'] ?? null,
            'engineNumber' => $result['EngineNumber'] ?? null,
            'chassisNumber' => $result['ChassisNumber'] ?? null,
            'bodyNumber' => $result['BodyNumber'] ?? null,
            'color' => $result['Color'] ?? null,
            'enginePower' => $result['EnginePower'] ?? null,
            'engineDisplacement' => $result['EngineDisplacement'] ?? null,
            'fuelType' => $result['FuelType'] ?? null,
            'ownerName' => $result['OwnerName'] ?? null,
            'ownerAddress' => $result['OwnerAddress'] ?? null,
            'registrationDate' => $result['RegistrationDate'] ?? null,
            'expiryDate' => $result['ExpiryDate'] ?? null,
            'vehicleType' => $result['VehicleType'] ?? null,
            'weight' => $result['Weight'] ?? null,
            'maxWeight' => $result['MaxWeight'] ?? null,
        ];
    }
}
