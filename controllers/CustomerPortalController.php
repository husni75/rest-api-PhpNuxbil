<?php

class CustomerPortalController {
    
    // GET /customer/profile
    public function profile() {
        $user = ApiAuth::getUser();
        ApiResponse::success("Customer profile", [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'],
            'email' => $user['email'],
            'phonenumber' => $user['phonenumber'],
            'address' => $user['address'],
            'balance' => (float)$user['balance'],
            'status' => $user['status'],
            'service_type' => $user['service_type'],
            'account_type' => $user['account_type'],
            'city' => $user['city'],
            'district' => $user['district'],
            'state' => $user['state'],
            'zip' => $user['zip'],
            'photo' => $user['photo'],
            'coordinates' => $user['coordinates'],
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login']
        ]);
    }

    // PUT /customer/profile
    public function updateProfile() {
        global $_app_stage;
        $user = ApiAuth::getUser();

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
        $phonenumber = isset($input['phonenumber']) ? Lang::phoneFormat(trim($input['phonenumber'])) : '';
        $address = isset($input['address']) ? trim($input['address']) : '';

        if (!empty($fullname)) $user->fullname = $fullname;
        if (!empty($email)) $user->email = $email;
        if (!empty($phonenumber)) $user->phonenumber = $phonenumber;
        if (!empty($address)) $user->address = $address;

        $newPassword = '';
        $passwordChanged = false;
        if (isset($input['password']) && !empty($input['password'])) {
            $newPassword = trim($input['password']);
            $user->password = $newPassword;
            $passwordChanged = true;
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
            // Sync password to Mikrotik device if password was changed
            if ($passwordChanged && $_app_stage != 'demo') {
                // Log: customer ubah password sendiri via Android App
                _log('[Android App] ' . $user['username'] . ' mengubah password akun', 'Customer', $user['id']);

                $turs = ORM::for_table('tbl_user_recharges')
                    ->where('customer_id', $user['id'])
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

                        if ($p['device'] === 'MikrotikHotspot') {
                            // Update password hotspot di Mikrotik
                            $deviceInstance->setHotspotUser($client, $user['username'], $newPassword);
                            // Disconnect session aktif agar user login ulang dengan password baru
                            $deviceInstance->removeHotspotActiveUser($client, $user['username']);
                            _log('[Android App] ' . $user['username'] . ' password Hotspot Mikrotik [' . $p['routers'] . '] berhasil diperbarui & sesi aktif di-disconnect', 'Customer', $user['id']);

                        } elseif ($p['device'] === 'MikrotikPppoe') {
                            // Tentukan username PPPoE yang dipakai
                            $pppoeUser = !empty($user['pppoe_username']) ? $user['pppoe_username'] : $user['username'];
                            $pppoePass = !empty($user['pppoe_password']) ? $user['pppoe_password'] : $newPassword;

                            // Cari ID secret PPPoE di Mikrotik
                            $printRequest = new \PEAR2\Net\RouterOS\Request('/ppp/secret/print');
                            $printRequest->setQuery(\PEAR2\Net\RouterOS\Query::where('name', $pppoeUser));
                            $cid = $client->sendSync($printRequest)->getProperty('.id');

                            if (!empty($cid)) {
                                // Update password PPPoE secret
                                $setRequest = new \PEAR2\Net\RouterOS\Request('/ppp/secret/set');
                                $setRequest->setArgument('numbers', $cid);
                                $setRequest->setArgument('password', $newPassword);
                                $client->sendSync($setRequest);
                            }

                            // Disconnect session PPPoE aktif agar reconnect dengan password baru
                            $deviceInstance->removePpoeActive($client, $pppoeUser);
                            if (!empty($user['pppoe_username']) && $user['pppoe_username'] !== $user['username']) {
                                $deviceInstance->removePpoeActive($client, $user['username']);
                            }
                            _log('[Android App] ' . $user['username'] . ' password PPPoE Mikrotik [' . $p['routers'] . '] berhasil diperbarui & sesi aktif di-disconnect', 'Customer', $user['id']);
                        }

                    } catch (Exception $e) {
                        _log('[Android App] Gagal sync password ke Mikrotik [' . $p['routers'] . '] untuk ' . $user['username'] . ': ' . $e->getMessage(), 'Customer', $user['id']);
                    }
                }
            }

            ApiResponse::success("Profile updated successfully");
        } else {
            ApiResponse::error("Failed to update profile", 500);
        }
    }

    // GET /customer/packages
    public function packages() {
        $user = ApiAuth::getUser();
        
        $packagesRaw = User::_billing($user['id']);
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

        ApiResponse::success("Active packages details", $data);
    }

    // GET /customer/transactions
    public function transactions() {
        $user = ApiAuth::getUser();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 10;
        $offset = ($page - 1) * $limit;

        $query = ORM::for_table('tbl_transactions')
            ->where('user_id', $user['id']);

        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $transactions = $query->limit($limit)->offset($offset)->order_by_desc('id')->find_many();

        $data = [];
        foreach ($transactions as $t) {
            $data[] = [
                'id' => (int)$t['id'],
                'invoice' => $t['invoice'],
                'username' => $t['username'],
                'plan_name' => $t['plan_name'],
                'price' => (float)$t['price'],
                'recharged_on' => $t['recharged_on'],
                'recharged_time' => $t['recharged_time'],
                'expiration' => $t['expiration'],
                'time' => $t['time'],
                'method' => $t['method'],
                'routers' => $t['routers'],
                'type' => $t['type']
            ];
        }

        ApiResponse::success("Transaction history details", $data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]);
    }

    // POST /customer/voucher/activate
    public function activateVoucher() {
        $user = ApiAuth::getUser();
        $code = isset($_POST['code']) ? trim($_POST['code']) : '';
        
        if (empty($code)) {
            ApiResponse::error("Voucher code is required", 400);
        }

        if ($user['status'] !== 'Active') {
            ApiResponse::error("This account is not active (" . $user['status'] . ")", 403);
        }

        $v = ORM::for_table('tbl_voucher')->whereRaw("BINARY code = '$code'")->find_one();
        if (!$v) {
            ApiResponse::error("Voucher code is invalid", 400);
        }

        if ($v['status'] != 0) {
            ApiResponse::error("Voucher is already used", 400);
        }

        $oldPass = $user['password'];
        $user->password = $v['code'];
        $user->save();

        if (Package::rechargeUser($user['id'], $v['routers'], $v['id_plan'], "Voucher", $v['code'])) {
            $v->status = "1";
            $v->used_date = date('Y-m-d H:i:s');
            $v->user = $user['username'];
            $v->save();

            $user->last_login = date('Y-m-d H:i:s');
            $user->save();

            $in = ORM::for_table('tbl_transactions')->where('username', $user['username'])->order_by_desc('id')->find_one();
            if ($in) {
                Package::createInvoice($in);
            }

            _log('Voucher ' . $v['code'] . ' activated for ' . $user['username'] . ' (API Portal)', 'Customer', $user['id']);

            ApiResponse::success("Voucher activated successfully", [
                'plan_name' => $in ? $in['plan_name'] : '',
                'price' => $in ? (float)$in['price'] : 0.0,
                'expiration' => $in ? $in['expiration'] : ''
            ]);
        } else {
            $user->password = $oldPass;
            $user->save();
            ApiResponse::error("Failed to activate voucher", 500);
        }
    }

    // GET /customer/balance
    public function balance() {
        $user = ApiAuth::getUser();
        ApiResponse::success("Customer balance details", [
            'balance' => (float)$user['balance']
        ]);
    }

    // POST /customer/balance/transfer
    public function balanceTransfer() {
        global $config;
        $user = ApiAuth::getUser();

        if ($config['enable_balance'] !== 'yes' || $config['allow_balance_transfer'] !== 'yes') {
            ApiResponse::error("Balance transfer is not allowed or enabled", 403);
        }

        if ($user['status'] !== 'Active') {
            ApiResponse::error("This account is not active (" . $user['status'] . ")", 403);
        }

        $targetUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
        $amount = isset($_POST['balance']) ? (float)$_POST['balance'] : 0.0;

        if (empty($targetUsername) || $amount <= 0) {
            ApiResponse::error("username and transfer balance amount are required", 400);
        }

        if ($user['username'] === $targetUsername) {
            ApiResponse::error("You cannot transfer balance to yourself", 400);
        }

        $target = ORM::for_table('tbl_customers')->where('username', $targetUsername)->find_one();
        if (!$target) {
            ApiResponse::error("Recipient username not found", 404);
        }

        if ($user['balance'] < $amount) {
            ApiResponse::error("Insufficient balance. Your current balance is " . $user['balance'], 400);
        }

        if (!empty($config['minimum_transfer']) && $amount < (float)$config['minimum_transfer']) {
            ApiResponse::error("Minimum transfer amount is " . Lang::moneyFormat($config['minimum_transfer']), 400);
        }

        if (Balance::transfer($user['id'], $targetUsername, $amount)) {
            // sender log
            $d1 = ORM::for_table('tbl_payment_gateway')->create();
            $d1->username = $user['username'];
            $d1->gateway = $target['username'];
            $d1->plan_id = 0;
            $d1->plan_name = 'Send Balance';
            $d1->routers_id = 0;
            $d1->routers = 'balance';
            $d1->price = $amount;
            $d1->payment_method = "Customer";
            $d1->payment_channel = "Balance";
            $d1->created_date = date('Y-m-d H:i:s');
            $d1->paid_date = date('Y-m-d H:i:s');
            $d1->expired_date = date('Y-m-d H:i:s');
            $d1->pg_url_payment = 'balance';
            $d1->status = 2;
            $d1->save();

            // receiver log
            $d2 = ORM::for_table('tbl_payment_gateway')->create();
            $d2->username = $target['username'];
            $d2->gateway = $user['username'];
            $d2->plan_id = 0;
            $d2->plan_name = 'Receive Balance';
            $d2->routers_id = 0;
            $d2->routers = 'balance';
            $d2->price = $amount;
            $d2->payment_method = "Customer";
            $d2->payment_channel = "Balance";
            $d2->created_date = date('Y-m-d H:i:s');
            $d2->paid_date = date('Y-m-d H:i:s');
            $d2->expired_date = date('Y-m-d H:i:s');
            $d2->pg_url_payment = 'balance';
            $d2->status = 2;
            $d2->save();

            Message::sendBalanceNotification($user, $target, $amount, ($user['balance'] - $amount), Lang::getNotifText('balance_send'), $config['user_notification_payment']);
            Message::sendBalanceNotification($target, $user, $amount, ($target['balance'] + $amount), Lang::getNotifText('balance_received'), $config['user_notification_payment']);
            Message::sendTelegram("#u$user[username] send balance to #u$target[username] \n" . Lang::moneyFormat($amount));

            ApiResponse::success("Balance transferred successfully", [
                'sender_remaining_balance' => (float)($user['balance'] - $amount),
                'transferred_amount' => $amount
            ]);
        } else {
            ApiResponse::error("Balance transfer failed", 500);
        }
    }
}
