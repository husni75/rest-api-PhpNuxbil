<?php

class AuthController {
    
    // POST /auth/login
    // Admin login
    public function login() {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($username) || empty($password)) {
            ApiResponse::error("Username and password are required", 400);
        }

        $d = ORM::for_table('tbl_users')->where('username', $username)->find_one();
        if (!$d) {
            ApiResponse::error("Invalid username or password", 401);
        }

        if (Password::_verify($password, $d['password']) !== true) {
            _log($username . ' Failed Login (API) from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'), $d['user_type']);
            ApiResponse::error("Invalid username or password", 401);
        }

        // Generate token using Admin::setCookie and extract the token value
        $token = Admin::setCookie($d['id']);
        if (!$token) {
            ApiResponse::error("Failed to generate token", 500);
        }

        $d->last_login = date('Y-m-d H:i:s');
        $d->save();

        $platform = (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Android') !== false) ? 'Android App' : 'API';
        _log('[' . $platform . '] ' . $username . ' Login Successful - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'), $d['user_type'], $d['id']);

        ApiResponse::success("Login successful", [
            'token' => "a." . $token,
            'user' => [
                'id' => (int)$d['id'],
                'username' => $d['username'],
                'fullname' => $d['fullname'],
                'user_type' => $d['user_type'],
                'email' => $d['email'],
                'phonenumber' => $d['phone'],
                'last_login' => $d['last_login']
            ]
        ]);
    }

    // POST /auth/login-customer
    public function loginCustomer() {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($username) || empty($password)) {
            ApiResponse::error("Username and password are required", 400);
        }

        $d = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if (!$d) {
            ApiResponse::error("Invalid username or password", 401);
        }

        if ($d['status'] === 'Banned') {
            ApiResponse::error("This account is banned", 403);
        }

        if (Password::_uverify($password, $d['password']) !== true) {
            _log($username . ' Failed Login (Android App) from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'), 'Customer');
            ApiResponse::error("Invalid username or password", 401);
        }

        // Generate token for customer
        $tokenData = User::generateToken($d['id']);
        $token = $tokenData['token'];

        $d->last_login = date('Y-m-d H:i:s');
        $d->save();

        _log('[Android App] ' . $username . ' Login Successful - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'), 'Customer', $d['id']);

        ApiResponse::success("Login successful", [
            'token' => "c." . $token,
            'user' => [
                'id' => (int)$d['id'],
                'username' => $d['username'],
                'fullname' => $d['fullname'],
                'email' => $d['email'],
                'phonenumber' => $d['phonenumber'],
                'address' => $d['address'],
                'balance' => (float)$d['balance'],
                'status' => $d['status'],
                'last_login' => $d['last_login']
            ]
        ]);
    }

    // POST /auth/register
    public function register() {
        global $config;
        if (isset($config['disable_registration']) && $config['disable_registration'] === 'yes') {
            ApiResponse::error("Registration is disabled by administrator", 403);
        }

        $username = isset($_POST['username']) ? alphanumeric(trim($_POST['username']), "+_.@-") : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $cpassword = isset($_POST['cpassword']) ? trim($_POST['cpassword']) : '';
        $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $phonenumber = isset($_POST['phonenumber']) ? Lang::phoneFormat(trim($_POST['phonenumber'])) : '';

        if (empty($username) || empty($password)) {
            ApiResponse::error("Username and password are required", 400);
        }

        if (strlen($username) < 3 || strlen($username) > 35) {
            ApiResponse::error("Username should be between 3 and 35 characters", 400);
        }

        if (strlen($password) < 3 || strlen($password) > 35) {
            ApiResponse::error("Password should be between 3 and 35 characters", 400);
        }

        if ($password !== $cpassword) {
            ApiResponse::error("Passwords do not match", 400);
        }

        if ($config['man_fields_fname'] === 'yes' && empty($fullname)) {
            ApiResponse::error("Full name is required", 400);
        }

        if ($config['man_fields_email'] === 'yes') {
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ApiResponse::error("Valid email is required", 400);
            }
        }

        // Check if username already exists
        $existing = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if ($existing) {
            ApiResponse::error("Username is already taken", 400);
        }

        $d = ORM::for_table('tbl_customers')->create();
        $d->username = $username;
        $d->password = $password; 
        $d->fullname = $fullname;
        $d->address = $address;
        $d->email = $email;
        $d->phonenumber = !empty($phonenumber) ? $phonenumber : $username;
        $d->balance = 0;
        $d->status = 'Active';
        $d->created_at = date('Y-m-d H:i:s');

        if ($d->save()) {
            $userId = $d->id();
            
            // Set custom fields if sent in POST body
            User::setFormCustomField($userId);
            
            if ($config['reg_nofify_admin'] === 'yes') {
                sendTelegram($config['CompanyName'] . ' - New User Registration (API)' . "\n\nFull Name: " . $fullname . "\nUsername: " . $username . "\nEmail: " . $email . "\nPhone: " . $d->phonenumber . "\nAddress: " . $address);
            }

            ApiResponse::success("Registration successful", [
                'user' => [
                    'id' => (int)$userId,
                    'username' => $username,
                    'fullname' => $fullname,
                    'email' => $email,
                    'phonenumber' => $d->phonenumber,
                    'address' => $address
                ]
            ], [], 201);
        } else {
            ApiResponse::error("Registration failed. Please try again.", 500);
        }
    }

    // GET /auth/me
    public function me() {
        $user = ApiAuth::getUser();
        $type = ApiAuth::getUserType();

        if ($type === 'admin') {
            ApiResponse::success("Authenticated user details", [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'type' => 'admin',
                'user_type' => $user['user_type'],
                'email' => $user['email'],
                'phonenumber' => $user['phone'],
                'last_login' => $user['last_login']
            ]);
        } else if ($type === 'customer') {
            ApiResponse::success("Authenticated user details", [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'type' => 'customer',
                'email' => $user['email'],
                'phonenumber' => $user['phonenumber'],
                'address' => $user['address'],
                'balance' => (float)$user['balance'],
                'status' => $user['status'],
                'last_login' => $user['last_login']
            ]);
        } else {
            ApiResponse::error("Not authenticated", 401);
        }
    }

    // POST /auth/forgot-password/request
    public function forgotPasswordRequest() {
        global $config, $CACHE_PATH, $db_pass;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        if (empty($username)) {
            ApiResponse::error("Username is required", 400);
        }

        $user = ORM::for_table('tbl_customers')->selects(['phonenumber', 'email'])->where('username', $username)->find_one();
        if (!$user) {
            ApiResponse::error("If your Username is found, Verification Code has been Sent", 400); // generic error
        }

        $otpPath = $CACHE_PATH . File::pathFixer('/forgot/');
        if (!file_exists($otpPath)) {
            @mkdir($otpPath, 0755, true);
        }

        $otpPath .= sha1($username . $db_pass) . ".txt";
        if (file_exists($otpPath) && time() - filemtime($otpPath) < 600) {
            ApiResponse::error("Verification Code already Sent. Please wait " . (600 - (time() - filemtime($otpPath))) . " seconds.", 429);
        }

        $via = $config['user_notification_reminder'];
        if ($via == 'email') {
            $via = 'sms';
        }
        $otp = mt_rand(100000, 999999);
        file_put_contents($otpPath, $otp);
        
        if ($via == 'sms') {
            Message::sendSMS($user['phonenumber'], $config['CompanyName'] . " C0de: $otp");
        } else {
            Message::sendWhatsapp($user['phonenumber'], $config['CompanyName'] . " C0de: $otp");
        }
        Message::sendEmail(
            $user['email'],
            $config['CompanyName'] . Lang::T("Your Verification Code") . ' : ' . $otp,
            Lang::T("Your Verification Code") . ' : <b>' . $otp . '</b>'
        );

        ApiResponse::success("Verification Code has been Sent to Your Phone/Whatsapp", ['via' => $via]);
    }

    // POST /auth/forgot-password/verify
    public function forgotPasswordVerify() {
        global $CACHE_PATH, $db_pass;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $otp_code = isset($_POST['otp_code']) ? trim($_POST['otp_code']) : '';

        if (empty($username) || empty($otp_code)) {
            ApiResponse::error("Username and Verification Code are required", 400);
        }

        $otpPath = $CACHE_PATH . File::pathFixer('/forgot/') . sha1($username . $db_pass) . ".txt";
        if (file_exists($otpPath) && time() - filemtime($otpPath) <= 600) {
            $otp = file_get_contents($otpPath);
            if ($otp == $otp_code) {
                $pass = mt_rand(10000, 99999);
                $user = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
                if ($user) {
                    $user->password = $pass;
                    $user->save();
                    @unlink($otpPath);
                    ApiResponse::success("Verification Code Valid", ['new_password' => $pass]);
                } else {
                    ApiResponse::error("User not found", 404);
                }
            } else {
                ApiResponse::error("Invalid Verification Code", 400);
            }
        } else {
            if (file_exists($otpPath)) {
                @unlink($otpPath);
            }
            ApiResponse::error("Invalid or Expired Verification Code", 400);
        }
    }

    // POST /auth/forgot-username
    public function forgotUsername() {
        global $config, $CACHE_PATH, $db_pass;
        $find = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        if (empty($find)) {
            ApiResponse::error("Phone number is required", 400);
        }
        
        $find = Lang::phoneFormat($find);

        $via = $config['user_notification_reminder'];
        if ($via == 'email') {
            $via = 'sms';
        }
        $otpPath = $CACHE_PATH . File::pathFixer('/forgot/');
        if (!file_exists($otpPath)) {
            @mkdir($otpPath, 0755, true);
        }
        $otpPath .= sha1($find . $db_pass) . ".txt";

        $users = ORM::for_table('tbl_customers')->selects(['username', 'phonenumber', 'email'])->where('phonenumber', $find)->find_array();
        if ($users) {
            if (!file_exists($otpPath) || (file_exists($otpPath) && time() - filemtime($otpPath) >= 600)) {
                $usernames = implode(", ", array_column($users, 'username'));
                if ($via == 'sms') {
                    Message::sendSMS($find, Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames);
                } else {
                    Message::sendWhatsapp($find, Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames);
                }
                file_put_contents($otpPath, time());
            }
            ApiResponse::success("Usernames have been sent to your phone/Whatsapp", []);
        } else {
            ApiResponse::error("No data found for this phone number", 404);
        }
    }

    // PUT /auth/me
    public function updateProfile() {
        $user = ApiAuth::getUser();
        $type = ApiAuth::getUserType();

        if ($type === 'customer') {
            require_once dirname(__FILE__) . '/CustomerPortalController.php';
            $customerController = new CustomerPortalController();
            $customerController->updateProfile();
            return;
        }

        // Get PUT input parameters
        $input = [];
        parse_str(file_get_contents("php://input"), $input);
        if (empty($input)) {
            $json = json_decode(file_get_contents("php://input"), true);
            if (is_array($json)) {
                $input = $json;
            }
        }
        if (empty($input)) {
            $input = $_POST;
        }

        $fullname = isset($input['fullname']) ? trim($input['fullname']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $phone = isset($input['phonenumber']) ? trim($input['phonenumber']) : (isset($input['phone']) ? trim($input['phone']) : '');

        if (!empty($fullname)) $user->fullname = $fullname;
        if (!empty($email)) $user->email = $email;
        if (!empty($phone)) $user->phone = $phone;

        if (isset($input['password']) && !empty($input['password'])) {
            $user->password = Password::_crypt(trim($input['password']));
        }

        $photoBase64 = isset($input['photo']) ? trim($input['photo']) : '';
        if (!empty($photoBase64)) {
            $photoData = base64_decode($photoBase64);
            if ($photoData !== false) {
                global $UPLOAD_PATH;
                $hash = md5($photoData);
                $subfolder = substr($hash, 0, 2);
                $folder = $UPLOAD_PATH . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR;
                if (!file_exists($folder)) {
                    @mkdir($folder, 0755, true);
                }
                $imgPath = $folder . $hash . '.jpg';
                file_put_contents($imgPath, $photoData);
                if (file_exists($imgPath)) {
                    File::makeThumb($imgPath, $imgPath . '.thumb.jpg', 200);
                    if ($user['photo'] != '' && strpos($user['photo'], 'default') === false) {
                        if (file_exists($UPLOAD_PATH . $user['photo'])) {
                            @unlink($UPLOAD_PATH . $user['photo']);
                            @unlink($UPLOAD_PATH . $user['photo'] . '.thumb.jpg');
                        }
                    }
                    $user->photo = '/photos/' . $subfolder . '/' . $hash . '.jpg';
                }
            }
        }

        if ($user->save()) {
            _log('[' . $user['username'] . ']: Updated own profile (API)', $user['user_type'], $user['id']);
            ApiResponse::success("Profile updated successfully");
        } else {
            ApiResponse::error("Failed to update profile", 500);
        }
    }
}
