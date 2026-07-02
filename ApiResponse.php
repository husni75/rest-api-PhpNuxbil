<?php

class ApiResponse {
    public static function send($success, $message = '', $result = [], $meta = [], $statusCode = 200) {
        // Clear any previous output buffers
        if (ob_get_length()) ob_clean();
        
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=UTF-8");
        
        echo json_encode([
            'success' => (bool)$success,
            'message' => $message,
            'result' => $result,
            'meta' => $meta
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($message = '', $result = [], $meta = [], $statusCode = 200) {
        self::send(true, $message, $result, $meta, $statusCode);
    }

    public static function error($message = '', $statusCode = 400, $result = [], $meta = []) {
        self::send(false, $message, $result, $meta, $statusCode);
    }

    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    public static function notFound($message = 'Not Found') {
        self::error($message, 404);
    }

    public static function internalServerError($message = 'Internal Server Error') {
        self::error($message, 500);
    }
}
