<?php

class CustomerController {
    
    // GET /customers
    public function list() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 10;
        $offset = ($page - 1) * $limit;

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $router = isset($_GET['router']) ? trim($_GET['router']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $coordinates = isset($_GET['coordinates']) ? trim($_GET['coordinates']) : '';
        
        file_put_contents(
            dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'debug_api.log', 
            date('Y-m-d H:i:s') . " - router: '$router', coordinates: '$coordinates', status: '$status', search: '$search'\n", 
            FILE_APPEND
        );
        
        $query = ORM::for_table('tbl_customers');
        if (!empty($search)) {
            $query->where_raw('(username LIKE ? OR fullname LIKE ? OR email LIKE ? OR phonenumber LIKE ?)', ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }

        if (!empty($router)) {
            $query->where_raw("id IN (SELECT customer_id FROM tbl_user_recharges r1 WHERE LOWER(r1.routers) = LOWER(?) AND r1.id = (SELECT MAX(r2.id) FROM tbl_user_recharges r2 WHERE r2.customer_id = r1.customer_id))", [$router]);
        }

        if (!empty($status)) {
            if (strtolower($status) === 'active') {
                $query->where_raw("id IN (SELECT customer_id FROM tbl_user_recharges WHERE status = 'on')");
            } else if (strtolower($status) === 'expired') {
                $query->where_raw("id NOT IN (SELECT customer_id FROM tbl_user_recharges WHERE status = 'on')");
            } else {
                $query->where('status', $status);
            }
        }

        $totalAllQuery = clone $query;
        $totalAll = $totalAllQuery->count();

        if ($coordinates === '1' || $coordinates === 'true') {
            $query->where_not_equal('coordinates', '');
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $customers = $query->limit($limit)->offset($offset)->order_by_desc('id')->find_many();

        $data = [];
        foreach ($customers as $c) {
            $data[] = [
                'id' => (int)$c['id'],
                'username' => $c['username'],
                'fullname' => $c['fullname'],
                'email' => $c['email'],
                'phonenumber' => $c['phonenumber'],
                'balance' => (float)$c['balance'],
                'status' => $c['status'],
                'address' => $c['address'],
                'coordinates' => $c['coordinates'],
                'service_type' => $c['service_type'],
                'account_type' => $c['account_type'],
                'photo' => $c['photo'],
                'created_at' => $c['created_at'],
                'last_login' => $c['last_login']
            ];
        }

        ApiResponse::success("Customer list details", $data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'total_count' => $totalAll
        ]);
    }

    // GET /customers/{id}
    public function detail($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid customer ID", 400);
        }

        $c = ORM::for_table('tbl_customers')->find_one($id);
        if (!$c) {
            ApiResponse::error("Customer not found", 404);
        }

        // Fetch custom fields
        $fields = ORM::for_table('tbl_customers_fields')->where('customer_id', $id)->find_many();
        $customFields = [];
        foreach ($fields as $f) {
            $customFields[] = [
                'name' => $f['field_name'],
                'value' => $f['field_value']
            ];
        }

        // Fetch active packages
        $packagesRaw = User::_billing($id);
        $packages = [];
        foreach ($packagesRaw as $pkg) {
            $packages[] = [
                'id' => (int)$pkg['id'],
                'plan_id' => (int)$pkg['plan_id'],
                'namebp' => $pkg['namebp'],
                'recharged_on' => $pkg['recharged_on'],
                'expiration' => $pkg['expiration'],
                'status' => $pkg['status'],
                'routers' => $pkg['routers'],
                'price' => (float)$pkg['price']
            ];
        }

        $createdByName = '-';
        if (isset($c['created_by']) && (int)$c['created_by'] > 0) {
            $creator = ORM::for_table('tbl_users')->find_one((int)$c['created_by']);
            if ($creator) {
                $createdByName = $creator['fullname'] ? $creator['fullname'] : $creator['username'];
            }
        }

        ApiResponse::success("Customer details", [
            'id' => (int)$c['id'],
            'username' => $c['username'],
            'fullname' => $c['fullname'],
            'pppoe_username' => $c['pppoe_username'],
            'pppoe_password' => $c['pppoe_password'],
            'pppoe_ip' => $c['pppoe_ip'],
            'email' => $c['email'],
            'phonenumber' => $c['phonenumber'],
            'address' => $c['address'],
            'balance' => (float)$c['balance'],
            'status' => $c['status'],
            'service_type' => $c['service_type'],
            'account_type' => $c['account_type'],
            'photo' => $c['photo'],
            'coordinates' => $c['coordinates'],
            'city' => $c['city'],
            'district' => $c['district'],
            'state' => $c['state'],
            'zip' => $c['zip'],
            'created_by' => $createdByName,
            'created_at' => $c['created_at'],
            'last_login' => $c['last_login'],
            'custom_fields' => $customFields,
            'packages' => $packages
        ]);
    }

    // POST /customers
    public function create() {
        $admin = ApiAuth::getUser();
        
        $username = isset($_POST['username']) ? alphanumeric(trim($_POST['username']), ":+_.@-") : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
        
        if (empty($username) || empty($password) || empty($fullname)) {
            ApiResponse::error("Username, password, and fullname are required fields", 400);
        }
        
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phonenumber = isset($_POST['phonenumber']) ? Lang::phoneFormat(trim($_POST['phonenumber'])) : '';
        $pppoe_username = isset($_POST['pppoe_username']) ? trim($_POST['pppoe_username']) : '';

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::error("Email format is not valid", 400);
        }
        if (!empty($phonenumber) && !preg_match("/^[0-9+]{8,15}$/", $phonenumber)) {
            ApiResponse::error("Phone number format is not valid (8-15 digits)", 400);
        }

        // Check if username already exists
        $existing = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if ($existing) {
            ApiResponse::error("Customer username already exists", 400);
        }
        
        if (!empty($pppoe_username)) {
            $existingPppoe = ORM::for_table('tbl_customers')->where('pppoe_username', $pppoe_username)->find_one();
            if ($existingPppoe) {
                ApiResponse::error("PPPoE Username already used by another customer", 400);
            }
        }

        $c = ORM::for_table('tbl_customers')->create();
        $c->username = $username;
        $c->password = $password;
        $c->fullname = $fullname;
        
        $c->pppoe_username = isset($_POST['pppoe_username']) ? trim($_POST['pppoe_username']) : '';
        $c->pppoe_password = isset($_POST['pppoe_password']) ? trim($_POST['pppoe_password']) : '';
        $c->pppoe_ip = isset($_POST['pppoe_ip']) ? trim($_POST['pppoe_ip']) : '';
        $c->email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $c->address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $c->phonenumber = isset($_POST['phonenumber']) ? Lang::phoneFormat(trim($_POST['phonenumber'])) : $username;
        $c->service_type = isset($_POST['service_type']) ? trim($_POST['service_type']) : 'Hotspot';
        $c->account_type = isset($_POST['account_type']) ? trim($_POST['account_type']) : 'Prepaid';
        $c->coordinates = isset($_POST['coordinates']) ? trim($_POST['coordinates']) : '';
        $c->city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $c->district = isset($_POST['district']) ? trim($_POST['district']) : '';
        $c->state = isset($_POST['state']) ? trim($_POST['state']) : '';
        $c->zip = isset($_POST['zip']) ? trim($_POST['zip']) : '';
        $c->balance = isset($_POST['balance']) ? (float)$_POST['balance'] : 0.0;
        $c->status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';
        $c->created_by = $admin['id'];
        $c->created_at = date('Y-m-d H:i:s');
        
        $photoBase64 = isset($_POST['photo']) ? trim($_POST['photo']) : '';
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
                    $c->photo = '/photos/' . $subfolder . '/' . $hash . '.jpg';
                }
            }
        }

        if ($c->save()) {
            $customerId = $c->id();

            // Handle custom fields if sent
            if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                foreach ($_POST['custom_fields'] as $name => $value) {
                    if (!empty($name)) {
                        $f = ORM::for_table('tbl_customers_fields')->create();
                        $f->customer_id = $customerId;
                        $f->field_name = $name;
                        $f->field_value = $value;
                        $f->save();
                    }
                }
            }

            ApiResponse::success("Customer created successfully", ['id' => (int)$customerId], [], 201);
        } else {
            ApiResponse::error("Failed to create customer", 500);
        }
    }

    // PUT /customers/{id}
    public function update($params) {
        global $_app_stage;
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid customer ID", 400);
        }

        $c = ORM::for_table('tbl_customers')->find_one($id);
        if (!$c) {
            ApiResponse::error("Customer not found", 404);
        }

        // Put request data in PHP input
        $input = [];
        parse_str(file_get_contents("php://input"), $input);
        if (empty($input)) {
            // Check if JSON
            $json = json_decode(file_get_contents("php://input"), true);
            if (is_array($json)) {
                $input = $json;
            }
        }

        // If put request is empty, fallback to $_POST in case of form-data POST emulation
        if (empty($input)) {
            $input = $_POST;
        }

        $username = isset($input['username']) ? alphanumeric(trim($input['username']), ":+_.@-") : '';
        $fullname = isset($input['fullname']) ? trim($input['fullname']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $phonenumber = isset($input['phonenumber']) ? Lang::phoneFormat(trim($input['phonenumber'])) : '';
        $pppoe_username = isset($input['pppoe_username']) ? trim($input['pppoe_username']) : '';

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::error("Email format is not valid", 400);
        }
        if (!empty($phonenumber) && !preg_match("/^[0-9+]{8,15}$/", $phonenumber)) {
            ApiResponse::error("Phone number format is not valid (8-15 digits)", 400);
        }
        
        if (!empty($pppoe_username) && $pppoe_username !== $c['pppoe_username']) {
            $existingPppoe = ORM::for_table('tbl_customers')->where('pppoe_username', $pppoe_username)->find_one();
            if ($existingPppoe) {
                ApiResponse::error("PPPoE Username already used by another customer", 400);
            }
        }

        if (!empty($username) && $username !== $c['username']) {
            // Check if username taken
            $existing = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
            if ($existing) {
                ApiResponse::error("Username is already taken by another customer", 400);
            }
            
            // Sync/update device if username changed
            $turs = ORM::for_table('tbl_user_recharges')->where('customer_id', $c['id'])->findMany();
            foreach ($turs as $tur) {
                $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
                $dvc = Package::getDevice($p);
                if (file_exists($dvc) && $tur['status'] == 'on') {
                    require_once $dvc;
                    try {
                        (new $p['device'])->change_username($p, $c['username'], $username);
                    } catch (Exception $e) {
                        // Log and ignore device communication issues during username change
                    }
                }
            }
            $c->username = $username;
        }

        if (!empty($fullname)) {
            $c->fullname = $fullname;
        }

        if ($password !== '') {
            $c->password = $password;
        }

        $oldPppoeUsername = $c['pppoe_username'];
        $oldPppoePassword = $c['pppoe_password'];

        if (isset($input['pppoe_username'])) $c->pppoe_username = trim($input['pppoe_username']);
        if (isset($input['pppoe_password'])) $c->pppoe_password = trim($input['pppoe_password']);
        if (isset($input['pppoe_ip'])) $c->pppoe_ip = trim($input['pppoe_ip']);
        if (isset($input['email'])) $c->email = trim($input['email']);
        if (isset($input['address'])) $c->address = trim($input['address']);
        if (isset($input['phonenumber'])) $c->phonenumber = Lang::phoneFormat(trim($input['phonenumber']));
        if (isset($input['service_type'])) $c->service_type = trim($input['service_type']);
        if (isset($input['account_type'])) $c->account_type = trim($input['account_type']);
        if (isset($input['coordinates'])) $c->coordinates = trim($input['coordinates']);
        if (isset($input['city'])) $c->city = trim($input['city']);
        if (isset($input['district'])) $c->district = trim($input['district']);
        if (isset($input['state'])) $c->state = trim($input['state']);
        if (isset($input['zip'])) $c->zip = trim($input['zip']);
        if (isset($input['balance'])) $c->balance = (float)$input['balance'];
        if (isset($input['status'])) $c->status = trim($input['status']);
        
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
                    if ($c['photo'] != '' && strpos($c['photo'], 'default') === false) {
                        if (file_exists($UPLOAD_PATH . $c['photo'])) {
                            @unlink($UPLOAD_PATH . $c['photo']);
                            @unlink($UPLOAD_PATH . $c['photo'] . '.thumb.jpg');
                        }
                    }
                    $c->photo = '/photos/' . $subfolder . '/' . $hash . '.jpg';
                }
            }
        }
        
        if ($c->save()) {
            // Update custom fields if sent
            if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
                foreach ($input['custom_fields'] as $name => $value) {
                    if (!empty($name)) {
                        $f = ORM::for_table('tbl_customers_fields')->where('customer_id', $id)->where('field_name', $name)->find_one();
                        if (!$f) {
                            $f = ORM::for_table('tbl_customers_fields')->create();
                            $f->customer_id = $id;
                            $f->field_name = $name;
                        }
                        $f->field_value = $value;
                        $f->save();
                    }
                }
            }

            // Sync password & PPPoE credentials to Mikrotik if changed
            if ($_app_stage != 'demo') {
                $passwordChanged = ($password !== '');
                $pppoePasswordChanged = isset($input['pppoe_password']) && trim($input['pppoe_password']) !== $oldPppoePassword;
                $pppoeUsernameChanged = isset($input['pppoe_username']) && trim($input['pppoe_username']) !== $oldPppoeUsername;

                // Audit log: catat apa saja yang diubah admin
                $changes = [];
                if ($passwordChanged) $changes[] = 'password akun';
                if ($pppoePasswordChanged) $changes[] = 'password PPPoE';
                if ($pppoeUsernameChanged) $changes[] = 'username PPPoE (dari \''. $oldPppoeUsername . '\' ke \'' . $c['pppoe_username'] . '\')';

                if (!empty($changes)) {
                    $admin = ApiAuth::getUser();
                    _log('[API] Admin [' . $admin['username'] . '] mengubah ' . implode(', ', $changes) . ' untuk customer [' . $c['username'] . ']', $admin['user_type'], $admin['id']);
                }

                if ($passwordChanged || $pppoePasswordChanged || $pppoeUsernameChanged) {
                    $turs = ORM::for_table('tbl_user_recharges')
                        ->where('customer_id', $id)
                        ->where('status', 'on')
                        ->find_many();

                    foreach ($turs as $tur) {
                        $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
                        if (!$p) continue;

                        $dvc = Package::getDevice($p);
                        if (!file_exists($dvc)) continue;

                        require_once $dvc;
                        try {
                            $deviceInstance = new $p['device']();
                            $mikrotik = $deviceInstance->info($p['routers']);
                            if (!$mikrotik) continue;

                            $client = $deviceInstance->getClient(
                                $mikrotik['ip_address'],
                                $mikrotik['username'],
                                $mikrotik['password']
                            );

                            if ($p['device'] === 'MikrotikHotspot' && $passwordChanged) {
                                // Update password hotspot
                                $deviceInstance->setHotspotUser($client, $c['username'], $password);
                                // Disconnect session aktif
                                $deviceInstance->removeHotspotActiveUser($client, $c['username']);
                                _log('[API] Password Hotspot Mikrotik [' . $p['routers'] . '] untuk customer [' . $c['username'] . '] diperbarui oleh admin [' . $admin['username'] . '] & sesi aktif di-disconnect', $admin['user_type'], $admin['id']);

                            } elseif ($p['device'] === 'MikrotikPppoe' && ($pppoePasswordChanged || $pppoeUsernameChanged)) {
                                // Tentukan username PPPoE lama
                                $pppoeUser = !empty($oldPppoeUsername) ? $oldPppoeUsername : $c['username'];
                                $newPppoePass = !empty($c['pppoe_password']) ? $c['pppoe_password'] : $c['password'];
                                $newPppoeUser = !empty($c['pppoe_username']) ? $c['pppoe_username'] : $c['username'];

                                // Cari ID secret PPPoE di Mikrotik berdasarkan username lama
                                $printRequest = new \PEAR2\Net\RouterOS\Request('/ppp/secret/print');
                                $printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('name', $pppoeUser));
                                $cid = $client->sendSync($printRequest)->getProperty('.id');

                                if (!empty($cid)) {
                                    $setRequest = new \PEAR2\Net\RouterOS\Request('/ppp/secret/set');
                                    $setRequest->setArgument('numbers', $cid);
                                    if ($pppoeUsernameChanged) {
                                        $setRequest->setArgument('name', $newPppoeUser);
                                    }
                                    if ($pppoePasswordChanged) {
                                        $setRequest->setArgument('password', $newPppoePass);
                                    }
                                    $client->sendSync($setRequest);
                                }

                                // Disconnect session PPPoE aktif dengan username lama
                                $deviceInstance->removePpoeActive($client, $pppoeUser);
                                if ($pppoeUsernameChanged && $pppoeUser !== $c['username']) {
                                    $deviceInstance->removePpoeActive($client, $c['username']);
                                }
                                _log('[API] Kredensial PPPoE Mikrotik [' . $p['routers'] . '] untuk customer [' . $c['username'] . '] diperbarui oleh admin [' . $admin['username'] . '] & sesi aktif di-disconnect', $admin['user_type'], $admin['id']);
                            }

                        } catch (Exception $e) {
                            _log('[API] Gagal sync kredensial ke Mikrotik [' . $p['routers'] . '] untuk customer [' . $c['username'] . ']: ' . $e->getMessage(), 'User', $id);
                        }
                    }
                }
            }

            ApiResponse::success("Customer updated successfully");
        } else {
            ApiResponse::error("Failed to update customer", 500);
        }
    }

    // DELETE /customers/{id}
    public function delete($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid customer ID", 400);
        }

        $c = ORM::for_table('tbl_customers')->find_one($id);
        if (!$c) {
            ApiResponse::error("Customer not found", 404);
        }

        // Delete custom fields
        ORM::for_table('tbl_customers_fields')->where('customer_id', $id)->delete_many();

        // Delete active packages & remove from devices
        $turs = ORM::for_table('tbl_user_recharges')->where('username', $c['username'])->find_many();
        foreach ($turs as $tur) {
            $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
            if ($p) {
                $dvc = Package::getDevice($p);
                if (file_exists($dvc)) {
                    require_once $dvc;
                    try {
                        $p['plan_expired'] = 0;
                        (new $p['device'])->remove_customer($c, $p);
                    } catch (Exception $e) {
                        // ignore device removal error
                    }
                }
            }
            try {
                $tur->delete();
            } catch (Exception $e) {}
        }

        if ($c->delete()) {
            ApiResponse::success("Customer deleted successfully");
        } else {
            ApiResponse::error("Failed to delete customer", 500);
        }
    }

    // GET /customers/{id}/packages
    public function packages($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid customer ID", 400);
        }

        $c = ORM::for_table('tbl_customers')->find_one($id);
        if (!$c) {
            ApiResponse::error("Customer not found", 404);
        }

        $packagesRaw = User::_billing($id);
        $data = [];
        foreach ($packagesRaw as $pkg) {
            $data[] = [
                'id' => (int)$pkg['id'],
                'customer_id' => (int)$pkg['customer_id'],
                'username' => $pkg['username'],
                'plan_id' => (int)$pkg['plan_id'],
                'namebp' => $pkg['namebp'],
                'recharged_on' => $pkg['recharged_on'],
                'recharged_time' => $pkg['recharged_time'],
                'expiration' => $pkg['expiration'],
                'time' => $pkg['time'],
                'status' => $pkg['status'],
                'method' => $pkg['method'],
                'plan_type' => $pkg['plan_type'],
                'routers' => $pkg['routers'],
                'type' => $pkg['type'],
                'admin_id' => (int)$pkg['admin_id'],
                'price' => (float)$pkg['price'],
                'name_bw' => $pkg['name_bw']
            ];
        }

        ApiResponse::success("Customer package list", $data);
    }

    // POST /customers/deactivate
    public function deactivate($params) {
        $admin = ApiAuth::getUser();
        
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        if (empty($body)) {
            $body = $_POST;
        }

        $id_customer = isset($body['id_customer']) ? (int)$body['id_customer'] : 0;
        $plan_id = isset($body['plan_id']) ? (int)$body['plan_id'] : 0;

        if ($id_customer <= 0 || $plan_id <= 0) {
            ApiResponse::error("Invalid customer ID or plan ID", 400);
        }

        $b = ORM::for_table('tbl_user_recharges')->where('customer_id', $id_customer)->where('plan_id', $plan_id)->find_one();
        if ($b) {
            $p = ORM::for_table('tbl_plans')->where('id', $b['plan_id'])->find_one();
            if ($p) {
                $c = User::_info($id_customer);
                $dvc = Package::getDevice($p);
                global $_app_stage;
                if ($_app_stage != 'demo') {
                    if (file_exists($dvc)) {
                        require_once $dvc;
                        try {
                            (new $p['device'])->remove_customer($c, $p);
                        } catch (Exception $e) {
                            // ignore device removal error if device is unreachable
                        }
                    } else {
                        ApiResponse::error("Devices Not Found", 500);
                    }
                }
                
                $b->status = 'off';
                $b->expiration = date('Y-m-d');
                $b->time = date('H:i:s');
                $b->save();
                
                _log('Admin ' . $admin['username'] . ' Deactivate ' . $b['namebp'] . ' for ' . $b['username'], 'User', $b['customer_id']);
                Message::sendTelegram('Admin ' . $admin['username'] . ' Deactivate ' . $b['namebp'] . ' for u' . $b['username']);
                
                ApiResponse::success("Success deactivate customer to Mikrotik");
            } else {
                ApiResponse::error("Plan not found", 404);
            }
        } else {
            ApiResponse::error("Cannot find active plan", 404);
        }
    }
}
