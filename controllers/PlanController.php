<?php

class PlanController {
    
    // GET /plans
    public function list() {
        $plans = ORM::for_table('tbl_plans');
        
        // If not admin, list only enabled plans
        $type = ApiAuth::getUserType();
        if ($type !== 'admin') {
            $plans->where('enabled', 1);
        }
        
        $list = $plans->find_many();
        $data = [];
        foreach ($list as $p) {
            $data[] = [
                'id' => (int)$p['id'],
                'name_plan' => $p['name_plan'],
                'type' => $p['type'],
                'price' => (float)$p['price'],
                'validity' => (int)$p['validity'],
                'validity_unit' => $p['validity_unit'],
                'routers' => $p['routers'],
                'enabled' => (int)$p['enabled'],
                'is_radius' => (int)$p['is_radius'],
                'device' => $p['device']
            ];
        }

        ApiResponse::success("Plan list details", $data);
    }

    // GET /plans/{id}
    public function detail($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid plan ID", 400);
        }

        $p = ORM::for_table('tbl_plans')->find_one($id);
        if (!$p) {
            ApiResponse::error("Plan not found", 404);
        }

        ApiResponse::success("Plan details", [
            'id' => (int)$p['id'],
            'name_plan' => $p['name_plan'],
            'type' => $p['type'],
            'price' => (float)$p['price'],
            'validity' => (int)$p['validity'],
            'validity_unit' => $p['validity_unit'],
            'routers' => $p['routers'],
            'enabled' => (int)$p['enabled'],
            'is_radius' => (int)$p['is_radius'],
            'device' => $p['device'],
            'id_bw' => (int)$p['id_bw']
        ]);
    }

    // GET /bandwidths
    public function bandwidths() {
        $bws = ORM::for_table('tbl_bandwidth')->find_many();
        $data = [];
        foreach ($bws as $b) {
            $data[] = [
                'id' => (int)$b['id'],
                'name_bw' => $b['name_bw'],
                'rate_down' => (int)$b['rate_down'],
                'rate_down_unit' => $b['rate_down_unit'],
                'rate_up' => (int)$b['rate_up'],
                'rate_up_unit' => $b['rate_up_unit']
            ];
        }
        ApiResponse::success("Bandwidth list", $data);
    }

    // GET /pools
    public function pools() {
        $pools = ORM::for_table('tbl_pool')->find_many();
        $data = [];
        foreach ($pools as $p) {
            $data[] = [
                'id' => (int)$p['id'],
                'pool_name' => $p['pool_name'],
                'range_ip' => $p['range_ip'],
                'routers' => $p['routers'],
                'local_ip' => $p['local_ip']
            ];
        }
        ApiResponse::success("Pool list", $data);
    }

    // POST /bandwidths
    public function createBandwidth() {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = isset($body['name_bw']) ? trim($body['name_bw']) : '';
        $rate_down = isset($body['rate_down']) ? (int)$body['rate_down'] : 0;
        $rate_down_unit = isset($body['rate_down_unit']) ? trim($body['rate_down_unit']) : 'Mbps';
        $rate_up = isset($body['rate_up']) ? (int)$body['rate_up'] : 0;
        $rate_up_unit = isset($body['rate_up_unit']) ? trim($body['rate_up_unit']) : 'Mbps';
        
        if (empty($name)) {
            ApiResponse::error("Bandwidth name is required", 400);
        }
        
        $existing = ORM::for_table('tbl_bandwidth')->where('name_bw', $name)->find_one();
        if ($existing) {
            ApiResponse::error("Bandwidth name already exists", 409);
        }
        
        $d = ORM::for_table('tbl_bandwidth')->create();
        $d->name_bw = $name;
        $d->rate_down = $rate_down;
        $d->rate_down_unit = $rate_down_unit;
        $d->rate_up = $rate_up;
        $d->rate_up_unit = $rate_up_unit;
        $d->burst = "";
        $d->save();
        
        ApiResponse::success("Bandwidth created successfully", ['id' => (int)$d->id()]);
    }

    // PUT /bandwidths/{id}
    public function updateBandwidth($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid Bandwidth ID", 400);
        }
        
        $d = ORM::for_table('tbl_bandwidth')->find_one($id);
        if (!$d) {
            ApiResponse::error("Bandwidth not found", 404);
        }
        
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = isset($body['name_bw']) ? trim($body['name_bw']) : $d['name_bw'];
        $rate_down = isset($body['rate_down']) ? (int)$body['rate_down'] : (int)$d['rate_down'];
        $rate_down_unit = isset($body['rate_down_unit']) ? trim($body['rate_down_unit']) : $d['rate_down_unit'];
        $rate_up = isset($body['rate_up']) ? (int)$body['rate_up'] : (int)$d['rate_up'];
        $rate_up_unit = isset($body['rate_up_unit']) ? trim($body['rate_up_unit']) : $d['rate_up_unit'];
        
        if (empty($name)) {
            ApiResponse::error("Bandwidth name is required", 400);
        }
        
        if ($name !== $d['name_bw']) {
            $existing = ORM::for_table('tbl_bandwidth')->where('name_bw', $name)->where_not_equal('id', $id)->find_one();
            if ($existing) {
                ApiResponse::error("Bandwidth name already exists", 409);
            }
        }
        
        $d->name_bw = $name;
        $d->rate_down = $rate_down;
        $d->rate_down_unit = $rate_down_unit;
        $d->rate_up = $rate_up;
        $d->rate_up_unit = $rate_up_unit;
        $d->save();
        
        ApiResponse::success("Bandwidth updated successfully", null);
    }

    // DELETE /bandwidths/{id}
    public function deleteBandwidth($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid Bandwidth ID", 400);
        }
        
        $d = ORM::for_table('tbl_bandwidth')->find_one($id);
        if (!$d) {
            ApiResponse::error("Bandwidth not found", 404);
        }
        
        $d->delete();
        ApiResponse::success("Bandwidth deleted successfully", null);
    }

    // POST /pools
    public function createPool() {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = isset($body['pool_name']) ? trim($body['pool_name']) : '';
        $range_ip = isset($body['range_ip']) ? trim($body['range_ip']) : '';
        $local_ip = isset($body['local_ip']) ? trim($body['local_ip']) : '';
        $routers = isset($body['routers']) ? trim($body['routers']) : '';
        
        if (empty($name) || empty($range_ip) || empty($routers)) {
            ApiResponse::error("Pool name, range IP, and routers are required", 400);
        }
        
        $existing = ORM::for_table('tbl_pool')->where('pool_name', $name)->find_one();
        if ($existing) {
            ApiResponse::error("Pool name already exists", 409);
        }
        
        $b = ORM::for_table('tbl_pool')->create();
        $b->local_ip = $local_ip;
        $b->pool_name = $name;
        $b->range_ip = $range_ip;
        $b->routers = $routers;
        
        if ($routers !== 'radius') {
            global $DEVICE_PATH;
            require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . 'MikrotikPppoe.php';
            try {
                (new MikrotikPppoe())->add_pool($b);
            } catch (Exception $e) {
                // Ignore router connection failure during database save
            }
        }
        
        $b->save();
        ApiResponse::success("Pool created successfully", ['id' => (int)$b->id()]);
    }

    // PUT /pools/{id}
    public function updatePool($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid Pool ID", 400);
        }
        
        $d = ORM::for_table('tbl_pool')->find_one($id);
        if (!$d) {
            ApiResponse::error("Pool not found", 404);
        }
        
        $old = ORM::for_table('tbl_pool')->find_one($id);
        
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = isset($body['pool_name']) ? trim($body['pool_name']) : $d['pool_name'];
        $range_ip = isset($body['range_ip']) ? trim($body['range_ip']) : $d['range_ip'];
        $local_ip = isset($body['local_ip']) ? trim($body['local_ip']) : $d['local_ip'];
        $routers = isset($body['routers']) ? trim($body['routers']) : $d['routers'];
        
        if (empty($name) || empty($range_ip) || empty($routers)) {
            ApiResponse::error("Pool name, range IP, and routers are required", 400);
        }
        
        if ($name !== $d['pool_name']) {
            $existing = ORM::for_table('tbl_pool')->where('pool_name', $name)->where_not_equal('id', $id)->find_one();
            if ($existing) {
                ApiResponse::error("Pool name already exists", 409);
            }
        }
        
        $d->local_ip = $local_ip;
        $d->pool_name = $name;
        $d->range_ip = $range_ip;
        $d->routers = $routers;
        $d->save();
        
        if ($routers !== 'radius') {
            global $DEVICE_PATH;
            require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . 'MikrotikPppoe.php';
            try {
                (new MikrotikPppoe())->update_pool($old, $d);
            } catch (Exception $e) {
                // Ignore router connection failure during database save
            }
        }
        
        ApiResponse::success("Pool updated successfully", null);
    }

    // DELETE /pools/{id}
    public function deletePool($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid Pool ID", 400);
        }
        
        $d = ORM::for_table('tbl_pool')->find_one($id);
        if (!$d) {
            ApiResponse::error("Pool not found", 404);
        }
        
        if ($d['routers'] !== 'radius') {
            global $DEVICE_PATH;
            require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . 'MikrotikPppoe.php';
            try {
                (new MikrotikPppoe())->remove_pool($d);
            } catch (Exception $e) {
                // Ignore router connection failure during database delete
            }
        }
        
        $d->delete();
        ApiResponse::success("Pool deleted successfully", null);
    }


    // POST /plans
    public function create() {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        
        $type = isset($body['type']) ? trim($body['type']) : 'Hotspot';
        $name_plan = isset($body['name_plan']) ? trim($body['name_plan']) : '';
        $id_bw = isset($body['id_bw']) ? (int)$body['id_bw'] : 0;
        $price = isset($body['price']) ? (float)$body['price'] : 0;
        $validity = isset($body['validity']) ? (int)$body['validity'] : 0;
        $validity_unit = isset($body['validity_unit']) ? trim($body['validity_unit']) : 'Months';
        $routers = isset($body['routers']) ? trim($body['routers']) : '';
        $is_radius = isset($body['is_radius']) ? (int)$body['is_radius'] : 0;
        $enabled = isset($body['enabled']) ? (int)$body['enabled'] : 1;
        
        // PPPoE specific
        $pool = isset($body['pool']) ? trim($body['pool']) : '';
        $plan_type = isset($body['plan_type']) ? trim($body['plan_type']) : 'Personal';

        if (empty($name_plan) || $id_bw <= 0 || $price <= 0 || $validity <= 0) {
            ApiResponse::error("Name, Bandwidth, Price, and Validity are required", 400);
        }

        if ($type === 'PPPOE' && empty($pool) && !$is_radius) {
            ApiResponse::error("Pool is required for PPPoE plans", 400);
        }
        
        if (!$is_radius && empty($routers)) {
            ApiResponse::error("Router is required if not using Radius", 400);
        }

        $existing = ORM::for_table('tbl_plans')->where('name_plan', $name_plan)->find_one();
        if ($existing) {
            ApiResponse::error("Plan Name already exists", 409);
        }

        $d = ORM::for_table('tbl_plans')->create();
        $d->type = $type;
        $d->name_plan = $name_plan;
        $d->id_bw = $id_bw;
        $d->price = $price;
        $d->validity = $validity;
        $d->validity_unit = $validity_unit;
        $d->enabled = $enabled;
        $d->plan_type = $plan_type;
        $d->is_radius = $is_radius;
        if ($is_radius) {
            $d->routers = '';
            $d->device = 'Radius';
        } else {
            $d->routers = $routers;
            $d->device = ($type === 'PPPOE') ? 'MikrotikPppoe' : 'MikrotikHotspot';
        }
        if ($type === 'PPPOE') {
            $d->pool = $pool;
        } else {
            $d->typebp = isset($body['typebp']) ? trim($body['typebp']) : 'Unlimited';
            $d->limit_type = isset($body['limit_type']) ? trim($body['limit_type']) : 'Time_Limit';
            $d->time_limit = isset($body['time_limit']) ? (int)$body['time_limit'] : 0;
            $d->time_unit = isset($body['time_unit']) ? trim($body['time_unit']) : 'Hrs';
            $d->data_limit = isset($body['data_limit']) ? (int)$body['data_limit'] : 0;
            $d->data_unit = isset($body['data_unit']) ? trim($body['data_unit']) : 'MB';
            $d->shared_users = isset($body['shared_users']) ? (int)$body['shared_users'] : 1;
        }
        $d->prepaid = isset($body['prepaid']) ? trim($body['prepaid']) : 'yes';
        if ($d->prepaid == 'no') {
            $expired_date = isset($body['expired_date']) ? (int)$body['expired_date'] : 20;
            if ($expired_date > 28 || $expired_date < 1) $expired_date = 20;
            $d->expired_date = $expired_date;
        } else {
            $d->expired_date = ($type === 'Hotspot') ? 20 : 0;
        }

        $d->save();

        global $_app_stage;
        $dvc = Package::getDevice($d);
        if ($_app_stage != 'demo') {
            if (file_exists($dvc)) {
                require_once $dvc;
                (new $d['device'])->add_plan($d);
            }
        }

        ApiResponse::success("Plan created successfully", ['id' => (int)$d->id()], 201);
    }

    // PUT /plans/{id}
    public function update($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid plan ID", 400);
        }

        $d = ORM::for_table('tbl_plans')->find_one($id);
        if (!$d) {
            ApiResponse::error("Plan not found", 404);
        }
        $old = ORM::for_table('tbl_plans')->where('id', $id)->find_one();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        
        $type = isset($body['type']) ? trim($body['type']) : $d['type'];
        $name_plan = isset($body['name_plan']) ? trim($body['name_plan']) : $d['name_plan'];
        $id_bw = isset($body['id_bw']) ? (int)$body['id_bw'] : $d['id_bw'];
        $price = isset($body['price']) ? (float)$body['price'] : $d['price'];
        $validity = isset($body['validity']) ? (int)$body['validity'] : $d['validity'];
        $validity_unit = isset($body['validity_unit']) ? trim($body['validity_unit']) : $d['validity_unit'];
        $routers = isset($body['routers']) ? trim($body['routers']) : $d['routers'];
        $is_radius = isset($body['is_radius']) ? (int)$body['is_radius'] : $d['is_radius'];
        $enabled = isset($body['enabled']) ? (int)$body['enabled'] : $d['enabled'];
        
        // PPPoE specific
        $pool = isset($body['pool']) ? trim($body['pool']) : $d['pool'];
        $plan_type = isset($body['plan_type']) ? trim($body['plan_type']) : $d['plan_type'];

        if (empty($name_plan) || $id_bw <= 0 || $price <= 0 || $validity <= 0) {
            ApiResponse::error("Name, Bandwidth, Price, and Validity are required", 400);
        }

        if ($type === 'PPPOE' && empty($pool) && !$is_radius) {
            ApiResponse::error("Pool is required for PPPoE plans", 400);
        }
        
        if (!$is_radius && empty($routers)) {
            ApiResponse::error("Router is required if not using Radius", 400);
        }

        if ($name_plan !== $d['name_plan']) {
            $existing = ORM::for_table('tbl_plans')->where('name_plan', $name_plan)->find_one();
            if ($existing) {
                ApiResponse::error("Plan Name already exists", 409);
            }
        }

        $d->type = $type;
        $d->name_plan = $name_plan;
        $d->id_bw = $id_bw;
        $d->price = $price;
        $d->validity = $validity;
        $d->validity_unit = $validity_unit;
        $d->enabled = $enabled;
        $d->plan_type = $plan_type;
        $d->is_radius = $is_radius;
        if ($is_radius) {
            $d->routers = '';
            $d->device = 'Radius';
        } else {
            $d->routers = $routers;
            $d->device = ($type === 'PPPOE') ? 'MikrotikPppoe' : 'MikrotikHotspot';
        }
        if ($type === 'PPPOE') {
            $d->pool = $pool;
        } else {
            $d->typebp = isset($body['typebp']) ? trim($body['typebp']) : $d['typebp'];
            $d->limit_type = isset($body['limit_type']) ? trim($body['limit_type']) : $d['limit_type'];
            $d->time_limit = isset($body['time_limit']) ? (int)$body['time_limit'] : $d['time_limit'];
            $d->time_unit = isset($body['time_unit']) ? trim($body['time_unit']) : $d['time_unit'];
            $d->data_limit = isset($body['data_limit']) ? (int)$body['data_limit'] : $d['data_limit'];
            $d->data_unit = isset($body['data_unit']) ? trim($body['data_unit']) : $d['data_unit'];
            $d->shared_users = isset($body['shared_users']) ? (int)$body['shared_users'] : $d['shared_users'];
        }
        $d->prepaid = isset($body['prepaid']) ? trim($body['prepaid']) : $d['prepaid'];
        if ($d->prepaid == 'no') {
            $expired_date = isset($body['expired_date']) ? (int)$body['expired_date'] : 20;
            if ($expired_date > 28 || $expired_date < 1) $expired_date = 20;
            $d->expired_date = $expired_date;
        } else {
            $d->expired_date = ($type === 'Hotspot') ? 20 : 0;
        }

        $d->save();

        global $_app_stage;
        $dvc = Package::getDevice($d);
        if ($_app_stage != 'demo') {
            if (file_exists($dvc)) {
                require_once $dvc;
                (new $d['device'])->update_plan($old, $d);
            }
        }

        ApiResponse::success("Plan updated successfully", null);
    }

    // DELETE /plans/{id}
    public function delete($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid plan ID", 400);
        }

        $d = ORM::for_table('tbl_plans')->find_one($id);
        if (!$d) {
            ApiResponse::error("Plan not found", 404);
        }

        global $_app_stage;
        $dvc = Package::getDevice($d);
        if ($_app_stage != 'demo') {
            if (file_exists($dvc)) {
                require_once $dvc;
                try {
                    (new $d['device'])->remove_plan($d);
                } catch(Exception $e){}
            }
        }
        $d->delete();

        ApiResponse::success("Plan deleted successfully", null);
    }

    // POST /plans/recharge (Admin only)
    public function recharge() {
        global $config;
        $admin = ApiAuth::getUser();

        $id_customer = isset($_POST['id_customer']) ? (int)$_POST['id_customer'] : 0;
        $server = isset($_POST['server']) ? trim($_POST['server']) : ''; // router name
        $planId = isset($_POST['plan']) ? (int)$_POST['plan'] : 0;
        $using = isset($_POST['using']) ? trim($_POST['using']) : ''; // payment method

        if (empty($id_customer) || empty($server) || empty($planId) || empty($using)) {
            ApiResponse::error("id_customer, server, plan, and using are required fields", 400);
        }

        $cust = ORM::for_table('tbl_customers')->find_one($id_customer);
        if (!$cust) {
            ApiResponse::error("Customer not found", 404);
        }

        $plan = ORM::for_table('tbl_plans')->find_one($planId);
        if (!$plan) {
            ApiResponse::error("Plan not found", 404);
        }

        list($bills, $add_cost) = User::getBills($id_customer);
        $add_inv = User::getAttribute("Invoice", $id_customer);
        if (!empty($add_inv)) {
            $plan['price'] = $add_inv;
        }

        // Tax calculation
        $tax_enable = isset($config['enable_tax']) ? $config['enable_tax'] : 'no';
        $tax_rate_setting = isset($config['tax_rate']) ? $config['tax_rate'] : null;
        $custom_tax_rate = isset($config['custom_tax_rate']) ? (float)$config['custom_tax_rate'] : null;

        $tax_rate = ($tax_rate_setting === 'custom') ? $custom_tax_rate : $tax_rate_setting;
        $tax = ($tax_enable === 'yes') ? Package::tax($plan['price'], $tax_rate) : 0;
        $total_cost = $plan['price'] + $add_cost + $tax;

        $gateway = ucwords($using);
        $channel = $admin['fullname'];

        if ($using === 'balance') {
            if (isset($config['enable_balance']) && $config['enable_balance'] === 'yes') {
                if ($cust['balance'] < $total_cost) {
                    ApiResponse::error("Insufficient balance. Total cost is: " . $total_cost, 400);
                }
                $gateway = 'Recharge Balance';
            } else {
                ApiResponse::error("Balance payment is disabled", 400);
            }
        } else if ($using === 'zero') {
            $add_cost = 0;
            $gateway = 'Recharge Zero';
        }

        if (Package::rechargeUser($id_customer, $server, $planId, $gateway, $channel)) {
            if ($using === 'balance') {
                Balance::min($cust['id'], $total_cost);
            }
            $in = ORM::for_table('tbl_transactions')->where('username', $cust['username'])->order_by_desc('id')->find_one();
            //Package::createInvoice($in);
            $this->createInvoiceApi($in);
            
            _log('[' . $admin['username'] . ']: ' . 'Recharge ' . $cust['username'] . ' [' . $in['plan_name'] . '][' . Lang::moneyFormat($in['price']) . '] (API)', $admin['user_type'], $admin['id']);

            ApiResponse::success("Recharge successful", [
                'invoice' => [
                    'id' => (int)$in['id'],
                    'invoice_number' => $in['invoice'],
                    'customer_id' => (int)$in['user_id'],
                    'username' => $in['username'],
                    'plan_name' => $in['plan_name'],
                    'price' => (float)$in['price'],
                    'recharged_on' => $in['recharged_on'],
                    'recharged_time' => $in['recharged_time'],
                    'expiration' => $in['expiration'],
                    'time' => $in['time'],
                    'method' => $in['method'],
                    'routers' => $in['routers'],
                    'type' => $in['type']
                ]
            ]);
        } else {
            ApiResponse::error("Failed to recharge account", 500);
        }
    }
    private function createInvoiceApi($in)
{
    global $config, $admin;

    $date = Lang::dateAndTimeFormat(
        $in['recharged_on'],
        $in['recharged_time']
    );

    if ($admin['id'] != $in['admin_id'] && $in['admin_id'] > 0) {
        $_admin = Admin::_info($in['admin_id']);
        if ($_admin) {
            $admin = $_admin;
        }
    } else {
        $admin['fullname'] = 'Customer';
    }

    $cust = ORM::for_table('tbl_customers')
        ->where('username', $in['username'])
        ->find_one();


    $invoice = "";

    $invoice .= Lang::pad($config['CompanyName'], ' ', 2)."\n";
    $invoice .= Lang::pad($config['address'], ' ', 2)."\n";
    $invoice .= Lang::pad($config['phone'], ' ', 2)."\n";
    $invoice .= Lang::pad("", '=') . "\n";

    $invoice .= Lang::pads("Invoice", $in['invoice'], ' ') . "\n";
    $invoice .= Lang::pads(
        Lang::T('Date'),
        $date,
        ' '
    )."\n";

    $invoice .= Lang::pads(
        Lang::T('Sales'),
        $admin['fullname'],
        ' '
    )."\n";

    $invoice .= Lang::pad("", '=') . "\n";

    $invoice .= Lang::pads(
        Lang::T('Plan Name'),
        $in['plan_name'],
        ' '
    )."\n";


    $invoice .= Lang::pads(
        Lang::T('Total'),
        Lang::moneyFormat($in['price']),
        ' '
    )."\n";


    if ($cust) {
        $invoice .= Lang::pads(
            Lang::T('Full Name'),
            $cust['fullname'],
            ' '
        )."\n";
    }


    $invoice .= Lang::pads(
        Lang::T('Username'),
        $in['username'],
        ' '
    )."\n";


    return $invoice;
}
}
