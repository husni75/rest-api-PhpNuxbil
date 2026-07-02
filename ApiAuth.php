<?php

class ApiAuth {
    private static $currentUser = null;
    private static $userType = null; // 'admin' or 'customer'

    public static function getToken() {
        // 1. Get headers
        $headers = [];
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }
        
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        // 2. Check Authorization header
        if (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // 3. Check query parameter or POST body
        if (isset($_REQUEST['token']) && !empty($_REQUEST['token'])) {
            return $_REQUEST['token'];
        }

        return null;
    }

    public static function validate() {
        global $api_secret, $db_pass, $config;
        
        $token = self::getToken();
        if (!$token) {
            return false;
        }

        // If the token matches the system API key (admin bypass)
        if (isset($config['api_key']) && !empty($config['api_key']) && $token === $config['api_key']) {
            $admin = ORM::for_table('tbl_users')->where('user_type', 'SuperAdmin')->find_one();
            if (!$admin) {
                $admin = ORM::for_table('tbl_users')->where('user_type', 'Admin')->find_one();
            }
            if ($admin) {
                $_SESSION['aid'] = $admin['id'];
                self::$currentUser = $admin;
                self::$userType = 'admin';
                return true;
            }
        }

        // Token parts
        $parts = explode('.', $token);
        if (count($parts) !== 4) {
            // Check if it has 3 parts without prefix (default user token format uid.time.sha1)
            if (count($parts) === 3) {
                $tipe = 'c';
                $uid = $parts[0];
                $time = $parts[1];
                $sha1 = $parts[2];
            } else {
                return false;
            }
        } else {
            list($tipe, $uid, $time, $sha1) = $parts;
        }

        $secret = !empty($api_secret) ? $api_secret : $db_pass;
        $expectedSha1 = sha1($uid . '.' . $time . '.' . $secret);

        if (trim($sha1) !== $expectedSha1) {
            return false;
        }

        // Check expiration (3 months)
        if ($time != 0 && (time() - $time > 7776000)) {
            return false;
        }

        if ($tipe === 'a') {
            $_SESSION['aid'] = $uid;
            $admin = ORM::for_table('tbl_users')->find_one($uid);
            if (!$admin) {
                return false;
            }
            self::$currentUser = $admin;
            self::$userType = 'admin';
            return true;
        } else if ($tipe === 'c' || $tipe === 'u') {
            $_SESSION['uid'] = $uid;
            $customer = ORM::for_table('tbl_customers')->find_one($uid);
            if (!$customer || $customer['status'] === 'Banned') {
                return false;
            }
            self::$currentUser = $customer;
            self::$userType = 'customer';
            return true;
        }

        return false;
    }

    public static function admin() {
        if (!self::validate() || self::$userType !== 'admin') {
            ApiResponse::unauthorized("Unauthorized: Admin access required");
        }
        if (self::$currentUser && strtolower(self::$currentUser['username']) === 'adminuji') {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
                ApiResponse::forbidden("Akses Ditolak: Akun penguji (adminuji) tidak diperbolehkan mengubah data atau pengaturan.");
            }
        }
    }

    public static function customer() {
        if (!self::validate() || self::$userType !== 'customer') {
            ApiResponse::unauthorized("Unauthorized: Customer access required");
        }
    }

    public static function any() {
        if (!self::validate()) {
            ApiResponse::unauthorized("Unauthorized: Valid token required");
        }
        if (self::$currentUser && self::$userType === 'admin' && strtolower(self::$currentUser['username']) === 'adminuji') {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
                ApiResponse::forbidden("Akses Ditolak: Akun penguji (adminuji) tidak diperbolehkan mengubah data atau pengaturan.");
            }
        }
    }

    public static function getUser() {
        return self::$currentUser;
    }

    public static function getUserType() {
        return self::$userType;
    }
}
