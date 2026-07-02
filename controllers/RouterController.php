<?php

class RouterController {
    
    // GET /routers
    public function list() {
        $routers = ORM::for_table('tbl_routers')->order_by_desc('id')->find_many();
        
        $data = [];
        foreach ($routers as $r) {
            $data[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'ip_address' => $r['ip_address'],
                'username' => $r['username'],
                'password' => $r['password'],
                'description' => $r['description'],
                'coordinates' => $r['coordinates'],
                'coverage' => $r['coverage'],
                'enabled' => (int)$r['enabled']
            ];
        }

        ApiResponse::success("Router list", $data);
    }

    // GET /routers/{id}
    public function detail($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid router ID", 400);
        }

        $r = ORM::for_table('tbl_routers')->find_one($id);
        if (!$r) {
            ApiResponse::error("Router not found", 404);
        }

        ApiResponse::success("Router details", [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'ip_address' => $r['ip_address'],
            'username' => $r['username'],
            'password' => $r['password'],
            'description' => $r['description'],
            'coordinates' => $r['coordinates'],
            'coverage' => $r['coverage'],
            'enabled' => (int)$r['enabled']
        ]);
    }

    // POST /routers
    public function create($params) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $name        = isset($body['name'])        ? trim($body['name'])        : '';
        $ip_address  = isset($body['ip_address'])  ? trim($body['ip_address'])  : '';
        $username    = isset($body['username'])    ? trim($body['username'])    : '';
        $password    = isset($body['password'])    ? $body['password']          : '';
        $description = isset($body['description']) ? trim($body['description']) : '';
        $enabled     = isset($body['enabled'])     ? (int)$body['enabled']      : 0;

        if (strlen($name) < 1 || strlen($name) > 30) {
            ApiResponse::error("Name should be between 1 to 30 characters", 422);
        }
        if (strtolower($name) === 'radius') {
            ApiResponse::error("'radius' name is reserved", 422);
        }
        if ($enabled) {
            if ($ip_address === '' || $username === '') {
                ApiResponse::error("IP address and username are required when router is enabled", 422);
            }
            $existing = ORM::for_table('tbl_routers')->where('ip_address', $ip_address)->find_one();
            if ($existing) {
                ApiResponse::error("IP Router already exists", 409);
            }
        }

        $d = ORM::for_table('tbl_routers')->create();
        $d->name        = $name;
        $d->ip_address  = $ip_address;
        $d->username    = $username;
        $d->password    = $password;
        $d->description = $description;
        $d->enabled     = $enabled;
        $d->save();

        ApiResponse::success("Router created successfully", ['id' => (int)$d->id()], 201);
    }

    // PUT /routers/{id}
    public function update($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid router ID", 400);
        }

        $r = ORM::for_table('tbl_routers')->find_one($id);
        if (!$r) {
            ApiResponse::error("Router not found", 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $name        = isset($body['name'])        ? trim($body['name'])        : $r['name'];
        $ip_address  = isset($body['ip_address'])  ? trim($body['ip_address'])  : $r['ip_address'];
        $username    = isset($body['username'])    ? trim($body['username'])    : $r['username'];
        $password    = isset($body['password'])    ? $body['password']          : $r['password'];
        $description = isset($body['description']) ? trim($body['description']) : $r['description'];
        $coordinates = isset($body['coordinates']) ? trim($body['coordinates']) : $r['coordinates'];
        $coverage    = isset($body['coverage'])    ? trim($body['coverage'])    : $r['coverage'];
        $enabled     = array_key_exists('enabled', $body) ? (int)$body['enabled'] : (int)$r['enabled'];

        if (strlen($name) < 1 || strlen($name) > 30) {
            ApiResponse::error("Name should be between 1 to 30 characters", 422);
        }
        if (strtolower($name) === 'radius') {
            ApiResponse::error("'radius' name is reserved", 422);
        }

        // Check name uniqueness (excluding this router)
        if ($r['name'] !== $name) {
            $dup = ORM::for_table('tbl_routers')->where('name', $name)->where_not_equal('id', $id)->find_one();
            if ($dup) {
                ApiResponse::error("Router name already exists", 409);
            }
        }
        // Check IP uniqueness (excluding this router) when enabled
        if ($enabled && $r['ip_address'] !== $ip_address) {
            $dup = ORM::for_table('tbl_routers')->where('ip_address', $ip_address)->where_not_equal('id', $id)->find_one();
            if ($dup) {
                ApiResponse::error("IP address already exists", 409);
            }
        }

        $oldname = $r['name'];
        $r->name        = $name;
        $r->ip_address  = $ip_address;
        $r->username    = $username;
        $r->password    = $password;
        $r->description = $description;
        $r->coordinates = $coordinates;
        $r->coverage    = $coverage;
        $r->enabled     = $enabled;
        $r->save();

        // Cascade router name change to related tables
        if ($name !== $oldname) {
            foreach (['tbl_plans', 'tbl_payment_gateway', 'tbl_pool', 'tbl_transactions', 'tbl_user_recharges', 'tbl_voucher'] as $tbl) {
                $p = ORM::for_table($tbl)->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
            }
        }

        ApiResponse::success("Router updated successfully", null);
    }

    // DELETE /routers/{id}
    public function delete($params) {
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid router ID", 400);
        }

        $r = ORM::for_table('tbl_routers')->find_one($id);
        if (!$r) {
            ApiResponse::error("Router not found", 404);
        }

        $r->delete();
        ApiResponse::success("Router deleted successfully", null);
    }
}
