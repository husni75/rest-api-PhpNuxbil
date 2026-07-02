<?php

class RadiusController {
    
    private function checkRadius() {
        global $config;
        if (empty($config['radius_enable']) || !$config['radius_enable']) {
            ApiResponse::error("Radius is not enabled in settings", 400);
        }
    }

    // GET /radius/nas
    public function list() {
        $this->checkRadius();
        
        $nas = ORM::for_table('nas', 'radius')->find_many();
        $data = [];
        foreach ($nas as $n) {
            $data[] = [
                'id' => (int)$n['id'],
                'nasname' => $n['nasname'],
                'shortname' => $n['shortname'],
                'type' => $n['type'],
                'ports' => $n['ports'] !== null ? (int)$n['ports'] : null,
                'secret' => $n['secret'],
                'description' => $n['description'],
                'server' => $n['server'],
                'community' => $n['community'],
                'routers' => $n['routers']
            ];
        }

        ApiResponse::success("NAS list", $data);
    }

    // GET /radius/nas/{id}
    public function detail($params) {
        $this->checkRadius();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid NAS ID", 400);
        }

        $n = ORM::for_table('nas', 'radius')->find_one($id);
        if (!$n) {
            ApiResponse::error("NAS not found", 404);
        }

        ApiResponse::success("NAS details", [
            'id' => (int)$n['id'],
            'nasname' => $n['nasname'],
            'shortname' => $n['shortname'],
            'type' => $n['type'],
            'ports' => $n['ports'] !== null ? (int)$n['ports'] : null,
            'secret' => $n['secret'],
            'description' => $n['description'],
            'server' => $n['server'],
            'community' => $n['community'],
            'routers' => $n['routers']
        ]);
    }

    // POST /radius/nas
    public function create() {
        $this->checkRadius();
        
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $shortname   = isset($body['shortname'])   ? trim($body['shortname'])   : '';
        $nasname     = isset($body['nasname'])     ? trim($body['nasname'])     : '';
        $secret      = isset($body['secret'])      ? trim($body['secret'])      : '';
        $ports       = isset($body['ports']) && $body['ports'] !== '' ? (int)$body['ports'] : null;
        $type        = isset($body['type'])        ? trim($body['type'])        : 'other';
        $server      = isset($body['server'])      ? trim($body['server'])      : null;
        $community   = isset($body['community'])   ? trim($body['community'])   : null;
        $description = isset($body['description']) ? trim($body['description']) : '';
        $routers     = isset($body['routers'])     ? trim($body['routers'])     : '';

        if (empty($shortname) || empty($nasname) || empty($secret)) {
            ApiResponse::error("Shortname, NAS IP/Name, and Secret are required", 400);
        }

        // Check if NAS name already exists
        $existing = ORM::for_table('nas', 'radius')->where('nasname', $nasname)->find_one();
        if ($existing) {
            ApiResponse::error("NAS IP Address / Name already exists", 409);
        }

        global $DEVICE_PATH;
        require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . "Radius.php";
        $radius = new Radius();
        
        $nasId = $radius->nasAdd($shortname, $nasname, $ports, $secret, $routers, $description, $type, $server, $community);
        if ($nasId > 0) {
            ApiResponse::success("NAS created successfully", ['id' => (int)$nasId], [], 201);
        } else {
            ApiResponse::error("Failed to create NAS", 500);
        }
    }

    // PUT /radius/nas/{id}
    public function update($params) {
        $this->checkRadius();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid NAS ID", 400);
        }

        $n = ORM::for_table('nas', 'radius')->find_one($id);
        if (!$n) {
            ApiResponse::error("NAS not found", 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $shortname   = isset($body['shortname'])   ? trim($body['shortname'])   : $n['shortname'];
        $nasname     = isset($body['nasname'])     ? trim($body['nasname'])     : $n['nasname'];
        $secret      = isset($body['secret'])      ? trim($body['secret'])      : $n['secret'];
        $ports       = isset($body['ports']) && $body['ports'] !== '' ? (int)$body['ports'] : null;
        $type        = isset($body['type'])        ? trim($body['type'])        : $n['type'];
        $server      = isset($body['server'])      ? trim($body['server'])      : $n['server'];
        $community   = isset($body['community'])   ? trim($body['community'])   : $n['community'];
        $description = isset($body['description']) ? trim($body['description']) : $n['description'];
        $routers     = isset($body['routers'])     ? trim($body['routers'])     : $n['routers'];

        if (empty($shortname) || empty($nasname) || empty($secret)) {
            ApiResponse::error("Shortname, NAS IP/Name, and Secret are required", 400);
        }

        // Check unique nasname (excluding this id)
        if ($nasname !== $n['nasname']) {
            $existing = ORM::for_table('nas', 'radius')->where('nasname', $nasname)->where_not_equal('id', $id)->find_one();
            if ($existing) {
                ApiResponse::error("NAS IP Address / Name already exists", 409);
            }
        }

        global $DEVICE_PATH;
        require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . "Radius.php";
        $radius = new Radius();
        
        $success = $radius->nasUpdate($id, $shortname, $nasname, $ports, $secret, $routers, $description, $type, $server, $community);
        if ($success) {
            ApiResponse::success("NAS updated successfully", null);
        } else {
            ApiResponse::error("Failed to update NAS or no changes made", 500);
        }
    }

    // DELETE /radius/nas/{id}
    public function delete($params) {
        $this->checkRadius();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid NAS ID", 400);
        }

        $n = ORM::for_table('nas', 'radius')->find_one($id);
        if (!$n) {
            ApiResponse::error("NAS not found", 404);
        }

        $n->delete();
        ApiResponse::success("NAS deleted successfully", null);
    }
}
