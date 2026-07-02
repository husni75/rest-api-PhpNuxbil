<?php

class ApiLog {
    const SOURCE = 'Android REST';

    public static function write($description, $type = '', $userid = '0') {
        _log('[' . self::SOURCE . '] ' . $description, $type, $userid);
    }

    public static function admin($admin, $description) {
        self::write('[' . $admin['username'] . ']: ' . $description, $admin['user_type'], $admin['id']);
    }

    public static function customer($customer, $description) {
        self::write($customer['username'] . ' ' . $description, 'User', $customer['id']);
    }
}
