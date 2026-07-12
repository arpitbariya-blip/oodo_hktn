<?php
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $statusCode >= 200 && $statusCode < 300,
            'data' => $data
        ]);
        exit;
    }

    public static function error($message, $statusCode = 400, $extraData = []) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $payload = [
            'success' => false,
            'error' => $message,
            'code' => $statusCode
        ];
        if (!empty($extraData)) {
            $payload = array_merge($payload, $extraData);
        }
        echo json_encode($payload);
        exit;
    }
}
