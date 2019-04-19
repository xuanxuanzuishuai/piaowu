<?php

namespace App;

use App\Libs\SimpleLogger;
use App\Libs\Util;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$arr = array(

    '/employee/auth/tokenlogin' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Auth:tokenlogin', 'middles' => array()),
    '/employee/auth/signout' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Auth:signout', 'middles' => array('\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/auth/usercenterurl' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Auth:usercenterurl', 'middles' => array()),

    '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
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

    '/boss/campus/list' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Campus:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/campus/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Campus:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/campus/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Campus:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/campus/add' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Campus:add', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/boss/classroom/list' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/classroom/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/classroom/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Classroom:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/boss/classroom/add' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Classroom:add', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/student/student/list' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/student_detail' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/student_modify' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/add_student' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/get_channels' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:getSChannels','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/batch_assign_cc' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:batchAssignCC','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    // 从ai_peilian_backend迁移至此 TODO:与dss_crm风格保持一致
    '/user/play/end' => array('method'=> array('post'),'call'=> '\App\Controllers\StudentApp\Play:PlayEnd','middles' => array('\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/user/play/save' => array('method'=> array('post'),'call'=> '\App\Controllers\StudentApp\Play:PlaySave','middles' => array('\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student_app/play/ai_end' => array('method'=> array('post'),'call'=> '\App\Controllers\StudentApp\Play:AiPlayEnd','middles' => array()),
    '/student_app/homework/record' => array('method'=> array('get'),'call'=> '\App\Controllers\StudentApp\Homework:HomeworkPracticeRecord','middles' => array()),
    '/student_app/homework/list' => array('method'=> array('get'),'call'=> '\App\Controllers\StudentApp\Homework:HomeworkList','middles' => array()),

    '/goods/course/list' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/detail' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/edit' => array('method'=> array('post'),'call'=> '\App\Controllers\Course\Course:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/add' => array('method'=> array('post'),'call'=> '\App\Controllers\Course\Course:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/teacher/teacher/detail' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\Teacher:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacher/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\Teacher:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacher/updateEntry' => array('method'=>array('post'),'call'=>'\App\Controllers\Teacher\Teacher:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacher/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Teacher\Teacher:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacherTags/tag_list' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\TeacherTag:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/area/area/getByParentCode' => array('method'=>array('get'),'call'=>'\App\Controllers\Area\Area:getByParentCode','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/area/area/getByCode' => array('method'=>array('get'),'call'=>'\App\Controllers\Area\Area:getByCode','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

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
    //管理员可以查看所有老师，或者指定机构下老师
    '/teacher/teacher/list_by_org' => [ // for super admin
        'method'  => ['get'],
        'call'    => '\App\Controllers\Teacher\Teacher:listByOrg',
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

    '/teacher_wx/student/list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Student:get', 'middles' => array()),
    '/teacher_wx/homework/collection_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentCollections', 'middles' => array()),
    '/teacher_wx/homework/lesson_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentLessons', 'middles' => array()),
    '/teacher_wx/homework/search_collections' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchCollections', 'middles' => array()),
    '/teacher_wx/homework/search_lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchLessons', 'middles' => array()),
    '/teacher_wx/homework/lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getLessons', 'middles' => array()),
    '/teacher_wx/homework/homework_demand' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getHomeworkDemand', 'middles' => array()),
    '/teacher_wx/homework/add' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Homework:add', 'middles' => array()),
);

/** @var App $app */
$app->add(function (Request $request, Response $response, $next) use ($app, $arr) {
    $uri = $request->getUri()->getPath();
    $startTime = Util::microtime_float();

    $method = $request->getMethod();
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== method: $method, path: $uri START =="]);

    if (!empty($arr[$uri])) {
        $r = $app->map($arr[$uri]['method'], $uri, $arr[$uri]['call']);
        if (!empty($arr[$uri]['middles']) && is_array($arr[$uri]['middles'])) {
            foreach ($arr[$uri]['middles'] as $middle)
                $r->add(new $middle($app->getContainer()));
        }
        //$r->add(new AfterMiddleware($app->getContainer()));
    }

    $response = $next($request, $response);
    $endTime = Util::microtime_float();
    $t = $endTime - $startTime;
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== path: $uri END ({$t}) =="]);

    return $response;
});


