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
use App\Controllers\OrgWeb\ActivitySign;
use App\Controllers\OrgWeb\Admin;
use App\Controllers\OrgWeb\Agent;
use App\Controllers\OrgWeb\AgentOrg;
use App\Controllers\OrgWeb\AgentStorage;
use App\Controllers\OrgWeb\AppPush;
use App\Controllers\OrgWeb\Area;
use App\Controllers\OrgWeb\Bill;
use App\Controllers\OrgWeb\Channel;
use App\Controllers\OrgWeb\CountingActivity;
use App\Controllers\OrgWeb\Dept;
use App\Controllers\OrgWeb\LandingRecall;
use App\Controllers\OrgWeb\Message;
use App\Controllers\OrgWeb\Employee as OrgWebEmployee;
use App\Controllers\OrgWeb\EmployeeActivity;
use App\Controllers\OrgWeb\MonthActivity;
use App\Controllers\OrgWeb\Opn;
use App\Controllers\OrgWeb\Package;
use App\Controllers\OrgWeb\PosterTemplateWord;
use App\Controllers\OrgWeb\RtActivity;
use App\Controllers\OrgWeb\SharePoster;
use App\Controllers\OrgWeb\SourceMaterial;
use App\Controllers\OrgWeb\StudentAccount;
use App\Controllers\OrgWeb\WeekActivity;
use App\Controllers\Referral\Award;
use App\Middleware\AdminLogMiddleware;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\OrgWebMiddleware;
use App\Controllers\OrgWeb\PosterTemplate;

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
        '/employee/employee/getEmployeeRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeRole'),
        '/employee/employee/fuzzy_search' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:fuzzySearch'),
        '/employee/employee/assistant_list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getAssistantList'),

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

        //转介绍管理 - 个性化海报,标准海报,文案
        '/org_web/poster_template/individualityList' => ['method' => ['get'], 'call' => PosterTemplate::class . ':individualityList'],
        '/org_web/poster_template/standardList' => ['method' => ['get'], 'call' => PosterTemplate::class . ':standardList'],
        '/org_web/poster_template/individualityAdd' => ['method' => ['post'], 'call' => PosterTemplate::class . ':individualityAdd'],
        '/org_web/poster_template/standardAdd' => ['method' => ['post'], 'call' => PosterTemplate::class . ':standardAdd'],
        '/org_web/poster_template/getPosterInfo' => ['method' => ['get'], 'call' => PosterTemplate::class . ':getPosterInfo'],
        '/org_web/poster_template/editPosterInfo' => ['method' => ['post'], 'call' => PosterTemplate::class . ':editPosterInfo'],
        '/org_web/poster_template/offlinePosterCheck' => ['method' => ['get'], 'call' => PosterTemplate::class . ':offlinePosterCheck'],
        '/org_web/poster_template_word/addWord' => ['method' => ['post'], 'call' => PosterTemplateWord::class . ':addWord'],
        '/org_web/poster_template_word/wordList' => ['method' => ['get'], 'call' => PosterTemplateWord::class . ':wordList'],
        '/org_web/poster_template_word/getWordInfo' => ['method' => ['get'], 'call' => PosterTemplateWord::class . ':getWordInfo'],
        '/org_web/poster_template_word/editWordInfo' => ['method' => ['post'], 'call' => PosterTemplateWord::class . ':editWordInfo'],


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
        '/op_web/agent/agent_recommend_bills' => ['method' => ['get'], 'call' => Agent::class . ':agentRecommendDuplicationBills'],
        '/op_web/agent/apply_list' => ['method' => ['get'], 'call' => Agent::class . ':applyList'],
        '/op_web/agent/apply_remark' => ['method' => ['post'], 'call' => Agent::class . ':applyRemark'],
        '/op_web/agent/popular_material' => ['method' => ['post'], 'call' => Agent::class . ':popularMaterial'],
        '/op_web/agent/popular_material_info' => ['method' => ['get'], 'call' => Agent::class . ':popularMaterialInfo'],
        '/op_web/agent/fuzzy_search' => ['method' => ['get'], 'call' => Agent::class . ':agentFuzzySearch'],
        '/op_web/agent/division_to_package' => ['method' => ['get'], 'call' => Agent::class . ':agentDivisionToPackage'],
        '/op_web/agent/package_relation_agent' => ['method' => ['get'], 'call' => Agent::class . ':agentRelationToPackage'],
        '/op_web/agent/update_package_relation_agent' => ['method' => ['post'], 'call' => Agent::class . ':updateAgentRelationToPackage'],

        // 代理商预存订单
        '/op_web/agent_storage/add' => ['method' => ['post'], 'call' => AgentStorage::class . ':add'],
        '/op_web/agent_storage/update' => ['method' => ['post'], 'call' => AgentStorage::class . ':update'],
        '/op_web/agent_storage/list' => ['method' => ['get'], 'call' => AgentStorage::class . ':list'],
        '/op_web/agent_storage/detail' => ['method' => ['get'], 'call' => AgentStorage::class . ':detail'],
        '/op_web/agent_storage/approval' => ['method' => ['post'], 'call' => AgentStorage::class . ':approval'],
        '/op_web/agent_storage/process_log' => ['method' => ['get'], 'call' => AgentStorage::class . ':processLog'],

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
        // DEV: 创建验证码
        '/op_web/admin/sms_code' => ['method' => ['post'], 'call' => Admin::class . ':smsCode', 'middles' => []],

        // event事件列表
        '/op_web/event/task_list' => ['method' => ['get'], 'call' => WeekActivity::class . ':eventTaskList', 'middles' => [EmployeeAuthCheckMiddleWare::class]],

        // 周周有奖
        '/op_web/week_activity/save' => ['method' => ['post'], 'call' => WeekActivity::class . ':save'],
        '/op_web/week_activity/list' => ['method' => ['get'], 'call' => WeekActivity::class . ':list'],
        '/op_web/week_activity/detail' => ['method' => ['get'], 'call' => WeekActivity::class . ':detail'],
        '/op_web/week_activity/enable_status' => ['method' => ['post'], 'call' => WeekActivity::class . ':editEnableStatus'],
        '/op_web/week_activity/send_msg' => ['method' => ['post'], 'call' => WeekActivity::class . ':sendMsg'],
        '/op_web/week_activity/push_weixin_msg' => ['method' => ['post'], 'call' => WeekActivity::class . ':pushWeixinMsg'],
        // 月月有奖
        '/op_web/month_activity/save' => ['method' => ['post'], 'call' => MonthActivity::class . ':save'],
        '/op_web/month_activity/list' => ['method' => ['get'], 'call' => MonthActivity::class . ':list'],
        '/op_web/month_activity/detail' => ['method' => ['get'], 'call' => MonthActivity::class . ':detail'],
        '/op_web/month_activity/enable_status' => ['method' => ['post'], 'call' => MonthActivity::class . ':editEnableStatus'],


        //代理退款
        '/op_web/agent_storage/refund_add' => ['method' => ['post'], 'call' => AgentStorage::class . ':refundAdd'],
        '/op_web/agent_storage/refund_update' => ['method' => ['post'], 'call' => AgentStorage::class . ':refundUpdate'],
        '/op_web/agent_storage/refund_verify' => ['method' => ['post'], 'call' => AgentStorage::class . ':refundVerify'],
        '/op_web/agent_storage/refund_detail' => ['method' => ['get'], 'call' => AgentStorage::class . ':refundDetail'],
        '/op_web/agent_storage/refund_list' => ['method' => ['get'], 'call' => AgentStorage::class . ':refundList'],

        //曲谱教材
        '/op_web/opn/drop_down_search' => ['method' => ['get'], 'call' => Opn::class . ':dropDownSearch'],

        //代理商机构
        '/op_web/agent_org/opn_relation' => ['method' => ['post'], 'call' => AgentOrg::class . ':orgOpnRelation'],
        '/op_web/agent_org/opn_list' => ['method' => ['get'], 'call' => AgentOrg::class . ':orgOpnList'],
        '/op_web/agent_org/opn_del_relation' => ['method' => ['post'], 'call' => AgentOrg::class . ':orgOpnDelRelation'],
        '/op_web/agent_org/statics' => ['method' => ['get'], 'call' => AgentOrg::class . ':orgStaticsData'],

        '/op_web/agent_org/student_add' => ['method' => ['post'], 'call' => AgentOrg::class . ':studentAdd'],
        '/op_web/agent_org/student_list' => ['method' => ['get'], 'call' => AgentOrg::class . ':studentList'],
        '/op_web/agent_org/student_del' => ['method' => ['post'], 'call' => AgentOrg::class . ':studentDel'],
        '/op_web/agent_org/student_import' => ['method' => ['post'], 'call' => AgentOrg::class . ':studentImport'],

        //素材库管理
        '/op_web/source_material/source_save'      => ['method' => ['post'], 'call' => SourceMaterial::class . ':sourceSave'],
        '/op_web/source_material/source_list'     => ['method' => ['post'], 'call' => SourceMaterial::class . ':sourceList'],
        '/op_web/source_material/enable_status'     => ['method' => ['post'], 'call' => SourceMaterial::class . ':editEnableStatus'],
        '/op_web/source_material/source_type_add' => ['method' => ['post'], 'call' => SourceMaterial::class . ':sourceTypeAdd'],
        '/op_web/source_material/source_type_list' => ['method' => ['get'], 'call' => SourceMaterial::class . ':sourceTypeList'],

        // 亲友优惠券活动管理
        '/op_web/rt_activity/save' => ['method' => ['post'], 'call' => RtActivity::class . ':save'],
        '/op_web/rt_activity/save_remark' => ['method' => ['post'], 'call' => RtActivity::class . ':saveRemark'],
        '/op_web/rt_activity/list' => ['method' => ['get'], 'call' => RtActivity::class . ':list'],
        '/op_web/rt_activity/detail' => ['method' => ['get'], 'call' => RtActivity::class . ':detail'],
        '/op_web/rt_activity/enable_status' => ['method' => ['post'], 'call' => RtActivity::class . ':editEnableStatus'],
        
        // 亲友优惠券活动明细
        '/op_web/rt_activity/activity_info_list' => ['method' => ['post'], 'call' => RtActivity::class . ':activityInfoList'],

        //统计活动
        '/op_web/counting_activity/create'=>['method'=>['post'],'call'=>CountingActivity::class . ':create'],
        '/op_web/counting_activity/detail'=>['method'=>['get'],'call'=>CountingActivity::class . ':getCountingActivityDetail'],
        '/op_web/counting_activity/list'=>['method'=>['get'],'call'=>CountingActivity::class . ':getActivityList'],
        '/op_web/counting_activity/editStatus'=>['method'=>['post'],'call'=>CountingActivity::class . ':editStatus'],
        '/op_web/counting_activity/editActivity'=>['method'=>['post'],'call'=>CountingActivity::class . ':editActivity'],
        '/op_web/logistics/goods_list'=>['method'=>['get'],'call'=>CountingActivity::class . ':getAwardList'],
        // 周周领奖-计数任务
        '/op_web/activity_sign/list' => ['method' => ['get'], 'call' => ActivitySign::class . ':list'],
        '/op_web/activity_sign/list_export' => ['method' => ['get'], 'call' => ActivitySign::class . ':listExport'],
        '/op_web/activity_sign/user_list' => ['method' => ['get'], 'call' => ActivitySign::class . ':userList'],

        // Landing页召回
        '/op_web/landing_recall/ext_info' => ['method' => ['get'], 'call' => LandingRecall::class . ':extInfo'],
        '/op_web/landing_recall/save' => ['method' => ['post'], 'call' => LandingRecall::class . ':save'],
        '/op_web/landing_recall/list' => ['method' => ['get'], 'call' => LandingRecall::class . ':list'],
        '/op_web/landing_recall/detail' => ['method' => ['get'], 'call' => LandingRecall::class . ':detail'],
        '/op_web/landing_recall/enable_status' => ['method' => ['post'], 'call' => LandingRecall::class . ':editEnableStatus'],
        '/op_web/landing_recall/send_msg' => ['method' => ['post'], 'call' => LandingRecall::class . ':sendMsg'],
        '/op_web/landing_recall/send_count' => ['method' => ['get'], 'call' => LandingRecall::class . ':sendCount'],
        
    ];
}