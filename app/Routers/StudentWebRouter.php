<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:47
 */

namespace App\Routers;

use App\Controllers\Agent\Agent;
use App\Controllers\StudentWeb\Landing;
use App\Controllers\StudentWeb\Order;
use App\Controllers\StudentWeb\Recall;
use App\Controllers\StudentWeb\Student;
use App\Controllers\StudentWeb\Area;
use App\Middleware\RecallAuthCheckMiddleware;
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
        '/student_web/student/unbind' => ['method' => ['post'], 'call' => Student::class . ':unbind'],
        '/student_web/student/assistant' => ['method' => ['get'], 'call' => Student::class . ':getAssistant'],

        '/student_web/area/get_by_parent_code' => ['method' => ['get'], 'call' => Area::class . ':getByParentCode'],
        '/student_web/area/get_by_code' => ['method' => ['get'], 'call' => Area::class . ':getByCode'],

        // 召回页
        '/student_web/recall/token' => ['method' => ['get'], 'call' => Recall::class . ':getToken', 'middles' => []],
        '/student_web/recall/index' => ['method' => ['get'], 'call' => Recall::class . ':index', 'middles' => [RecallAuthCheckMiddleware::class]],
        '/student_web/config/country_code' => ['method' => ['get'], 'call' => Agent::class . ':countryCode', 'middles' => []],
        '/student_web/event/web_page' => ['method' => ['post'], 'call' => Student::class . ':webPageEvent'],
        // 小叶子智能陪练免费领取5天体验时长页
        // H5 首页
        '/student_web/landing/index' => ['method' => ['get'], 'call' => Landing::class . ':index', 'middles' => []],
        '/student_web/landing/register' => ['method' => ['post'], 'call' => Landing::class . ':register', 'middles' => []],

        //课管主动关联转介绍关系
        '/student_web/student/activity_index' => ['method' => ['get'], 'call' => Student::class . ':activityIndex', 'middles' => []],
        '/student_web/student/send_code' => ['method' => ['get'], 'call' => Student::class . ':sendCode', 'middles' => []],
        '/student_web/student/free_order' => ['method' => ['get'], 'call' => Student::class . ':freeOrder', 'middles' => []],
        '/student_web/student/get_assistant_info' => ['method' => ['get'], 'call' => Student::class . ':getAssistantInfo', 'middles' => []],
        
        // Landing页召回,创建订单
        '/student_web/order/landing_recall_create_order' => ['method' => ['post'], 'call' => Order::class . ':LandingRecallCreateOrder', 'middles' => []],
    ];
}