<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/24
 * Time: 17:15
 */

namespace App\Routers;

use App\Controllers\Employee\Employee;
use App\Controllers\Employee\Goods;
use App\Controllers\Employee\Privilege;
use App\Controllers\Employee\Receipt;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Controllers\Employee\BA;
use App\Controllers\Employee\Shop;

class EmployeeRouter extends RouterBase
{
    protected $logFilename = 'employee.log';
    public    $middleWares = [EmployeeAuthCheckMiddleWare::class];
    protected $uriConfig = [

        '/employee/employee/login' => ['method' => ['post'], 'call' => Employee::class . ':login', 'middles' => []],
        '/employee/employee/list' => ['method' => ['get'], 'call' => Employee::class . ':list'],
        '/employee/employee/info' => ['method' => ['get'], 'call' => Employee::class . ':info'],
        '/employee/privilege/employee_menu' => ['method' => ['get'], 'call' => Privilege::class . ':employeeMenu'],

        '/employee/employee/employee_list' => ['method' => ['get'], 'call' => Employee::class . ':list'],
        '/employee/employee/add_employee' => ['method' => ['post'], 'call' => Employee::class . ':add'],
        '/employee/employee/update_pwd' => ['method' => ['post'], 'call' => Employee::class . ':updatePwd'],


        '/employee/ba/get_ba' => ['method' => ['get'], 'call' => BA::class . ':baList'],
        '/employee/ba/update_apply' => ['method' => ['post'], 'call' => BA::class . ':updateApply'],
        '/employee/ba/get_ba_info' => ['method' => ['get'], 'call' => BA::class . ':getBaInfo'],
        '/employee/ba/get_pass_ba' => ['method' => ['get'], 'call' => BA::class . ':getPassBa'],
        '/employee/ba/export_ba' => ['method' => ['get'], 'call' => BA::class . ':exportBa'],


        '/employee/receipt/add_receipt' => ['method' => ['post'], 'call' => Receipt::class . ':addReceipt'],
        '/employee/receipt/receipt_list' => ['method' => ['get'], 'call' => Receipt::class . ':receiptList'],
        '/employee/receipt/receipt_info' => ['method' => ['get'], 'call' => Receipt::class . ':receiptInfo'],
        '/employee/receipt/update_receipt' => ['method' => ['get'], 'call' => Receipt::class . ':updateReceipt'],
        '/employee/receipt/export_data' => ['method' => ['get'], 'call' => Receipt::class . ':exportData'],



        '/employee/shop/shop_list' => ['method' => ['get'], 'call' => Shop::class . ':shopList'],
        '/employee/shop/add_shop' => ['method' => ['post'], 'call' => Shop::class . ':addShop'],

        '/employee/goods/goods_list' => ['method' => ['get'], 'call' => Goods::class . ':list'],
        '/employee/goods/add_goods' => ['method' => ['post'], 'call' => Goods::class . ':addGoods'],
    ];
}