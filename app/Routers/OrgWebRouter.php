<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\API\OSS;
use App\Controllers\Boss\GiftCode;
use App\Controllers\Employee\Employee;
use App\Controllers\Org\Org;
use App\Controllers\Schedule\ScheduleRecord;
use App\Controllers\Student\Student;
use App\Controllers\Teacher\Teacher;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Controllers\Org\OrgAccount as OrgAccount;
use App\Controllers\Student\PlayRecord as BackendPlayRecord;
use App\Controllers\Bill\Bill;

class OrgWebRouter extends RouterBase
{
    protected $logFilename = 'dss_org_web.log';

    protected $uriConfig = [

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
        '/student/student/account_log' => array('method'=> array('get'),'call'=> '\App\Controllers\Student\Student:accountLog','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),


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
        '/schedule/task/searchName' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\STClass:searchName','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

        '/schedule/schedule/list' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\Schedule:list','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/detail' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\Schedule:detail','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/modify' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:modify','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/signIn' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:signIn','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/takeOff' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:takeOff','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/deduct' => array('method'=>array('get'),'call'=>'\App\Controllers\Schedule\Schedule:deduct','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/deductAmount' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:deductAmount','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/finish' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:finish','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/add' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:add','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
        '/schedule/schedule/cancel' => array('method'=>array('post'),'call'=>'\App\Controllers\Schedule\Schedule:cancel','middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

        //学员上课记录 1对1
        '/schedule/schedule/ai_attend_record' => [ // for org manager
            'method'  => ['get'],
            'call'    => ScheduleRecord::class . ':AIAttendRecord',
            'middles' => [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class],
        ],
        //非1对1上课记录
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
    ];

}