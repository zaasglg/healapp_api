<?php
// Пример скрипта для обновления только settings.all_indicators дневника через API

$apiUrl = 'https://your-api-url.com/api/v1/diary/pinned'; // Замените на ваш URL
$token = 'ВАШ_ТОКЕН'; // Замените на ваш Bearer Token

$payload = [
    'patient_id' => 18, // ID пациента
    'pinned_parameters' => [], // Можно оставить пустым, если не обновляете
    'settings' => [
        'all_indicators' => [
            'vitamins',
            'meal',
            'skin_moisturizing',
            'diaper_change',
            'walk',
            'sleep',
            'pain_level',
            'oxygen_saturation',
            'defecation'
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Вывод результата
header('Content-Type: application/json');
echo json_encode([
    'http_code' => $httpCode,
    'response' => json_decode($response, true)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
