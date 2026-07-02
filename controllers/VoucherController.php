<?php

class VoucherController {
    
    // GET /vouchers (Admin only)
    public function list() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 10;
        $offset = ($page - 1) * $limit;

        $router = isset($_GET['router']) ? trim($_GET['router']) : '';
        $plan = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
        $status = isset($_GET['status']) ? trim($_GET['status']) : ''; // '0' or '1'
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';

        $query = ORM::for_table('tbl_voucher')
            ->select('tbl_voucher.*')
            ->select('tbl_plans.name_plan', 'name_plan')
            ->left_outer_join('tbl_plans', ['tbl_plans.id', '=', 'tbl_voucher.id_plan']);

        if (!empty($router)) {
            $query->where('tbl_voucher.routers', $router);
        }
        if ($status === '1' || $status === '0') {
            $query->where('tbl_voucher.status', $status);
        }
        if (!empty($plan)) {
            $query->where('tbl_voucher.id_plan', $plan);
        }
        if (!empty($customer)) {
            $query->where('tbl_voucher.user', $customer);
        }
        if (!empty($search)) {
            $query->where_like('tbl_voucher.code', '%' . $search . '%');
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $vouchers = $query->limit($limit)->offset($offset)->order_by_desc('tbl_voucher.id')->find_many();

        $data = [];
        foreach ($vouchers as $v) {
            $data[] = [
                'id' => (int)$v['id'],
                'type' => $v['type'],
                'routers' => $v['routers'],
                'id_plan' => (int)$v['id_plan'],
                'name_plan' => $v['name_plan'],
                'code' => $v['code'],
                'user' => $v['user'],
                'status' => (int)$v['status'],
                'used_date' => $v['used_date'],
                'created_at' => $v['created_at'],
                'generated_by' => (int)$v['generated_by']
            ];
        }

        ApiResponse::success("Voucher list", $data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]);
    }

    // POST /vouchers/generate (Admin only)
    public function generate() {
        $admin = ApiAuth::getUser();

        $type = isset($_POST['type']) ? trim($_POST['type']) : ''; // 'Hotspot' or 'PPPoE'
        $plan = isset($_POST['plan']) ? (int)$_POST['plan'] : 0;
        $voucher_format = isset($_POST['voucher_format']) ? trim($_POST['voucher_format']) : 'rand'; // 'numbers', 'low', 'rand'
        $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : '';
        $server = isset($_POST['server']) ? trim($_POST['server']) : ''; // router name
        $numbervoucher = isset($_POST['numbervoucher']) ? (int)$_POST['numbervoucher'] : 0;
        $lengthcode = isset($_POST['lengthcode']) ? (int)$_POST['lengthcode'] : 12;

        if (empty($type) || empty($plan) || empty($server) || empty($numbervoucher)) {
            ApiResponse::error("type, plan, server, and numbervoucher are required", 400);
        }

        // Validate plan
        $p = ORM::for_table('tbl_plans')->find_one($plan);
        if (!$p) {
            ApiResponse::error("Plan not found", 404);
        }

        // Save voucher prefix setting in appconfig if provided
        if (!empty($prefix)) {
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'voucher_prefix')->find_one();
            if ($d) {
                $d->value = $prefix;
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'voucher_prefix';
                $d->value = $prefix;
                $d->save();
            }
        }

        $vouchers = [];
        if ($voucher_format === 'numbers') {
            if ($lengthcode < 6) {
                ApiResponse::error("Length code must be at least 6 for numbers", 400);
            }
            $vouchers = generateUniqueNumericVouchers($numbervoucher, $lengthcode);
        } else {
            for ($i = 0; $i < $numbervoucher; $i++) {
                $code = strtoupper(substr(md5(time() . rand(10000, 99999)), 0, $lengthcode));
                if ($voucher_format === 'low') {
                    $code = strtolower($code);
                } else if ($voucher_format === 'rand') {
                    $code = Lang::randomUpLowCase($code);
                }
                $vouchers[] = $code;
            }
        }

        $generatedVouchers = [];
        foreach ($vouchers as $code) {
            $d = ORM::for_table('tbl_voucher')->create();
            $d->type = $type;
            $d->routers = $server;
            $d->id_plan = $plan;
            $d->code = "$prefix$code";
            $d->user = '0';
            $d->status = '0';
            $d->generated_by = $admin['id'];
            $d->created_at = date('Y-m-d H:i:s');
            $d->save();

            $generatedVouchers[] = [
                'id' => (int)$d->id(),
                'code' => $d->code,
                'price' => (float)$p['price'],
                'plan_name' => $p['name_plan']
            ];
        }

        ApiResponse::success(count($generatedVouchers) . " vouchers generated successfully", $generatedVouchers, [], 201);
    }

    // POST /vouchers/activate (Any authenticated user)
    public function activate() {
        $user = ApiAuth::getUser();
        $userType = ApiAuth::getUserType();

        $code = isset($_POST['code']) ? trim($_POST['code']) : '';
        
        if (empty($code)) {
            ApiResponse::error("Voucher code is required", 400);
        }

        // 1. Resolve Customer ID
        $customerId = 0;
        if ($userType === 'customer') {
            $customerId = (int)$user['id'];
        } else if ($userType === 'admin') {
            // Admin can pass customer_id or username
            if (isset($_POST['customer_id'])) {
                $customerId = (int)$_POST['customer_id'];
            } else if (isset($_POST['username'])) {
                $cust = ORM::for_table('tbl_customers')->where('username', trim($_POST['username']))->find_one();
                if ($cust) {
                    $customerId = (int)$cust['id'];
                }
            }
        }

        if ($customerId <= 0) {
            ApiResponse::error("A valid customer_id or username is required for activation", 400);
        }

        $cust = ORM::for_table('tbl_customers')->find_one($customerId);
        if (!$cust) {
            ApiResponse::error("Customer not found", 404);
        }

        // 2. Validate Voucher
        $v = ORM::for_table('tbl_voucher')->whereRaw("BINARY code = '$code'")->find_one();
        if (!$v) {
            ApiResponse::error("Voucher code is invalid", 400);
        }

        if ($v['status'] != 0) {
            ApiResponse::error("Voucher is already used", 400);
        }

        // 3. Perform Recharge
        $oldPass = $cust['password'];
        // Update password to voucher code (standard behavior in phpnuxbill)
        $cust->password = $v['code'];
        $cust->save();

        if (Package::rechargeUser($cust['id'], $v['routers'], $v['id_plan'], "Voucher", $v['code'])) {
            $v->status = "1";
            $v->used_date = date('Y-m-d H:i:s');
            $v->user = $cust['username'];
            $v->save();

            $cust->last_login = date('Y-m-d H:i:s');
            $cust->save();

            // Create Invoice
            $in = ORM::for_table('tbl_transactions')->where('username', $cust['username'])->order_by_desc('id')->find_one();
            if ($in) {
                Package::createInvoice($in);
            }

            _log('Voucher ' . $v['code'] . ' activated for ' . $cust['username'], $userType, $user['id']);

            ApiResponse::success("Voucher activated successfully", [
                'plan_name' => $in ? $in['plan_name'] : '',
                'price' => $in ? (float)$in['price'] : 0.0,
                'expiration' => $in ? $in['expiration'] : ''
            ]);
        } else {
            // Restore password
            $cust->password = $oldPass;
            $cust->save();
            ApiResponse::error("Failed to activate voucher", 500);
        }
    }
}
