<?php

namespace App;

use App\Controllers\API\OSS;
use App\Controllers\Boss\GiftCode;
use App\Controllers\Employee\Employee;
use App\Controllers\Org\Org;
use App\Controllers\Schedule\ScheduleRecord;
use App\Controllers\Student\Student;
use App\Controllers\Teacher\Teacher;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Middleware\AppApiForStudent;
use App\Middleware\AppApiForTeacher;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\OrgAuthCheckMiddleWareForApp;
use App\Middleware\OrgResPrivilegeCheckMiddleWareForApp;
use App\Middleware\OrgTeacherAuthMiddleWareForApp;
use App\Middleware\StudentAuthCheckMiddleWareForApp;
use App\Controllers\StudentApp\App as StudentAppApp;
use App\Controllers\StudentApp\Auth as StudentAppAuth;
use App\Controllers\StudentApp\Opn as StudentAppOpn;
use App\Controllers\StudentApp\Play as StudentAppPlay;
use App\Controllers\StudentApp\Homework as StudentAppHomework;
use App\Controllers\StudentApp\Subscription as StudentAppSubscription;
use App\Controllers\TeacherApp\App as TeacherAppApp;
use App\Controllers\TeacherApp\Auth as TeacherAppAuth;
use App\Controllers\TeacherApp\Opn as TeacherAppOpn;
use App\Controllers\TeacherApp\Schedule as TeacherAppSchedule;
use App\Controllers\TeacherApp\Play as TeacherAppPlay;
use App\Controllers\TeacherApp\Org as TeacherAppOrg;
use App\Controllers\Org\OrgAccount as OrgAccount;
use App\Controllers\Student\PlayRecord as BackendPlayRecord;
use App\Controllers\Bill\Bill;
use App\Middleware\StudentResPrivilegeCheckMiddleWareForApp;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$arr = array(


    '/api/qiniu/token' => array('method'=> array('get'),'call'=> '\App\Controllers\API\Qiniu:token','middles' => array()),
    '/api/qiniu/callback' => array('method'=> array('get'),'call'=> '\App\Controllers\API\Qiniu:callback','middles' => array()),
    '/api/uictl/dropdown' =>array('method'=> array('get'),'call'=> '\App\Controllers\API\UICtl:dropdown','middles' => array()),

    '/api/oss/signature' => [
        'method' => ['get'],
        'call' => OSS::class . ':signature',
        'middles' => []
    ],

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
    '/boss/classroom/list_for_option' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //list,detail are for internal employee
    '/student/student/list' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //add,student,info,fuzzy_search are for org employee
    '/student/student/info' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:info','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/modify' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/add' => array('method'=> array('post'),'call'=> '\App\Controllers\Student\Student:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/student/student/fuzzy_search' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:fuzzySearch','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    // /student_app/auth
    '/student_app/auth/login' => [
        'method' => ['post'],
        'call' => StudentAppAuth::class . ':login',
        'middles' => [AppApiForStudent::class]
    ],
    '/student_app/auth/token_login' => [
        'method' => ['post'],
        'call' => StudentAppAuth::class . ':tokenLogin',
        'middles' => [AppApiForStudent::class]
    ],
    '/student_app/auth/validate_code' => [
        'method' => ['get'],
        'call' => StudentAppAuth::class . ':validateCode',
        'middles' => [AppApiForStudent::class]
    ],
    '/user/auth/get_user_id' => [ // musvg访问
        'method' => ['get'],
        'call' => StudentAppAuth::class . ':getUserId',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],

    // /student_app/app
    '/student_app/app/version' => [
        'method' => ['get'],
        'call' => StudentAppApp::class . ':version',
        'middles' => [AppApiForStudent::class]
    ],
    '/student_app/app/config' => [
        'method' => ['get'],
        'call' => StudentAppApp::class . ':config',
        'middles' => [AppApiForStudent::class]
    ],
    '/student_app/app/feedback' => [
        'method' => ['post'],
        'call' => StudentAppApp::class . ':feedback',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],

    // /student_app/sub
    '/student_app/subscription/redeem_gift_code' => [
        'method' => ['post'],
        'call' => StudentAppSubscription::class . ':redeemGiftCode',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],

    // /student_app/opn
    '/student_app/opn/categories' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':categories',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/opn/collections' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':collections',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/opn/lessons' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':lessons',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/opn/lesson' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':lesson',
        'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class, StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/opn/search' => [
        'method' => ['get'],
        'call' => StudentAppOpn::class . ':search',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],

    // /student_app/play
    '/student_app/play/save' => [
        'method' => ['get'],
        'call' => StudentAppPlay::class . ':save',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/play/end' => [
        'method' => ['post'],
        'call' => StudentAppPlay::class . ':end',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/play/ai_end' => [
        'method' => ['post'],
        'call' => StudentAppPlay::class . ':aiEnd',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],

    // /student_app/homework
    '/student_app/homework/record' => [
        'method' => ['get'],
        'call' => StudentAppHomework::class . ':practiceRecord',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],
    '/student_app/homework/list' => [
        'method' => ['get'],
        'call' => StudentAppHomework::class . ':list',
        'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
    ],

    // /teacher_app/auth/login
    '/teacher_app/auth/login' => [
        'method' => ['post'],
        'call' => TeacherAppAuth::class . ':login',
        'middles' => [AppApiForTeacher::class]
    ],
    '/teacher_app/auth/token_login' => [
        'method' => ['post'],
        'call' => TeacherAppAuth::class . ':tokenLogin',
        'middles' => [AppApiForTeacher::class]
    ],
    '/teacher_app/auth/send_verify_code' => [
        'method' => ['post'],
        'call' => TeacherAppAuth::class . ':validateCode',
        'middles' => [AppApiForTeacher::class]
    ],
    '/teacher_app/org/get_students' => [
        'method' => ['get'],
        'call' => TeacherAppOrg::class . ':getStudents',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/org/select_student' => [
        'method' => ['post'],
        'call' => TeacherAppOrg::class . ':selectStudent',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/org/teacher_list' => [
        'method' => ['get'],
        'call' => TeacherAppOrg::class . ':teacherList',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],

    // 爱学琴老师端
    '/teacher_app/opn/categories' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':categories',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/collections' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':collections',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/lessons' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':lessons',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/lesson' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':lesson',
        'middles' => [OrgResPrivilegeCheckMiddleWareForApp::class, OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/recent_collections' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':recentCollections',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/recent_lessons' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':recentLessons',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/lesson_resource' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':getLessonResource',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/opn/knowledge' => [
        'method' => ['get'],
        'call' => TeacherAppOpn::class . ':getKnowledge',
        'middles' => [OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/schedule/end' => [
        'method' => ['post'],
        'call' => TeacherAppSchedule::class . ':end',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/play/end' => [
        'method' => ['post'],
        'call' => TeacherAppPlay::class . ':end',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],
    '/teacher_app/play/ai_end' => [
        'method' => ['post'],
        'call' => TeacherAppPlay::class . ':aiEnd',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],

    '/teacher_app/app/version' => [
        'method' => ['get'],
        'call' => TeacherAppApp::class . ':version',
        'middles' => [AppApiForStudent::class]
    ],

    '/teacher_app/app/feedback' => [
        'method' => ['get'],
        'call' => TeacherAppApp::class . ':feedback',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],

    '/teacher_app/app/heart_beat' => [
        'method' => ['get'],
        'call' => TeacherAppApp::class . ':heartBeat',
        'middles' => [OrgTeacherAuthMiddleWareForApp::class,
            OrgAuthCheckMiddleWareForApp::class, AppApiForTeacher::class]
    ],

    '/goods/course/list' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/detail' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/edit' => array('method'=> array('post'),'call'=> '\App\Controllers\Course\Course:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/goods/course/add' => array('method'=> array('post'),'call'=> '\App\Controllers\Course\Course:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //机构管理后台使用，不希望看见课程列表菜单，但需要访问接口，所以新加一个接口
    '/goods/course/list_for_option' => array('method'=> array('get'),'call'=> '\App\Controllers\Course\Course:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    //list, updateEntry are for internal employee
    '/teacher/teacher/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\Teacher:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacher/updateEntry' => array('method'=>array('post'),'call'=>'\App\Controllers\Teacher\Teacher:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    //add,fuzzy_search is for org employee
    '/teacher/teacher/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Teacher\Teacher:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/teacher/teacher/fuzzy_search' => array('method'=>array('get'),'call'=>'\App\Controllers\Teacher\Teacher:fuzzySearch','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/area/area/getByParentCode' => array('method'=>array('get'),'call'=>'\App\Controllers\Area\Area:getByParentCode','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/area/area/getByCode' => array('method'=>array('get'),'call'=>'\App\Controllers\Area\Area:getByCode','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),


    '/schedule/task/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\STClass:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/copy' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\STClass:copySTClass','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/modify' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\STClass:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\STClass:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/detail' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\STClass:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/bindStudents' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ClassUser:bindStudents','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/bindTeachers' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ClassUser:bindTeachers','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/unbindUsers' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\ClassUser:unbindUsers','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/cancelST' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\STClass:cancelST','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/beginST' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\STClass:beginST','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/task/endST' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\STClass:endST','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/schedule/schedule/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\Schedule:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/detail' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\Schedule:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/modify' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/signIn' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:signIn','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/takeOff' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:takeOff','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/finish' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:finish','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/schedule/schedule/cancel' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:cancel','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    //学员上课记录
    '/schedule/schedule/attend_record' => [ // for org manager
        'method'  => ['get'],
        'call'    => ScheduleRecord::class . ':attendRecord',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],

    // 机构相关接口

    //添加或更新机构
    '/org_web/org/add_or_update' => [ // for super admin
        'method'  => ['post'],
        'call'    => Org::class . ':addOrUpdate',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //机构列表
    '/org_web/org/list' => [ // for super admin
        'method'  => ['get'],
        'call'    => Org::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //模糊搜索机构
    '/org_web/org/fuzzy_search' => [ // for super admin
        'method'  => ['get'],
        'call'    => Org::class . ':fuzzySearch',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //机构详情
    '/org_web/org/detail' => [ // for super admin
        'method'  => ['get'],
        'call'    => Org::class . ':detail',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //管理员可以查看所有老师，或者指定机构下老师
    '/teacher/teacher/list_by_org' => [ // for super admin
        'method'  => ['get'],
        'call'    => Teacher::class . ':listByOrg',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    '/teacher/teacher/info' => [ // 用于编辑前的查看
        'method'  => ['get'],
        'call'    => Teacher::class . '::info',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //管理员可以查看所有学生，或者指定机构下学生
    '/student/student/list_by_org' => [ // for super admin
        'method'  => ['get'],
        'call'    => Student::class . ':listByOrg',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //机构管理员绑定老师和学生
    '/org_web/teacher/bind_student' => [
        'method'  => ['post'],
        'call'    => Teacher::class . ':bindStudent',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    //机构管理员解绑老师和学生
    '/org_web/teacher/unbind_student' => [
        'method'  => ['post'],
        'call'    => Teacher::class . ':unbindStudent',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],

    '/teacher_wx/student/list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Student:get', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/collection_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentCollections', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/lesson_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/search_collections' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchCollections', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/search_lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/homework_demand' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getHomeworkDemand', array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/add' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Homework:add', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/teacher/register' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Teacher:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/teacher_wx/teacher/login' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/teacher_wx/homework/play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getHomeworkPlayRecordList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/homework/homework_record_detail' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getTaskDetail', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/teacher/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:sendSmsCode', 'middles' => array()),
    '/teacher_wx/teacher/teacher_org_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:getBindOrgList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/teacher/generate_invite_qr_code' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:getInviteQrCode', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/teacher_wx/schedule/student_schedule_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Schedule:scheduleList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),

    '/student_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/student_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/student_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:sendSmsCode', 'middles' => array()),
    '/student_wx/student/account_detail' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:accountDetail', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/student_wx/student/edit_account' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:editAccountInfo', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
    '/student_wx/student/day_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:recordReport', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/student/shared_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareReport', 'middles' => array()),
    '/student_wx/schedule/schedule_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Schedule:scheduleReport', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/common/js_config' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => array()),
    '/student_wx/play_record/wonderful_moment' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getWonderfulMomentUrl', 'middles' => array()),
    '/student_wx/play_record/play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getPlayRecordList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/schedule/schedule_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Schedule:scheduleList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/play_record/test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getLessonTestStatistics', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/play_record/ai_record_grade' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getAIRecordGrade', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/play_record/month_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthStatistics', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    '/student_wx/play_record/month_day_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthDayStatistics', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),

    '/org_web/org/bind_unbind_student' => [
        'method'  => ['post'],
        'call'    => Org::class . ':bindUnbindStudent',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],
    '/org_web/org/bind_unbind_teacher' => [
        'method'  => ['post'],
        'call'    => Org::class . ':bindUnbindTeacher',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
    ],

    // /boss/gift_code
    '/boss/gift_code/list' => [
        'method' => ['get'],
        'call' => GiftCode::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    // for org
    '/boss/gift_code/list_for_org' => [
        'method' => ['get'],
        'call' => GiftCode::class . ':listForOrg',
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
    //机构批量分配课管(course consultant)
    '/employee/employee/assign_cc' => [
        'method'  => ['post'],
        'call'    => Employee::class . ':assignCC',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //内部管理员查看机构账号列表
    '/org_web/org_account/list' => [
        'method'  => ['get'],
        'call'    => OrgAccount::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //内部管理员查看机构账号详情
    '/org_web/org_account/detail' => [
        'method'  => ['get'],
        'call'    => OrgAccount::class . ':detail',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //内部管理员修改机构账号
    '/org_web/org_account/modify' => [
        'method'  => ['post'],
        'call'    => OrgAccount::class . ':modify',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构管理员查看本机构下账号
    '/org_web/org_account/list_for_org' => [
        'method'  => ['get'],
        'call'    => OrgAccount::class . ':listForOrg',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构修改自己的机构密码(password in org_account)
    '/org_web/org_account/modify_password' => [
        'method'  => ['post'],
        'call'    => OrgAccount::class . ':modifyPassword',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构后台查询学生练习日报
    '/org_web/org/report_for_org' => [
        'method'  => ['get'],
        'call'    => BackendPlayRecord::class . ':reportForOrg',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构后台cc角色查看其负责的学生列表
    '/student/student/list_for_cc' => [
        'method'  => ['get'],
        'call'    => Student::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //内部用订单列表
    '/bill/bill/list' => [
        'method'  => ['get'],
        'call'    => Bill::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构用订单列表
    '/bill/bill/list_for_org' => [
        'method'  => ['get'],
        'call'    => Bill::class . ':listForOrg',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构添加订单
    '/bill/bill/add' => [
        'method'  => ['post'],
        'call'    => Bill::class . ':add',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //机构废除订单
    '/bill/bill/disable' => [
        'method'  => ['post'],
        'call'    => Bill::class . ':disable',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //老师学生二维码
    '/org_web/org/qrcode' => [
        'method'  => ['get'],
        'call'    => Org::class . ':qrcode',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //转介绍二维码
    '/org_web/org/referee_qrcode' => [
        'method'  => ['get'],
        'call'    => Org::class . ':refereeQrcode',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //请求机构cc列表，分配cc用
    '/employee/employee/cc_list' => [
        'method'  => ['get'],
        'call'    => Employee::class . ':CCList',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ],
    //外部机构的学生列表接口
    '/student/student/list_for_external' => [
        'method'  => ['get'],
        'call'    => Student::class . ':list',
        'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class]
    ]
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

    /** @var Response $response */
    $response = $next($request, $response);

    $body = (string)$response->getBody();
    SimpleLogger::debug(__FILE__ . ':' . __LINE__, [
        '== RESPONSE ==' => $body,
    ]);

    $endTime = Util::microtime_float();
    $t = $endTime - $startTime;
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== path: $uri END ({$t}) =="]);

    return $response;
});


