<?php

// 1. CORS Headers
if ($_SERVER['REQUEST_METHOD'] === "OPTIONS" || $_SERVER['REQUEST_METHOD'] === "HEAD") {
    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("HTTP/1.1 200 OK");
    die();
}

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// 2. Bootstrap PHPNuxBill Environment
$isApi = true;
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'init.php';

// 3. Include API Core Helpers
require_once dirname(__FILE__) . '/ApiResponse.php';
require_once dirname(__FILE__) . '/ApiRouter.php';
require_once dirname(__FILE__) . '/ApiAuth.php';
require_once dirname(__FILE__) . '/ApiLog.php';

// 4. Extract Route Path
$routePath = isset($_GET['_route']) ? $_GET['_route'] : '';
if (empty($routePath)) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestUri = explode('?', $requestUri)[0];
    $basePath = dirname($scriptName);
    if (strpos($requestUri, $basePath) === 0) {
        $routePath = substr($requestUri, strlen($basePath));
    }
}
$routePath = trim($routePath, '/');

// 5. Initialize Router
$router = new ApiRouter();

// -------------------------------------------------------------
// ROUTES DEFINITION
// -------------------------------------------------------------

// Auth Routes
$router->post('auth/login', 'AuthController@login');
$router->post('auth/login-customer', 'AuthController@loginCustomer');
$router->post('auth/register', 'AuthController@register');
$router->post('auth/forgot-password/request', 'AuthController@forgotPasswordRequest');
$router->post('auth/forgot-password/verify', 'AuthController@forgotPasswordVerify');
$router->post('auth/forgot-username', 'AuthController@forgotUsername');
$router->get('auth/me', 'AuthController@me', ['ApiAuth@any']);
$router->put('auth/me', 'AuthController@updateProfile', ['ApiAuth@any']);

// Dashboard Routes (Admin)
$router->get('dashboard/summary', 'DashboardController@summary', ['ApiAuth@admin']);
$router->get('dashboard/chart-data', 'DashboardController@chartData', ['ApiAuth@admin']);

// Admin User & Role Routes
$router->get('admin-roles', 'AdminUserController@roles', ['ApiAuth@admin']);
$router->get('admin-users', 'AdminUserController@list', ['ApiAuth@admin']);
$router->get('admin-users/{id}', 'AdminUserController@detail', ['ApiAuth@admin']);
$router->post('admin-users', 'AdminUserController@create', ['ApiAuth@admin']);
$router->put('admin-users/{id}', 'AdminUserController@update', ['ApiAuth@admin']);
$router->delete('admin-users/{id}', 'AdminUserController@delete', ['ApiAuth@admin']);

// Customer Routes (Admin)
$router->get('customers', 'CustomerController@list', ['ApiAuth@admin']);
$router->get('customers/{id}', 'CustomerController@detail', ['ApiAuth@admin']);
$router->post('customers', 'CustomerController@create', ['ApiAuth@admin']);
$router->put('customers/{id}', 'CustomerController@update', ['ApiAuth@admin']);
$router->delete('customers/{id}', 'CustomerController@delete', ['ApiAuth@admin']);
$router->get('customers/{id}/packages', 'CustomerController@packages', ['ApiAuth@admin']);
$router->post('customers/deactivate', 'CustomerController@deactivate', ['ApiAuth@admin']);

// Plan/Service Routes
$router->get('plans', 'PlanController@list', ['ApiAuth@any']);
$router->get('plans/{id}', 'PlanController@detail', ['ApiAuth@any']);
$router->post('plans', 'PlanController@create', ['ApiAuth@admin']);
$router->put('plans/{id}', 'PlanController@update', ['ApiAuth@admin']);
$router->delete('plans/{id}', 'PlanController@delete', ['ApiAuth@admin']);
$router->post('plans/recharge', 'PlanController@recharge', ['ApiAuth@admin']);
$router->get('bandwidths', 'PlanController@bandwidths', ['ApiAuth@admin']);
$router->post('bandwidths', 'PlanController@createBandwidth', ['ApiAuth@admin']);
$router->put('bandwidths/{id}', 'PlanController@updateBandwidth', ['ApiAuth@admin']);
$router->delete('bandwidths/{id}', 'PlanController@deleteBandwidth', ['ApiAuth@admin']);

$router->get('pools', 'PlanController@pools', ['ApiAuth@admin']);
$router->post('pools', 'PlanController@createPool', ['ApiAuth@admin']);
$router->put('pools/{id}', 'PlanController@updatePool', ['ApiAuth@admin']);
$router->delete('pools/{id}', 'PlanController@deletePool', ['ApiAuth@admin']);

// Radius Routes
$router->get('radius/nas', 'RadiusController@list', ['ApiAuth@admin']);
$router->get('radius/nas/{id}', 'RadiusController@detail', ['ApiAuth@admin']);
$router->post('radius/nas', 'RadiusController@create', ['ApiAuth@admin']);
$router->put('radius/nas/{id}', 'RadiusController@update', ['ApiAuth@admin']);
$router->delete('radius/nas/{id}', 'RadiusController@delete', ['ApiAuth@admin']);

// Voucher Routes
$router->get('vouchers', 'VoucherController@list', ['ApiAuth@admin']);
$router->post('vouchers/generate', 'VoucherController@generate', ['ApiAuth@admin']);
$router->post('vouchers/activate', 'VoucherController@activate', ['ApiAuth@any']);

// Router Routes (Admin)
$router->get('routers', 'RouterController@list', ['ApiAuth@admin']);
$router->get('routers/{id}', 'RouterController@detail', ['ApiAuth@admin']);
$router->post('routers', 'RouterController@create', ['ApiAuth@admin']);
$router->put('routers/{id}', 'RouterController@update', ['ApiAuth@admin']);
$router->delete('routers/{id}', 'RouterController@delete', ['ApiAuth@admin']);

// Transaction & Report Routes (Admin)
$router->get('transactions', 'TransactionController@list', ['ApiAuth@admin']);
$router->get('reports/daily', 'ReportController@daily', ['ApiAuth@admin']);
$router->get('reports/monthly', 'ReportController@monthly', ['ApiAuth@admin']);

// Customer Portal Routes (Customer Only)
$router->get('customer/profile', 'CustomerPortalController@profile', ['ApiAuth@customer']);
$router->put('customer/profile', 'CustomerPortalController@updateProfile', ['ApiAuth@customer']);
$router->get('customer/packages', 'CustomerPortalController@packages', ['ApiAuth@customer']);
$router->get('customer/transactions', 'CustomerPortalController@transactions', ['ApiAuth@customer']);
$router->post('customer/voucher/activate', 'CustomerPortalController@activateVoucher', ['ApiAuth@customer']);
$router->get('customer/balance', 'CustomerPortalController@balance', ['ApiAuth@customer']);
$router->post('customer/balance/transfer', 'CustomerPortalController@balanceTransfer', ['ApiAuth@customer']);

$router->get('/settings','SettingController@index', ['ApiAuth@admin']);
$router->get('/logs','LogController@list', ['ApiAuth@admin']);

// 6. Dispatch Request
try {
    $router->dispatch($routePath, $_SERVER['REQUEST_METHOD']);
} catch (Exception $e) {
    ApiResponse::internalServerError($e->getMessage());
}
