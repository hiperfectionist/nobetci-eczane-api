<?php

namespace App\Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(
        array $pharmacies,
        string $city,
        ?string $district = null,
        array $extra = []
    ): void {
        $response = [
            'status'        => 'success',
            'sehir'         => $city,
            'ilce'          => $district,
            'tarih'         => date('Y-m-d'),
            'saat'          => date('H:i:s'),
            'count'         => count($pharmacies),
            'data'          => $pharmacies
        ];

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        self::json($response, 200);
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        $response = array_merge([
            'status'  => 'error',
            'message' => $message,
            'tarih'   => date('Y-m-d')
        ], $extra);

        self::json($response, $statusCode);
    }
}
