<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:47
 */

namespace App\Routers;

use App\Controllers\StudentWeb\Order;
use App\Controllers\StudentWeb\Student;
use App\Controllers\StudentWeb\Area;
use App\Middleware\WebAuthCheckMiddleware;

class StudentWebRouter extends RouterBase
{
    protected $logFilename = 'operation_student_web.log';
    public $middleWares = [WebAuthCheckMiddleware::class];
    protected $uriConfig = [
        // 新订单中心
        // 产品包详情
        '/student_web/order/package_detail' => ['method' => ['get'], 'call' => Order::class . ':getPackageDetail'],
        // 创建订单
        '/student_web/order/create' => ['method' => ['post'], 'call' => Order::class . ':createOrder'],
        // 订单状态
        '/student_web/order/status' => ['method' => ['get'], 'call' => Order::class . ':orderStatus'],
        // 产品包赠品信息
        '/student_web/order/package_gift_goods' => ['method' => ['get'], 'call' => Order::class . ':getGiftGroupGoods'],
        // 根据订单ID获取产品或赠品中的实物商品和邮寄地址
        '/student_web/order/check_objects_and_address' => ['method' => ['get'], 'call' => Order::class . ':checkObjectsAndAddress'],
        // 更新产品包邮寄地址
        '/student_web/order/update_address' => ['method' => ['post'], 'call' => Order::class . ':updateOrderAddress'],
        '/student_web/order/pay_success' => ['method' => ['get'], 'call' => Order::class . ':paySuccess'],
        '/student_web/student/address_list' => ['method' => ['get'], 'call' => Student::class . ':addressList'],
        '/student_web/student/modify_address' => ['method' => ['post'], 'call' => Student::class . ':modifyAddress'],

        '/student_web/area/get_by_parent_code' => ['method' => ['get'], 'call' => Area::class . ':getByParentCode'],
        '/student_web/area/get_by_code' => ['method' => ['get'], 'call' => Area::class . ':getByCode'],
    ];
}