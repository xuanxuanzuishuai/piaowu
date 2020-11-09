<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:52 PM
 */

namespace App\Routers;
use App\Controllers\API\MUSVG;
use App\Controllers\StudentApp\Favorite;
use App\Controllers\StudentApp\Leave;
use App\Controllers\StudentApp\App;
use App\Controllers\StudentApp\Auth;
use App\Controllers\StudentApp\InteractiveClassroom;
use App\Controllers\StudentApp\Opn;
use App\Controllers\StudentApp\Panda;
use App\Controllers\StudentApp\Pay;
use App\Controllers\StudentApp\Play;
use App\Controllers\StudentApp\Homework;
use App\Controllers\StudentApp\Referral;
use App\Controllers\StudentApp\SaleShop;
use App\Controllers\StudentApp\Subscription;
use App\Middleware\AppApiForStudent;
use App\Middleware\MUSVGMiddleWare;
use App\Middleware\StudentAuthCheckMiddleWareForApp;
use App\Middleware\StudentResPrivilegeCheckMiddleWareForApp;
use App\Controllers\StudentApp\Question;
use App\Controllers\StudentApp\PointActivity;

class StudentAppRouter extends RouterBase
{
    protected $logFilename = 'dss_student.log';

    protected $uriConfig = [
        '/user/auth/get_user_id' => [ // musvg访问
            'method' => ['get'],
            'call' => MUSVG::class . ':getUserId',
            'middles' => [MUSVGMiddleWare::class]
        ],

        '/student_app/auth/login' => [
            'method' => ['post'],
            'call' => Auth::class . ':login',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/auth/token_login' => [
            'method' => ['post'],
            'call' => Auth::class . ':tokenLogin',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/auth/validate_code' => [
            'method' => ['get'],
            'call' => Auth::class . ':validateCode',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/auth/update_pwd' => [
            'method' => ['post'],
            'call' => Auth::class . ':updatePwd',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // /student_app/app
        '/student_app/app/version' => [
            'method' => ['get'],
            'call' => App::class . ':version',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/app/engine' => [
            'method' => ['get'],
            'call' => App::class . ':engine',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/config' => [
            'method' => ['get'],
            'call' => App::class . ':config',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/app/ad_active' => [
            'method' => ['post'],
            'call' => App::class . ':adActive',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/app/feedback' => [
            'method' => ['post'],
            'call' => App::class . ':feedback',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/action' => [
            'method' => ['post'],
            'call' => App::class . ':action',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/set_nickname' => [
            'method' => ['post'],
            'call' => App::class . ':setNickname',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/set_user_info' => [
            'method' => ['post'],
            'call' => App::class . ':setUserInfo',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/get_signature' => [
            'method' => ['get'],
            'call' => App::class . ':getSignature',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/app/leads_check' => [
            'method' => ['get'],
            'call' => App::class . ':leadsCheck',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/app/banner' => [
            'method' => ['get'],
            'call' => App::class . ':banner',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/country_code' => [
            'method' => ['get'],
            'call' => App::class . ':countryCode',
            'middles' => [AppApiForStudent::class]
        ],

        '/student_app/app/get_by_parent_code' => [
            'method' => ['get'],
            'call' => App::class . ':getByParentCode',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/app/get_by_code' => [
            'method' => ['get'],
            'call' => App::class . ':getByCode',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/set_join_ranking' => [
            'method' => ['post'],
            'call' => App::class . ':setJoinRanking',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/home_page' => [
            'method' => ['get'],
            'call' => App::class . ':homePage',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/medal_detail' => [
            'method' => ['get'],
            'call' => App::class . ':medalDetail',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/set_default_medal' => [
            'method' => ['post'],
            'call' => App::class . ':setDefaultMedal',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        // /student_app/sub
        '/student_app/subscription/redeem_gift_code' => [
            'method' => ['post'],
            'call' => Subscription::class . ':redeemGiftCode',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/subscription/trial' => [
            'method' => ['post'],
            'call' => Subscription::class . ':trial',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // /student_app/opn
        '/student_app/opn/categories' => [
            'method' => ['get'],
            'call' => Opn::class . ':categories',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/collections' => [
            'method' => ['get'],
            'call' => Opn::class . ':collections',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/hot_collections' => [
            'method' => ['get'],
            'call' => Opn::class . ':hotCollections',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/lessons' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessons',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/lesson' => [
            'method' => ['get'],
            'call' => Opn::class . ':lesson',
            'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class,
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/lesson_limit' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessonLimit',
            'middles' => [
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/music_score_search' => [
            'method' => ['post'],
            'call' => Opn::class . ':musicScoreSearch',
            'middles' => [
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/lesson_resources' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessonResources',
            'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class,
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/search' => [
            'method' => ['get'],
            'call' => Opn::class . ':search',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/search_collection' => [
            'method' => ['get'],
            'call' => Opn::class . ':searchCollection',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/search_lesson' => [
            'method' => ['get'],
            'call' => Opn::class . ':searchLesson',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/engine' => [
            'method' => ['get'],
            'call' => Opn::class . ':engine',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/play/save' => [
            'method' => ['get'],
            'call' => Play::class . ':save',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/play/end' => [
            'method' => ['post'],
            'call' => Play::class . ':end',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/play/rank' => [
            'method' => ['get'],
            'call' => Play::class . ':rank',
            'middles' => [MUSVGMiddleWare::class]
        ],
        '/student_app/play/class_end' => [
            'method' => ['post'],
            'call' => Play::class . ':classEnd',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/play/duration' => [
            'method' => ['get'],
            'call' => Play::class . ':playDuration',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/play/set_join_ranking' => [
            'method' => ['post'],
            'call' => Play::class . ':setJoinRanking',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/play/join_ranking_status' => [
            'method' => ['get'],
            'call' => Play::class . ':joinRankingStatus',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/homework/record' => [
            'method' => ['get'],
            'call' => Homework::class . ':record',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/homework/list' => [
            'method' => ['get'],
            'call' => Homework::class . ':list',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // 熊猫接口
        '/student_app/panda/get_student' => [
            'method' => ['get'],
            'call' => Panda::class . ':getStudent',
            'middles' => []
        ],
        '/student_app/panda/recent_played' => [
            'method' => ['get'],
            'call' => Panda::class . ':recentPlayed',
            'middles' => []
        ],
        '/student_app/panda/recent_detail' => [
            'method' => ['get'],
            'call' => Panda::class . ':recentDetail',
            'middles' => []
        ],
        '/student_app/panda/ai_end' => [
            'method' => ['post'],
            'call' => Panda::class . ':aiEnd',
            'middles' => []
        ],

        // 支付
        '/student_app/pay/create_bill' => [
            'method' => ['post'],
            'call' => Pay::class . ':createBill',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/pay/packages' => [
            'method' => ['get'],
            'call' => Pay::class . ':appPackages',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/pay/bill_status' => [
            'method' => ['get'],
            'call' => Pay::class . ':billStatus',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // 积分商城
        '/student_app/sale_shop/shop_packages' => [
            'method' => ['get'],
            'call' => SaleShop::class . ':saleShopPackages',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/shop_package_detail' => [
            'method' => ['get'],
            'call' => SaleShop::class . ':saleShopPackageDetail',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/address_list' => [
            'method' => ['get'],
            'call' => SaleShop::class . ':addressList',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/address_modify' => [
            'method' => ['post'],
            'call' => SaleShop::class . ':modifyAddress',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/address_delete' => [
            'method' => ['post'],
            'call' => SaleShop::class . ':deleteAddress',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // 积分商城订单
        '/student_app/sale_shop/create_bill' => [
            'method' => ['post'],
            'call' => SaleShop::class . ':createBill',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/bill_list' => [
            'method' => ['get'],
            'call' => SaleShop::class . ':billList',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/bill_detail' => [
            'method' => ['get'],
            'call' => SaleShop::class . ':billDetail',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/sale_shop/logistics' => [
            'method' => ['get'],
            'call' => SaleShop::class . ':billLogistics',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],


        '/student_app/referral/list' => [
            'method' => ['get'],
            'call' => Referral::class . ':list',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        //APP内嵌音基接口
        '/student_app/exam/question_list' => [
            'method'  => ['get'],
            'call'    => Question::class . ':list',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/exam/question_baseList' => [
            'method'  => ['get'],
            'call'    => Question::class . ':baseList',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/exam/question_categoryRelateQuestions' => [
            'method'  => ['get'],
            'call'    => Question::class . ':categoryRelateQuestions',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/points/activity_list' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':activityList',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/points/report' => [
            'method'  => ['post'],
            'call'    => PointActivity::class . ':report',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/points/points_detail' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':pointsDetail',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/points/total' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':getStudentTotalPoints',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/alert/need_alert' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':needAlert',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/term/term_sprint' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':termSprint',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/term/draw_award' => [
            'method'  => ['post'],
            'call'    => PointActivity::class . ':drawAward',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
         '/student_app/interactive_classroom/sign_up_course' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getSignUpCourse',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/to_be_launched_course' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getToBeLaunchedCourse',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/in_production_course' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getInProductionCourse',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/today_plan' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':studentTodayPlan',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/small_horn_info' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':smallHornInfo',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/collection_sign_up' => [
            'method'  => ['post'],
            'call'    => InteractiveClassroom::class . ':collectionSignUp',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/cancel_sign_up' => [
            'method'  => ['post'],
            'call'    => InteractiveClassroom::class . ':cancelSignUp',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/update_learn_status' => [
            'method'  => ['post'],
            'call'    => InteractiveClassroom::class . ':studentLearnRecord',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/play_calendar' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getPlayCalendar',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/learn_calendar' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getLearnCalendar',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/calendar_details' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getCalendarDetails',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/get_student_share_token' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':studentShareToken',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/get_student_identity' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':getStudentIdentity',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/lesson_recourse' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':lessonRecourse',
            'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class,StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/expect' => [
            'method'  => ['post'],
            'call'    => InteractiveClassroom::class . ':expect',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/platform_course_plan' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':platformCoursePlan',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],
        '/student_app/interactive_classroom/collection_detail' => [
            'method'  => ['get'],
            'call'    => InteractiveClassroom::class . ':collectionDetail',
            'middles' => [StudentAuthCheckMiddleWareForApp::class,AppApiForStudent::class]
        ],

        //万圣节活动相关路由
        '/student_app/halloween/sign_up' => [
            'method'  => ['post'],
            'call'    => PointActivity::class . ':halloweenSignUp',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/halloween/user_record' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':halloweenUserRecord',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/halloween/rank' => [
            'method'  => ['get'],
            'call'    => PointActivity::class . ':halloweenRank',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/halloween/take_award' => [
            'method'  => ['post'],
            'call'    => PointActivity::class . ':halloweenTakeAward',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        //学生请假
        '/student_app/leave/cancel_leave' => [
            'method'  => ['post'],
            'call'    => Leave::class . ':studentCancelLeave',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        //收藏曲谱，教材
        '/student_app/favorite/first_page_list' => [
            'method'  => ['get'],
            'call'    => Favorite::class . ':firstPageList',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/favorite/add' => [
            'method'  => ['post'],
            'call'    => Favorite::class . ':add',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/favorite/cancel' => [
            'method'  => ['post'],
            'call'    => Favorite::class . ':cancel',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/favorite/get_favorite_lesson' => [
            'method'  => ['get'],
            'call'    => Favorite::class . ':getFavoritesLesson',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/favorite/get_favorite_collection' => [
            'method'  => ['get'],
            'call'    => Favorite::class . ':getFavoriteCollection',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/favorite/favorite_status' => [
            'method'  => ['get'],
            'call'    => Favorite::class . ':favoriteStatus',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
    ];
}