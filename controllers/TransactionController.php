<?php

class TransactionController {
    
    // GET /transactions
    public function list() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 10;
        $offset = ($page - 1) * $limit;

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $router = isset($_GET['router']) ? trim($_GET['router']) : '';
        $method = isset($_GET['method']) ? trim($_GET['method']) : '';
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

        $query = ORM::for_table('tbl_transactions');

        if (!empty($search)) {
            $query->where_raw('(username LIKE ? OR plan_name LIKE ? OR invoice LIKE ?)', ["%$search%", "%$search%", "%$search%"]);
        }
        if (!empty($router)) {
            $query->where('routers', $router);
        }
        if (!empty($method)) {
            $query->where('method', $method);
        }
        if ($user_id > 0) {
            $query->where('user_id', $user_id);
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $transactions = $query->limit($limit)->offset($offset)->order_by_desc('id')->find_many();

        $data = [];
        foreach ($transactions as $t) {
            $data[] = [
                'id' => (int)$t['id'],
                'invoice' => $t['invoice'],
                'username' => $t['username'],
                'user_id' => (int)$t['user_id'],
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

        ApiResponse::success("Transaction list", $data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]);
    }
}
