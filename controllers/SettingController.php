<?php


class SettingController
{


public function index(){
//Middleware::auth();
$config=[];
$list =ORM::for_table('tbl_appconfig')->find_many();
foreach($list as $row){
$config[$row->setting]=$row->value;}

$data=[
"company_name"=>$config['CompanyName'] ?? '',
"logo"=>$config['logo'] ?? '/system/uploads/logo.png',
"currency"=>$config['currency_code'] ?? 'IDR',
"timezone"=>$config['timezone'] ?? 'Asia/Jakarta',
"dec_point"=>$config['dec_point'] ?? ',',
"thousands_sep"=>$config['thousands_sep'] ?? '.',
"rtl"=>$config['rtl'] ?? '0',
"language"=>$config['language'] ?? 'en',
"payment_usings"=>$config['payment_usings'] ?? '',
"date_format"=>$config['date_format'] ?? 'd M Y',
"phone"=>$config['phone'] ?? '',
"login_page_description"=>$config['login_page_description'] ?? '',
"radius_enable"=>$config['radius_enable'] ?? '0',
"allow_balance_transfer"=>$config['allow_balance_transferne'] ?? 'no',
"registration_username"=>$config['registration_username'] ?? 'username',
"photo_register"=>$config['photo_register'] ?? 'no',
"reset_day"=>$config['reset_day'] ?? '5',
"disable_voucher"=>$config['disable_voucher'] ?? 'no',
"country_code_phone"=>$config['country_code_phone'] ?? '62'
];

ApiResponse::success("Application settings",$data);
}
}