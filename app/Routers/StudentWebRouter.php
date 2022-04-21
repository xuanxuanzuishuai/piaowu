<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:47
 */

namespace App\Routers;

use App\Controllers\Agent\Agent;
use App\Controllers\OrgWeb\LandingRecall;
use App\Controllers\StudentWeb\Landing;
use App\Controllers\StudentWeb\Lottery;
use App\Controllers\StudentWeb\Order;
use App\Controllers\StudentWeb\Recall;
use App\Controllers\StudentWeb\Student;
use App\Controllers\StudentWeb\Area;
use App\Controllers\StudentWeb\StudentWebCommon;
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
        // Landing页召回,解密手机号
        '/student_web/landing_recall/decode_sign' => ['method' => ['get'], 'call' => LandingRecall::class . ':decodeSign', 'middles' => []],

        //发送验证码
        '/student_web/student/send_sms_code' => ['method' => ['get'], 'call'   => Student::class . ':sendSmsCode', 'middles' => []],
        //鲸鱼数据记录
        '/student_web/student/whale_data_record' => ['method' => ['get'], 'call'   => Student::class . ':whaleDataRecord', 'middles' => []],
        //  获取学生是否是系统判定的重复用户，如果是购买指定课包时会返回其他课包
        '/student_web/student/check_student_is_repeat' => ['method' => ['get'], 'call' => Student::class . ':checkStudentIsRepeat', 'middles' => []],


        /********************抽奖活动接口**********************/
        //用户登录接口
        '/student_web/common/login' => ['method' => ['get'], 'call' => StudentWebCommon::class . ':login','middles' => []],
        '/student_web/common/country' => ['method' => ['get'], 'call' => StudentWebCommon::class . ':countryList'],
        '/student_web/common/province' => ['method' => ['get'], 'call' => StudentWebCommon::class . ':provinceList'],
        '/student_web/common/city' => ['method' => ['get'], 'call' => StudentWebCommon::class . ':cityList'],
        '/student_web/common/district' => ['method' => ['get'], 'call' => StudentWebCommon::class . ':districtList'],
        //活动信息获取
        '/student_web/lottery/activity_info' => ['method' => ['get'], 'call' => Lottery::class . ':activityInfo','middles'=>[]],
        //开始抽奖
        '/student_web/lottery/start_lottery' => ['method' => ['get'], 'call' => Lottery::class . ':startLottery'],
        //获取中奖记录
        '/student_web/lottery/hit_record' => ['method' => ['get'], 'call' => Lottery::class . ':hitRecord'],
        //获取收货地址
        '/student_web/lottery/get_address' => ['method' => ['get'], 'call' => Lottery::class . ':getAddress'],
        //修改收货地址
        '/student_web/lottery/modify_address' => ['method' => ['post'], 'call' => Lottery::class . ':modifyAddress'],
        //查看物流信息
        '/student_web/lottery/shipping_info' => ['method' => ['get'], 'call' => Lottery::class . ':shippingInfo'],
    ];
}