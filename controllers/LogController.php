<?php

class LogController {
    
    // GET /logs
    public function list() {
        $admin = ApiAuth::getUser();
        if (!$admin || !in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            ApiResponse::forbidden("Only SuperAdmin or Admin can view system logs");
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $query = ORM::for_table('tbl_logs')
          ->where_not_equal('ip', 'CLI');

        if ($search !== '') {
            $query->where_like('description', "%$search%");
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $logs = $query->order_by_desc('id')
            ->limit($limit)
            ->offset($offset)
            ->find_many();

        $data = [];
        foreach ($logs as $log) {
            $data[] = [
                'id' => (int)$log['id'],
                'type' => $log['type'],
                'description' => $log['description'],
                'user_id' => (int)$log['userid'],
                'ip' => $log['ip'],
                'time' => $log['date']
            ];
        }

        ApiResponse::success("System logs", $data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]);
    }
}