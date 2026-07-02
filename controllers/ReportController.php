<?php

class ReportController {
    
    // GET /reports/daily
    public function daily() {
        $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
        
        // Basic date validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            ApiResponse::error("Invalid date format. Expected Y-m-d", 400);
        }

        $transactions = ORM::for_table('tbl_transactions')
            ->where('recharged_on', $date)
            ->order_by_desc('id')
            ->find_many();

        $totalRevenue = ORM::for_table('tbl_transactions')
            ->where('recharged_on', $date)
            ->sum('price');
        $totalRevenue = $totalRevenue ? (float)$totalRevenue : 0.0;

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

        ApiResponse::success("Daily report details for " . $date, [
            'date' => $date,
            'total_revenue' => $totalRevenue,
            'count' => count($data),
            'transactions' => $data
        ]);
    }

    // GET /reports/monthly
    public function monthly() {
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            ApiResponse::error("Invalid month or year parameters", 400);
        }

        $transactions = ORM::for_table('tbl_transactions')
            ->where_raw('MONTH(recharged_on) = ? AND YEAR(recharged_on) = ?', [$month, $year])
            ->order_by_desc('id')
            ->find_many();

        $totalRevenue = ORM::for_table('tbl_transactions')
            ->where_raw('MONTH(recharged_on) = ? AND YEAR(recharged_on) = ?', [$month, $year])
            ->sum('price');
        $totalRevenue = $totalRevenue ? (float)$totalRevenue : 0.0;

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

        ApiResponse::success("Monthly report details for " . sprintf("%02d-%d", $month, $year), [
            'month' => $month,
            'year' => $year,
            'total_revenue' => $totalRevenue,
            'count' => count($data),
            'transactions' => $data
        ]);
    }
}
