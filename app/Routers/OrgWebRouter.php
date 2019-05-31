<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\API\OSS;
use App\Controllers\API\Qiniu;
use App\Controllers\API\UICtl;
use App\Controllers\Bill\Bill;
use App\Controllers\Boss\GiftCode;
use App\Controllers\Employee\Auth;
use App\Controllers\Employee\Employee;
use App\Controllers\Org\Org;
use App\Controllers\Org\OrgAccount as OrgAccount;
use App\Controllers\Org\OrgLicense;
use App\Controllers\OrgWeb\Erp;
use App\Controllers\Schedule\ScheduleRecord;
use App\Controllers\Student\PlayRecord as BackendPlayRecord;
use App\Controllers\Student\Student;
use App\Controllers\Teacher\Teacher;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\ErpMiddleware;
use App\Middleware\OrgWebMiddleware;

class OrgWebRouter extends RouterBase
{
    public $middleWares = [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class];
    protected $logFilename = 'dss_org_web.log';
    protected $uriConfig = [

        '/api/qiniu/token' => [
            'method' => ['get'],
            'call' => Qiniu::class . ':token',
            'middles' => [OrgWebMiddleware::class]
        ],
        '/api/qiniu/callback' => [
            'method' => ['get'],
            'call' => Qiniu::class . ':callback',
            'middles' => [OrgWebMiddleware::class]
        ],
        '/api/uictl/dropdown' => [
            'method' => ['get'],
            'call' => UICtl::class . ':dropdown',
            'middles' => [OrgWebMiddleware::class]
        ],

        '/api/oss/signature' => [
            'method' => ['get'],
            'call' => OSS::class . ':signature',
            'middles' => [OrgWebMiddleware::class]
        ],

        '/employee/auth/tokenlogin' => [
            'method' => ['post'],
            'call' => Auth::class . ':tokenlogin',
            'middles' => [OrgWebMiddleware::class]
        ],
        '/employee/auth/signout' => [
            'method' => ['post'],
            'call' => Auth::class . ':signout',
            'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]
        ],
        '/employee/auth/usercenterurl' => [
            'method' => ['get'],
            'call' => Auth::class . ':usercenterurl',
            'middles' => [OrgWebMiddleware::class]
        ],

        '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list'),
        //list for org
        '/employee/employee/list_for_org' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:listForOrg'),
        '/employee/employee/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:detail'),
        '/employee/employee/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:modify'),
        '/employee/employee/setPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setPwd'),
        '/employee/employee/userSetPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:userSetPwd'),
        '/employee/employee/getEmployeeListWithRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeListWithRole'),

        '/privilege/privilege/employee_menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:employee_menu'),
        '/privilege/privilege/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:list'),
        '/privilege/privilege/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:detail'),
        '/privilege/privilege/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Privilege:modify'),
        '/privilege/privilege/menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:menu'),

        '/privilege/privilegeGroup/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:list'),
        '/privilege/privilegeGroup/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:detail'),
        '/privilege/privilegeGroup/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:modify'),

        '/privilege/role/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:list'),
        '/privilege/role/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:detail'),
        '/privilege/role/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Role:modify'),
        //list for org
        '/privilege/role/list_for_org' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:listForOrg'),

        '/boss/campus/list' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Campus:list'),
        '/boss/campus/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Campus:detail'),
        '/boss/campus/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Campus:modify'),
        '/boss/campus/add' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Campus:add'),

        '/boss/classroom/list' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:list'),
        '/boss/classroom/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:detail'),
        '/boss/classroom/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Classroom:modify'),
        '/boss/classroom/add' => array('method' => array('post'), 'call' => '\App\Controllers\Boss\Classroom:add'),
        '/boss/classroom/list_for_option' => array('method' => array('get'), 'call' => '\App\Controllers\Boss\Classroom:list'),
        //list,detail are for internal employee
        '/student/student/list' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:list'),
        '/student/student/add_follow_remark' => array('method' => array('post'), 'call' => '\App\Controllers\Student\FollowRemark:add'),
        '/student/student/get_follow_remark' => array('method' => array('get'), 'call' => '\App\Controllers\Student\FollowRemark:lookOver'),
        //add,student,info,fuzzy_search are for org employee
        '/student/student/info' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:info'),
        '/student/student/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Student\Student:modify'),
        '/student/student/add' => array('method' => array('post'), 'call' => '\App\Controllers\Student\Student:add'),
        '/student/student/fuzzy_search' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:fuzzySearch'),
        '/student/student/account_log' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:accountLog'),


        '/goods/course/list' => array('method' => array('get'), 'call' => '\App\Controllers\Course\Course:list'),
        '/goods/course/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Course\Course:detail'),
        '/goods/course/edit' => array('method' => array('post'), 'call' => '\App\Controllers\Course\Course:modify'),
        '/goods/course/add' => array('method' => array('post'), 'call' => '\App\Controllers\Course\Course:add'),
        //机构管理后台使用，不希望看见课程列表菜单，但需要访问接口，所以新加一个接口
        '/goods/course/list_for_option' => array('method' => array('get'), 'call' => '\App\Controllers\Course\Course:list'),

        //list, updateEntry are for internal employee
        '/teacher/teacher/list' => array('method' => array('get'), 'call' => '\App\Controllers\Teacher\Teacher:list'),
        '/teacher/teacher/updateEntry' => array('method' => array('post'), 'call' => '\App\Controllers\Teacher\Teacher:modify'),
        //add,fuzzy_search is for org employee
        '/teacher/teacher/add' => array('method' => array('post'), 'call' => '\App\Controllers\Teacher\Teacher:add'),
        '/teacher/teacher/fuzzy_search' => array('method' => array('get'), 'call' => '\App\Controllers\Teacher\Teacher:fuzzySearch'),

        '/area/area/getByParentCode' => array('method' => array('get'), 'call' => '\App\Controllers\Area\Area:getByParentCode'),
        '/area/area/getByCode' => array('method' => array('get'), 'call' => '\App\Controllers\Area\Area:getByCode'),


        '/schedule/task/add' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\STClass:add'),
        '/schedule/task/copy' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\STClass:copySTClass'),
        '/schedule/task/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\STClass:modify'),
        '/schedule/task/list' => array('method' => array('get'), 'call' => '\App\Controllers\Schedule\STClass:list'),
        '/schedule/task/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Schedule\STClass:detail'),
        '/schedule/task/bindStudents' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\ClassUser:bindStudents'),
        '/schedule/task/bindTeachers' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\ClassUser:bindTeachers'),
        '/schedule/task/unbindUsers' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\ClassUser:unbindUsers'),
        '/schedule/task/cancelST' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\STClass:cancelST'),
        '/schedule/task/beginST' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\STClass:beginST'),
        '/schedule/task/endST' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\STClass:endST'),
        '/schedule/task/searchName' => array('method' => array('get'), 'call' => '\App\Controllers\Schedule\STClass:searchName'),

        '/schedule/schedule/list' => array('method' => array('get'), 'call' => '\App\Controllers\Schedule\Schedule:list'),
        '/schedule/schedule/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Schedule\Schedule:detail'),
        '/schedule/schedule/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:modify'),
        '/schedule/schedule/signIn' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:signIn'),
        '/schedule/schedule/takeOff' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:takeOff'),
        '/schedule/schedule/deduct' => array('method' => array('get'), 'call' => '\App\Controllers\Schedule\Schedule:deduct'),
        '/schedule/schedule/deductAmount' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:deductAmount'),
        '/schedule/schedule/finish' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:finish'),
        '/schedule/schedule/add' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:add'),
        '/schedule/schedule/cancel' => array('method' => array('post'), 'call' => '\App\Controllers\Schedule\Schedule:cancel'),

        //学员上课记录 1对1
        '/schedule/schedule/ai_attend_record' => [ // for org manager
            'method' => ['get'],
            'call' => ScheduleRecord::class . ':AIAttendRecord',

        ],
        //非1对1上课记录
        '/schedule/schedule/attend_record' => [ // for org manager
            'method' => ['get'],
            'call' => ScheduleRecord::class . ':attendRecord',

        ],

        // 机构相关接口

        //添加或更新机构
        '/org_web/org/add_or_update' => [ // for super admin
            'method' => ['post'],
            'call' => Org::class . ':addOrUpdate',

        ],
        //机构列表
        '/org_web/org/list' => [ // for super admin
            'method' => ['get'],
            'call' => Org::class . ':list',

        ],
        //模糊搜索机构
        '/org_web/org/fuzzy_search' => [ // for super admin
            'method' => ['get'],
            'call' => Org::class . ':fuzzySearch',

        ],
        //机构详情
        '/org_web/org/detail' => [ // for super admin
            'method' => ['get'],
            'call' => Org::class . ':detail',

        ],
        //管理员可以查看所有老师，或者指定机构下老师
        '/teacher/teacher/list_by_org' => [ // for super admin
            'method' => ['get'],
            'call' => Teacher::class . ':listByOrg',

        ],
        '/teacher/teacher/info' => [ // 用于编辑前的查看
            'method' => ['get'],
            'call' => Teacher::class . '::info',

        ],
        //管理员可以查看所有学生，或者指定机构下学生
        '/student/student/list_by_org' => [ // for super admin
            'method' => ['get'],
            'call' => Student::class . ':listByOrg',

        ],
        //机构管理员绑定老师和学生
        '/org_web/teacher/bind_student' => [
            'method' => ['post'],
            'call' => Teacher::class . ':bindStudent',

        ],
        //机构管理员解绑老师和学生
        '/org_web/teacher/unbind_student' => [
            'method' => ['post'],
            'call' => Teacher::class . ':unbindStudent',

        ],


        '/org_web/org/bind_unbind_student' => [
            'method' => ['post'],
            'call' => Org::class . ':bindUnbindStudent',

        ],
        '/org_web/org/bind_unbind_teacher' => [
            'method' => ['post'],
            'call' => Org::class . ':bindUnbindTeacher',

        ],

        // /boss/gift_code
        '/boss/gift_code/list' => [
            'method' => ['get'],
            'call' => GiftCode::class . ':list',

        ],
        // for org
        '/boss/gift_code/list_for_org' => [
            'method' => ['get'],
            'call' => GiftCode::class . ':listForOrg',

        ],
        '/boss/gift_code/add' => [
            'method' => ['post'],
            'call' => GiftCode::class . ':add',

        ],
        '/boss/gift_code/abandon' => [
            'method' => ['post'],
            'call' => GiftCode::class . ':abandon',

        ],
        //机构批量分配课管(course consultant)
        '/employee/employee/assign_cc' => [
            'method' => ['post'],
            'call' => Employee::class . ':assignCC',

        ],
        //内部管理员查看机构账号列表
        '/org_web/org_account/list' => [
            'method' => ['get'],
            'call' => OrgAccount::class . ':list',

        ],
        //内部管理员查看机构账号详情
        '/org_web/org_account/detail' => [
            'method' => ['get'],
            'call' => OrgAccount::class . ':detail',

        ],
        //内部管理员修改机构账号
        '/org_web/org_account/modify' => [
            'method' => ['post'],
            'call' => OrgAccount::class . ':modify',

        ],
        //机构管理员查看本机构下账号
        '/org_web/org_account/list_for_org' => [
            'method' => ['get'],
            'call' => OrgAccount::class . ':listForOrg',

        ],
        //机构修改自己的机构密码(password in org_account)
        '/org_web/org_account/modify_password' => [
            'method' => ['post'],
            'call' => OrgAccount::class . ':modifyPassword',

        ],
        //机构后台查询学生练习日报
        '/org_web/org/report_for_org' => [
            'method' => ['get'],
            'call' => BackendPlayRecord::class . ':reportForOrg',

        ],
        //机构后台cc角色查看其负责的学生列表
        '/student/student/list_for_cc' => [
            'method' => ['get'],
            'call' => Student::class . ':list',

        ],
        //内部用订单列表
        '/bill/bill/list' => [
            'method' => ['get'],
            'call' => Bill::class . ':list',

        ],
        //订单详情
        '/bill/bill/detail' => [
            'method' => ['get'],
            'call' => Bill::class . ':detail',

        ],
        //机构用订单列表
        '/bill/bill/list_for_org' => [
            'method' => ['get'],
            'call' => Bill::class . ':listForOrg',

        ],
        //机构添加订单
        '/bill/bill/add' => [
            'method' => ['post'],
            'call' => Bill::class . ':add',

        ],
        //机构废除订单
        '/bill/bill/disable' => [
            'method' => ['post'],
            'call' => Bill::class . ':disable',

        ],
        //老师学生二维码
        '/org_web/org/qrcode' => [
            'method' => ['get'],
            'call' => Org::class . ':qrcode',

        ],
        //转介绍二维码
        '/org_web/org/referee_qrcode' => [
            'method' => ['get'],
            'call' => Org::class . ':refereeQrcode',

        ],
        //请求机构cc列表，分配cc用
        '/employee/employee/cc_list' => [
            'method' => ['get'],
            'call' => Employee::class . ':CCList',

        ],
        //外部机构的学生列表接口
        '/student/student/list_for_external' => [
            'method' => ['get'],
            'call' => Student::class . ':list',

        ],
        //学生渠道列表
        '/org_web/org/channel_list' => [
            'method' => ['get'],
            'call'   => Org::class . ':channelList',
        ],
        //机构许可证，创建
        '/org_web/org_license/create' => [
            'method' => ['post'],
            'call'   => OrgLicense::class . ':create',
        ],
        //机构许可证，废除
        '/org_web/org_license/disable' => [
            'method' => ['post'],
            'call'   => OrgLicense::class . ':disable',
        ],
        //机构许可证，激活
        '/org_web/org_license/active' => [
            'method' => ['post'],
            'call'   => OrgLicense::class . ':active',
        ],
        //机构许可证，列表
        '/org_web/org_license/list' => [
            'method' => ['get'],
            'call'   => OrgLicense::class . ':list',
        ],

        '/org_web/erp/exchange_gift_code' => [
            'method' => ['post'],
            'call' => Erp::class . ':exchangeGiftCode',
            'middles' => [ErpMiddleware::class]
        ],
    ];
}