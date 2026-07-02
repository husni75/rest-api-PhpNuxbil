<?php

class DashboardController {
    
    // GET /dashboard/summary
    public function summary() {
        global $current_date, $start_date;

        // Ensure dates are initialized (in case they are not in global scope)
        if (empty($current_date)) {
            $current_date = date('Y-m-d');
        }
        
        if (empty($start_date)) {
            global $config;
            $reset_day = isset($config['reset_day']) ? $config['reset_day'] : 1;
            if (date("d") >= $reset_day) {
                $start_date = date('Y-m-' . sprintf('%02d', $reset_day));
            } else {
                $start_date = date('Y-m-' . sprintf('%02d', $reset_day), strtotime("-1 MONTH"));
            }
        }

        // Today's Sales
        $iday = ORM::for_table('tbl_transactions')
            ->where('recharged_on', $current_date)
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->sum('price');
        $iday = $iday ? (float)$iday : 0.0;

        // Monthly Sales
        $imonth = ORM::for_table('tbl_transactions')
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->where_gte('recharged_on', $start_date)
            ->where_lte('recharged_on', $current_date)
            ->sum('price');
        $imonth = $imonth ? (float)$imonth : 0.0;

        // Active Packages
        $u_act = ORM::for_table('tbl_user_recharges')->where('status', 'on')->count();
        $u_act = $u_act ? (int)$u_act : 0;

        // Total Recharge Transactions Count
        $u_all = ORM::for_table('tbl_user_recharges')->count();
        $u_all = $u_all ? (int)$u_all : 0;

        // Total Customers
        $c_all = ORM::for_table('tbl_customers')->count();
        $c_all = $c_all ? (int)$c_all : 0;

        ApiResponse::success("Dashboard summary details", [
            'today_sales' => $iday,
            'monthly_sales' => $imonth,
            'active_packages' => $u_act,
            'total_recharges' => $u_all,
            'total_customers' => $c_all
        ]);
    }

    // GET /dashboard/chart-data
    public function chartData() {
        // 1. Monthly Sales for Current Year
        $salesResults = ORM::for_table('tbl_transactions')
            ->select_expr('MONTH(recharged_on)', 'month')
            ->select_expr('SUM(price)', 'total')
            ->where_raw("YEAR(recharged_on) = YEAR(CURRENT_DATE())")
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->group_by_expr('MONTH(recharged_on)')
            ->find_many();

        $monthlySales = array_fill(1, 12, 0.0);
        foreach ($salesResults as $row) {
            $monthlySales[(int)$row->month] = (float)$row->total;
        }

        $formattedSales = [];
        for ($m = 1; $m <= 12; $m++) {
            $formattedSales[] = [
                'month' => $m,
                'totalSales' => $monthlySales[$m]
            ];
        }

        // 2. Monthly Registered Customers for Current Year
        $customerResults = ORM::for_table('tbl_customers')
            ->select_expr('MONTH(created_at)', 'month')
            ->select_expr('COUNT(*)', 'count')
            ->where_raw('YEAR(created_at) = YEAR(NOW())')
            ->group_by_expr('MONTH(created_at)')
            ->find_many();

        $monthlyCustomers = array_fill(1, 12, 0);
        foreach ($customerResults as $row) {
            $monthlyCustomers[(int)$row->month] = (int)$row->count;
        }

        $formattedCustomers = [];
        for ($m = 1; $m <= 12; $m++) {
            $formattedCustomers[] = [
                'month' => $m,
                'count' => $monthlyCustomers[$m]
            ];
        }

        ApiResponse::success("Dashboard chart data details", [
            'monthly_sales' => $formattedSales,
            'monthly_registered' => $formattedCustomers
        ]);
    }
}
