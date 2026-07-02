<?php

class AdminUserController {
    private $roles = ['SuperAdmin', 'Admin', 'Report', 'Agent', 'Sales'];

    private function serialize($user) {
        return [
            'id' => (int)$user['id'],
            'root' => (int)$user['root'],
            'photo' => $user['photo'],
            'username' => $user['username'],
            'fullname' => $user['fullname'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'city' => $user['city'],
            'subdistrict' => $user['subdistrict'],
            'ward' => $user['ward'],
            'user_type' => $user['user_type'],
            'status' => $user['status'],
            'last_login' => $user['last_login'],
            'creationdate' => $user['creationdate']
        ];
    }

    private function currentAdmin() {
        $admin = ApiAuth::getUser();
        if (!$admin || !in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent'])) {
            ApiResponse::forbidden("Only SuperAdmin, Admin, or Agent can manage admin users");
        }
        return $admin;
    }

    private function allowedRoles($admin) {
        if ($admin['user_type'] === 'SuperAdmin') {
            return $this->roles;
        }
        if ($admin['user_type'] === 'Admin') {
            return ['Report', 'Agent', 'Sales'];
        }
        if ($admin['user_type'] === 'Agent') {
            return ['Sales'];
        }
        return [];
    }

    private function scopedQuery($admin) {
        $query = ORM::for_table('tbl_users');
        if ($admin['user_type'] === 'Admin') {
            $query->where_in('user_type', ['Report', 'Agent', 'Sales']);
        } else if ($admin['user_type'] === 'Agent') {
            $query->where('root', $admin['id'])->where('user_type', 'Sales');
        }
        return $query;
    }

    private function findScoped($id, $admin) {
        $query = $this->scopedQuery($admin);
        return $query->where('id', $id)->find_one();
    }

    private function input() {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        $form = [];
        parse_str($raw, $form);
        return !empty($form) ? $form : $_POST;
    }

    public function roles() {
        $admin = $this->currentAdmin();
        $allowed = $this->allowedRoles($admin);
        $data = [];
        foreach ($this->roles as $role) {
            $data[] = [
                'name' => $role,
                'assignable' => in_array($role, $allowed)
            ];
        }
        ApiResponse::success("Admin roles", $data);
    }

    public function list() {
        $admin = $this->currentAdmin();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $role = isset($_GET['role']) ? trim($_GET['role']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';

        $query = $this->scopedQuery($admin);
        if ($search !== '') {
            $query->where_raw('(username LIKE ? OR fullname LIKE ? OR email LIKE ? OR phone LIKE ?)', ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }
        if ($role !== '') {
            if (!in_array($role, $this->allowedRoles($admin)) && $admin['user_type'] !== 'SuperAdmin') {
                ApiResponse::forbidden("You cannot view this role");
            }
            $query->where('user_type', $role);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();
        $users = $query->order_by_asc('id')->limit($limit)->offset($offset)->find_many();

        $data = [];
        foreach ($users as $user) {
            $data[] = $this->serialize($user);
        }

        ApiResponse::success("Admin user list", $data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]);
    }

    public function detail($params) {
        $admin = $this->currentAdmin();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid admin user ID", 400);
        }

        $user = $this->findScoped($id, $admin);
        if (!$user) {
            ApiResponse::notFound("Admin user not found");
        }

        ApiResponse::success("Admin user details", $this->serialize($user));
    }

    public function create() {
        $admin = $this->currentAdmin();
        $input = $this->input();
        $allowedRoles = $this->allowedRoles($admin);

        $username = isset($input['username']) ? alphanumeric(trim($input['username']), "_-") : '';
        $fullname = isset($input['fullname']) ? trim($input['fullname']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';
        $userType = isset($input['user_type']) ? trim($input['user_type']) : '';
        $status = isset($input['status']) ? trim($input['status']) : 'Active';

        if ($username === '' || $fullname === '' || $password === '' || $userType === '') {
            ApiResponse::error("Username, fullname, password, and user_type are required", 422);
        }
        if (!in_array($userType, $allowedRoles)) {
            ApiResponse::forbidden("You cannot assign this role");
        }
        if (!in_array($status, ['Active', 'Inactive'])) {
            ApiResponse::error("Invalid status", 422);
        }
        if (ORM::for_table('tbl_users')->where('username', $username)->find_one()) {
            ApiResponse::error("Username already exists", 409);
        }

        $user = ORM::for_table('tbl_users')->create();
        $user->username = $username;
        $user->fullname = $fullname;
        $user->password = Password::_crypt($password);
        $user->phone = isset($input['phone']) ? trim($input['phone']) : '';
        $user->email = isset($input['email']) ? trim($input['email']) : '';
        $user->city = isset($input['city']) ? trim($input['city']) : '';
        $user->subdistrict = isset($input['subdistrict']) ? trim($input['subdistrict']) : '';
        $user->ward = isset($input['ward']) ? trim($input['ward']) : '';
        $user->user_type = $userType;
        $user->status = $status;
        $user->root = $admin['user_type'] === 'Agent' ? (int)$admin['id'] : (isset($input['root']) ? (int)$input['root'] : 0);
        if ($userType === 'Sales' && $admin['user_type'] !== 'Agent' && isset($input['root'])) {
            $agent = ORM::for_table('tbl_users')->where('user_type', 'Agent')->find_one((int)$input['root']);
            if (!$agent) {
                ApiResponse::error("Selected root agent was not found", 422);
            }
        }
        $user->creationdate = date('Y-m-d H:i:s');
        $user->save();

        _log('[' . $admin['username'] . ']: Created ' . $userType . ' ' . $username . ' (API)', $admin['user_type'], $admin['id']);
        ApiResponse::success("Admin user created successfully", ['id' => (int)$user->id()], [], 201);
    }

    public function update($params) {
        $admin = $this->currentAdmin();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid admin user ID", 400);
        }

        $user = $this->findScoped($id, $admin);
        if (!$user) {
            ApiResponse::notFound("Admin user not found");
        }

        $input = $this->input();
        $allowedRoles = $this->allowedRoles($admin);

        if (isset($input['username'])) {
            $username = alphanumeric(trim($input['username']), "_-");
            if ($username === '') {
                ApiResponse::error("Username cannot be empty", 422);
            }
            $existing = ORM::for_table('tbl_users')->where('username', $username)->where_not_equal('id', $id)->find_one();
            if ($existing) {
                ApiResponse::error("Username already exists", 409);
            }
            $user->username = $username;
        }
        if (isset($input['fullname'])) $user->fullname = trim($input['fullname']);
        if (isset($input['password']) && trim($input['password']) !== '') $user->password = Password::_crypt(trim($input['password']));
        if (isset($input['phone'])) $user->phone = trim($input['phone']);
        if (isset($input['email'])) $user->email = trim($input['email']);
        if (isset($input['city'])) $user->city = trim($input['city']);
        if (isset($input['subdistrict'])) $user->subdistrict = trim($input['subdistrict']);
        if (isset($input['ward'])) $user->ward = trim($input['ward']);
        if (isset($input['status'])) {
            if (!in_array($input['status'], ['Active', 'Inactive'])) {
                ApiResponse::error("Invalid status", 422);
            }
            $user->status = $input['status'];
        }
        if (isset($input['user_type'])) {
            $userType = trim($input['user_type']);
            if (!in_array($userType, $allowedRoles)) {
                ApiResponse::forbidden("You cannot assign this role");
            }
            $user->user_type = $userType;
        }
        if ($admin['user_type'] === 'Agent') {
            $user->root = (int)$admin['id'];
        } else if (isset($input['root'])) {
            $user->root = (int)$input['root'];
        }

        $user->save();
        _log('[' . $admin['username'] . ']: Updated admin user ' . $user['username'] . ' (API)', $admin['user_type'], $admin['id']);
        ApiResponse::success("Admin user updated successfully");
    }

    public function delete($params) {
        $admin = $this->currentAdmin();
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            ApiResponse::error("Invalid admin user ID", 400);
        }
        if ((int)$admin['id'] === $id) {
            ApiResponse::error("You cannot delete your own account", 422);
        }

        $user = $this->findScoped($id, $admin);
        if (!$user) {
            ApiResponse::notFound("Admin user not found");
        }

        $username = $user['username'];
        $user->delete();
        _log('[' . $admin['username'] . ']: Deleted admin user ' . $username . ' (API)', $admin['user_type'], $admin['id']);
        ApiResponse::success("Admin user deleted successfully");
    }
}
