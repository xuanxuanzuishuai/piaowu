<?php

namespace App;

use App\Controllers\Boss\GiftCode;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Middleware\AppApi;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\StudentAuthCheckMiddleWareForApp;
use App\Controllers\StudentApp\App as StudentAppApp;
use App\Controllers\StudentApp\Auth as StudentAppAuth;
use App\Controllers\StudentApp\Opn as StudentAppOpn;
use App\Controllers\StudentApp\Play as StudentAppPlay;
use App\Controllers\StudentApp\Homework as StudentAppHomework;
use App\Controllers\StudentApp\Subscription as StudentAppSubscription;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$arr = array(

    '/employee/auth/tokenlogin' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Auth:tokenlogin', 'middles' => array()),
    '/employee/auth/signout' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Auth:signout', 'middles' => array('\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/auth/usercenterurl' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Auth:usercenterurl', 'middles' => array()),

    '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //list for org
    '/employee/employee/list_for_org' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:listForOrg', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/setPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setPwd', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/userSetPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:userSetPwd', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/setExcludePrivilege' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setExcludePrivilege', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/setExtendPrivilege' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setExtendPrivilege', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/getEmployeeListWithRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeListWithRole', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/privilege/privilege/employee_menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:employee_menu', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Privilege:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:menu', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/privilege/privilegeGroup/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilegeGroup/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilegeGroup/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/privilege/role/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/role/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/role/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Role:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //list for org
    '/privilege/role/list_for_org' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:listForOrg', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/boss/campus/list' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Campus:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/campus/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Campus:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/campus/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Campus:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/campus/add' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Campus:add', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/boss/classroom/list' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/classroom/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/classroom/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Classroom:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/classroom/add' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Classroom:add', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    //list,detail are for internal employee
    '/student/student/list' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/detail' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //add,student,info are for org employee
    '/student/student/info' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:info','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/modify' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/add' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
//    '/student/student/get_channels' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:getSChannels','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
//    '/student/student/batch_assign_cc' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:batchAssignCC','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    // /student_app/auth
    '/student_app/auth/login' => [
        'method' => ['post'],
        'call' => StudentAppAuth::class . ':login',
        'middles' => [AppApi::class]
    ],
    '/student_app/auth/token_login' => [
        'method' => ['post'],
        'call' => StudentAppAuth::class . ':tokenLogin',
        'middles' => [AppApi::class]
    ],
    '/student_app/auth/validate_code' => [
        'method' => ['get'],
        'call' => StudentAppAuth::class . ':validateCode',
        'middles' => [AppApi::class]
    ],
//    '/student_app/auth/get_user_id' => [
    '/user/auth/get_user_id' => [ // musvg访问
        'method' => ['get'],
        'call' => StudentAppAuth::class . ':getUserId',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],

    // /student_app/app
    '/student_app/app/version' => [
        'method' => ['get'],
        'call' => StudentAppApp::class . ':version',
        'middles' => [AppApi::class]
    ],
    '/student_app/app/guide' => [
        'method' => ['get'],
        'call' => StudentAppApp::class . ':guide',
        'middles' => [AppApi::class]
    ],
    '/student_app/app/feedback' => [
        'method' => ['post'],
        'call' => StudentAppApp::class . ':feedback',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],

    // /student_app/sub
    '/student_app/subscription/redeem_gift_code' => [
        'method' => ['post'],
        'call' => StudentAppSubscription::class . ':redeemGiftCode',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],

    // /student_app/opn
    '/student_app/opn/categories' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':categories',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/opn/collections' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':collections',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/opn/lessons' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':lessons',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/opn/lesson' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':lesson',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/opn/search' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':search',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],

    // /student_app/play
    '/student_app/play/save' => [
        'method' => ['get'],
        'call' => StudentAppPlay::class . ':save',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/play/end' => [
        'method' => ['post'],
        'call' => StudentAppPlay::class . ':end',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/play/ai_end' => [
        'method' => ['post'],
        'call' => StudentAppPlay::class . ':aiEnd',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],

    // /student_app/homework
    '/student_app/homework/record' => [
        'method' => ['get'],
        'call' => StudentAppHomework::class . ':practiceRecord',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],
    '/student_app/homework/list' => [
        'method' => ['get'],
        'call' => StudentAppHomework::class . ':list',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApi::class]
    ],

    '/goods/course/list' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/detail' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/edit' => array('method'=> array('post'),'call'=> '\App\Controllers\Course\Course:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/add' => array('method'=> array('post'),'call'=> '\App\Controllers\Course\Course:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

//    '/teacher/teacher/detail' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\Teacher:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    //list, updateEntry are for internal employee
    '/teacher/teacher/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\Teacher:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacher/updateEntry' => array('method'=>array('post'),'call'=>'\App\Controllers\Teacher\Teacher:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //add is for org employee
    '/teacher/teacher/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Teacher\Teacher:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
//    '/teacher/teacherTags/tag_list' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\TeacherTag:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/area/area/getByParentCode' => array('method'=>array('get'),'call'=>'\App\Controllers\Area\Area:getByParentCode','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/area/area/getByCode' => array('method'=>array('get'),'call'=>'\App\Controllers\Area\Area:getByCode','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),


    '/schedule/task/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ScheduleTask:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/modify' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ScheduleTask:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\ScheduleTask:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/detail' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\ScheduleTask:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/bindStudents' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ScheduleTask:bindStudents','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/bindTeachers' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ScheduleTask:bindTeachers','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/unbindUsers' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ScheduleTask:unbindUsers','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/beginST' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ScheduleTask:beginST','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),


    '/api/qiniu/token' => array('method'=> array('get'),'call'=> '\App\Controllers\API\Qiniu:token','middles' => array()),
    '/api/qiniu/callback' => array('method'=> array('get'),'call'=> '\App\Controllers\API\Qiniu:callback','middles' => array()),
    '/api/uictl/dropdown' =>array('method'=> array('get'),'call'=> '\App\Controllers\API\UICtl:dropdown','middles' => array()),

    // 机构相关接口

    //添加或更新机构
    '/org_web/org/add_or_update' => [ // for super admin
        'method'  => ['post'],
        'call'    => '\App\Controllers\Org\Org:addOrUpdate',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //机构列表
    '/org_web/org/list' => [ // for super admin
        'method'  => ['get'],
        'call'    => '\App\Controllers\Org\Org:list',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //模糊搜索机构
    '/org_web/org/fuzzy_search' => [ // for super admin
        'method'  => ['get'],
        'call'    => '\App\Controllers\Org\Org:fuzzySearch',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //机构详情
    '/org_web/org/detail' => [ // for super admin
        'method'  => ['get'],
        'call'    => '\App\Controllers\Org\Org:detail',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //管理员可以查看所有老师，或者指定机构下老师
    '/teacher/teacher/list_by_org' => [ // for super admin
        'method'  => ['get'],
        'call'    => '\App\Controllers\Teacher\Teacher:listByOrg',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    '/teacher/teacher/info' => [ // 用于编辑前的查看
        'method'  => ['get'],
        'call'    => '\App\Controllers\Teacher\Teacher::info',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //管理员可以查看所有学生，或者指定机构下学生
    '/student/student/list_by_org' => [ // for super admin
        'method'  => ['get'],
        'call'    => '\App\Controllers\Student\Student:listByOrg',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //机构管理员绑定老师和学生
    '/org_web/teacher/bind_student' => [
        'method'  => ['post'],
        'call'    => '\App\Controllers\Teacher\Teacher:bindStudent',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    //机构管理员解绑老师和学生
    '/org_web/teacher/unbind_student' => [
        'method'  => ['post'],
        'call'    => '\App\Controllers\Teacher\Teacher:unbindStudent',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],

    '/teacher_wx/student/list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Student:get', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/collection_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentCollections', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/lesson_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/search_collections' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchCollections', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/search_lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/homework_demand' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getHomeworkDemand', array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/add' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Homework:add', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/teacher/register' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Teacher:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/teacher_wx/teacher/login' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),

    '/org_web/org/bind_unbind_student' => [
        'method'  => ['post'],
        'call'    => '\App\Controllers\Org\Org:bindUnbindStudent',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],
    '/org_web/org/bind_unbind_teacher' => [
        'method'  => ['post'],
        'call'    => '\App\Controllers\Org\Org:bindUnbindTeacher',
        'middles' => ['\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare'],
    ],

    // /boss/gift_code
    '/boss/gift_code/list' => [
        'method' => ['get'],
        'call' => GiftCode::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    '/boss/gift_code/add' => [
        'method' => ['post'],
        'call' => GiftCode::class . ':add',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    '/boss/gift_code/abandon' => [
        'method' => ['post'],
        'call' => GiftCode::class . ':abandon',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
);

/** @var App $app */
$app->add(function (Request $request, Response $response, $next) use ($app, $arr) {
    $uri = $request->getUri()->getPath();
    $startTime = Util::microtime_float();

    $method = $request->getMethod();
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== method: $method, path: $uri START =="]);

    $params = $request->getParams();
    $headers = $request->getHeaders();
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ['headers' => $headers, 'params' => $params]);

    if (!empty($arr[$uri])) {
        $r = $app->map($arr[$uri]['method'], $uri, $arr[$uri]['call']);
        if (!empty($arr[$uri]['middles']) && is_array($arr[$uri]['middles'])) {
            foreach ($arr[$uri]['middles'] as $middle)
                $r->add(new $middle($app->getContainer()));
        }
        //$r->add(new AfterMiddleware($app->getContainer()));
    }

    $response = $next($request, $response);
    $body = $response->getBody();
    // 只输出JSON数据
    if (preg_match('/^\{([\s\S].*)\}$/', $body)) {
        SimpleLogger::debug(__FILE__ . ':' . __LINE__, [
            '== RESPONSE ==' => json_decode($body, true)
        ]);
    }

    $endTime = Util::microtime_float();
    $t = $endTime - $startTime;
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== path: $uri END ({$t}) =="]);

    return $response;
});


