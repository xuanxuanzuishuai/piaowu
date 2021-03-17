<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\API\OSS;
use App\Controllers\API\UICtl;
use App\Controllers\Employee\Auth;
use App\Controllers\Employee\Employee;
use App\Controllers\OrgWeb\Agent;
use App\Controllers\OrgWeb\AppPush;
use App\Controllers\OrgWeb\Area;
use App\Controllers\OrgWeb\Bill;
use App\Controllers\OrgWeb\Channel;
use App\Controllers\OrgWeb\Dept;
use App\Controllers\OrgWeb\Message;
use App\Controllers\OrgWeb\Employee as OrgWebEmployee;
use App\Controllers\OrgWeb\EmployeeActivity;
use App\Controllers\OrgWeb\Package;
use App\Controllers\OrgWeb\SharePoster;
use App\Controllers\OrgWeb\StudentAccount;
use App\Controllers\Referral\Award;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\OrgWebMiddleware;

class OrgWebRouter extends RouterBase
{
    public $middleWares = [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class];
    protected $logFilename = 'operation_admin_web.log';
    protected $uriConfig = [

        '/api/uictl/dropdown' => ['method' => ['get'], 'call' => UICtl::class . ':dropdown', 'middles' => [OrgWebMiddleware::class]],
        '/api/oss/callback' => ['method' => ['post'], 'call' => OSS::class . ':callback', 'middles' => [OrgWebMiddleware::class]],
        '/api/oss/signature' => ['method' => ['get'], 'call' => OSS::class . ':signature', 'middles' => [OrgWebMiddleware::class]],

        '/employee/auth/tokenlogin' => ['method' => ['post'], 'call' => Auth::class . ':tokenlogin', 'middles' => [OrgWebMiddleware::class]],
        '/employee/auth/signout' => ['method' => ['post'], 'call' => Auth::class . ':signout', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],
        '/employee/auth/usercenterurl' => ['method' => ['get'], 'call' => Auth::class . ':usercenterurl', 'middles' => [OrgWebMiddleware::class]],

        '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list'),
        //list for org
        '/employee/employee/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:detail'),
        '/employee/employee/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:modify'),
        '/employee/employee/setPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setPwd'),
        '/employee/employee/userSetPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:userSetPwd'),
        '/employee/employee/getEmployeeListWithRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeListWithRole'),
        '/employee/employee/set_ding_mobile' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setDingMobile'),
        '/employee/employee/del_ding_mobile' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:delDingMobile'),
        //机构批量分配课管(course consultant)
        '/org_web/employee/user_external_information' => ['method' => ['post'], 'call' => Employee::class . ':userExternalInformation', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],
        '/org_web/employee/get_external_information' => ['method' => ['get'], 'call' => Employee::class . ':getExternalInformation', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],


        '/org_web/employee/get_dept_members' => [
            'method' => ['get'],
            'call' => OrgWebEmployee::class . ':getDeptMembers',
        ],
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


        '/area/area/getByParentCode' => array('method' => array('get'), 'call' => '\App\Controllers\Area\Area:getByParentCode'),
        '/area/area/getByCode' => array('method' => array('get'), 'call' => '\App\Controllers\Area\Area:getByCode'),

        // 部门
        '/org_web/dept/tree' => ['method' => ['get'], 'call' => Dept::class . ':tree'],
        '/org_web/dept/list' => ['method' => ['get'], 'call' => Dept::class . ':list'],
        '/org_web/dept/modify' => ['method' => ['post'], 'call' => Dept::class . ':modify'],
        '/org_web/dept/dept_privilege' => ['method' => ['get'], 'call' => Dept::class . ':deptPrivilege'],
        '/org_web/dept/privilege_modify' => ['method' => ['post'], 'call' => Dept::class . ':privilegeModify'],


        '/op_web/employee_activity/list'          => ['method' => ['get'], 'call' => EmployeeActivity::class . ':list'],
        '/op_web/employee_activity/detail'        => ['method' => ['get'], 'call' => EmployeeActivity::class . ':detail'],
        '/op_web/employee_activity/add'           => ['method' => ['post'], 'call' => EmployeeActivity::class . ':add'],
        '/op_web/employee_activity/modify'        => ['method' => ['post'], 'call' => EmployeeActivity::class . ':modify'],
        '/op_web/employee_activity/update_status' => ['method' => ['post'], 'call' => EmployeeActivity::class . ':updateStatus'],

        // 打卡截图审核：
        '/op_web/checkin_poster/list'     => ['method' => ['get'], 'call' => SharePoster::class . ':list'],
        '/op_web/checkin_poster/approved' => ['method' => ['post'], 'call' => SharePoster::class . ':approved'],
        '/op_web/checkin_poster/refused'  => ['method' => ['post'], 'call' => SharePoster::class . ':refused'],
        // 红包列表：
        '/op_web/referee/award_list'   => ['method' => ['get'], 'call' => Award::class . ':list'],
        '/op_web/referee/award_verify' => ['method' => ['get'], 'call' => Award::class . ':updateAward'],
        '/op_web/referee/config'       => ['method' => ['get'], 'call' => Award::class . ':config'],
        '/op_web/referee/receive_info' => ['method' => ['get'], 'call' => Award::class . ':receiveInfo'],

        // 消息：
        '/op_web/message/rules_list'         => ['method' => ['get'], 'call' => Message::class . ':rulesList'],
        '/op_web/message/rule_detail'        => ['method' => ['get'], 'call' => Message::class . ':ruleDetail'],
        '/op_web/message/rule_update_status' => ['method' => ['post'], 'call' => Message::class . ':ruleUpdateStatus'],
        '/op_web/message/rule_update'        => ['method' => ['post'], 'call' => Message::class . ':ruleUpdate'],

        // 手动推送：
        '/op_web/message/manual_last_push' => ['method' => ['get'], 'call' => Message::class . ':manualLastPush'],
        '/op_web/message/manual_push'      => ['method' => ['post'], 'call' => Message::class . ':manualPush'],

        // APP推送：
        '/op_web/app/push' => ['method' => ['post'], 'call' => AppPush::class . ':push'],
        '/op_web/app/push_list' => ['method' => ['get'], 'call' => AppPush::class . ':pushList'],
        '/op_web/app/download_push_user_template' => ['method' => ['get'], 'call' => AppPush::class . ':downloadPushUserTemplate'],
        // 代理管理后台
        '/op_web/agent/add' => ['method' => ['post'], 'call' => Agent::class . ':add'],
        '/op_web/agent/update' => ['method' => ['post'], 'call' => Agent::class . ':update'],
        '/op_web/agent/detail' => ['method' => ['get'], 'call' => Agent::class . ':detail'],
        '/op_web/agent/list' => ['method' => ['get'], 'call' => Agent::class . ':list'],
        '/op_web/agent/statics' => ['method' => ['get'], 'call' => Agent::class . ':agentStaticsData'],
        '/op_web/agent/freeze' => ['method' => ['post'], 'call' => Agent::class . ':freezeAgent'],
        '/op_web/agent/unfreeze' => ['method' => ['post'], 'call' => Agent::class . ':unfreezeAgent'],
        '/op_web/agent/recommend_users' => ['method' => ['get'], 'call' => Agent::class . ':recommendUsersList'],
        '/op_web/agent/recommend_bills' => ['method' => ['get'], 'call' => Agent::class . ':recommendBillsList'],
        '/op_web/agent/apply_list' => ['method' => ['get'], 'call' => Agent::class . ':applyList'],
        '/op_web/agent/apply_remark' => ['method' => ['post'], 'call' => Agent::class . ':applyRemark'],
        '/op_web/agent/popular_material' => ['method' => ['post'], 'call' => Agent::class . ':popularMaterial'],
        '/op_web/agent/popular_material_info' => ['method' => ['get'], 'call' => Agent::class . ':popularMaterialInfo'],
        '/op_web/agent/fuzzy_search' => ['method' => ['get'], 'call' => Agent::class . ':agentFuzzySearch'],
        '/op_web/agent/division_to_package' => ['method' => ['get'], 'call' => Agent::class . ':agentDivisionToPackage'],
        // 地址搜索国家/省/市/县（区）
        '/op_web/area/country' => ['method' => ['get'], 'call' => Area::class . ':countryList'],
        '/op_web/area/province' => ['method' => ['get'], 'call' => Area::class . ':provinceList'],
        '/op_web/area/city' => ['method' => ['get'], 'call' => Area::class . ':cityList'],
        '/op_web/area/district' => ['method' => ['get'], 'call' => Area::class . ':districtList'],
        // 课包管理
        '/op_web/package/search' => ['method' => ['get'], 'call' => Package::class . ':search'],
        '/op_web/package/new_package' => ['method' => ['get'], 'call' => Package::class . ':getNewPackage'],
        '/op_web/package/list' => ['method' => ['get'], 'call' => Package::class . ':packageList'],

        // 第三方订单管理
        '/op_web/bill/third_bill_list' => ['method' => ['get'], 'call' => Bill::class . ':thirdBillList'],
        '/op_web/bill/third_bill_import' => ['method' => ['post'], 'call' => Bill::class . ':thirdBillImport'],
        '/op_web/bill/download_template' => ['method' => ['get'], 'call' => Bill::class . ':thirdBillDownloadTemplate'],
        // 渠道管理
        '/op_web/channel/getChannels' => ['method' => ['get'], 'call' => Channel::class . ':getChannels'],

        // 批量导入学生积分奖励
        '/op_web/student_account/batchImportRewardPoints' => ['method' => ['post'], 'call' => StudentAccount::class . ':batchImportRewardPoints'],
        // 查询学生积分奖励导入状态信息
        '/op_web/student_account/importRewardPointsInfo' => ['method' => ['get'], 'call' => StudentAccount::class . ':importRewardPointsInfo'],
        // 获取导入学生积分奖励列表
        '/op_web/student_account/importRewardPointsList' => ['method' => ['get'], 'call' => StudentAccount::class . ':importRewardPointsList'],
        // 获取批量发放积分导入模板地址
        '/op_web/student_account/download_template' => ['method' => ['get'], 'call' => StudentAccount::class . ':batchImportRewardPointsTemplate'],
    ];
}