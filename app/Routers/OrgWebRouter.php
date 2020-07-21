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
use App\Controllers\ClassV1\ClassV1;
use App\Controllers\Employee\Auth;
use App\Controllers\Employee\Employee;
use App\Controllers\Org\Org;
use App\Controllers\Org\OrgAccount as OrgAccount;
use App\Controllers\Org\OrgLicense;
use App\Controllers\OrgWeb\Activity;
use App\Controllers\OrgWeb\Admin;
use App\Controllers\OrgWeb\Approval;
use App\Controllers\OrgWeb\AutoReply;
use App\Controllers\OrgWeb\Banner;
use App\Controllers\OrgWeb\CommunityRedPack;
use App\Controllers\OrgWeb\CommunitySharePoster;
use App\Controllers\OrgWeb\Dept;
use App\Controllers\OrgWeb\Erp;
use App\Controllers\OrgWeb\Flags;
use App\Controllers\OrgWeb\PersonalLink;
use App\Controllers\OrgWeb\PlayRecord;
use App\Controllers\OrgWeb\PosterTemplate;
use App\Controllers\OrgWeb\PosterTemplateWord;
use App\Controllers\OrgWeb\Question;
use App\Controllers\OrgWeb\QuestionTag;
use App\Controllers\OrgWeb\Referral;
use App\Controllers\OrgWeb\ReviewCourse;
use App\Controllers\OrgWeb\SharePoster;
use App\Controllers\OrgWeb\Employee as OrgWebEmployee;
use App\Controllers\Bill\ThirdPartBill;
use App\Controllers\Schedule\ScheduleRecord;
use App\Controllers\Student\PlayRecord as BackendPlayRecord;
use App\Controllers\Student\Student;
use App\Controllers\Teacher\Teacher;
use App\Controllers\WeChatCS\WeChatCS;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\ErpMiddleware;
use App\Middleware\OrgWebMiddleware;
use App\Controllers\OrgWeb\Poster;
use App\Controllers\OrgWeb\WechatConfig;
use App\Controllers\OrgWeb\Collection;
use App\Controllers\OrgWeb\Package;
use App\Controllers\OrgWeb\Student as OrgWebStudent;
use App\Controllers\OrgWeb\Faq;
use App\Controllers\OrgWeb\StudentCertificate;

class OrgWebRouter extends RouterBase
{
    public $middleWares = [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class];
    protected $logFilename = 'dss_org_web.log';
    protected $uriConfig = [

        '/api/qiniu/token' => ['method' => ['get'], 'call' => Qiniu::class . ':token', 'middles' => [OrgWebMiddleware::class]],
        '/api/qiniu/callback' => ['method' => ['get'], 'call' => Qiniu::class . ':callback', 'middles' => [OrgWebMiddleware::class]],
        '/api/uictl/dropdown' => ['method' => ['get'], 'call' => UICtl::class . ':dropdown', 'middles' => [OrgWebMiddleware::class]],
        '/api/oss/signature' => ['method' => ['get'], 'call' => OSS::class . ':signature', 'middles' => [OrgWebMiddleware::class]],
        '/api/oss/callback' => ['method' => ['post'], 'call' => OSS::class . ':callback', 'middles' => [OrgWebMiddleware::class]],


        '/employee/auth/tokenlogin' => ['method' => ['post'], 'call' => Auth::class . ':tokenlogin', 'middles' => [OrgWebMiddleware::class]],
        '/employee/auth/signout' => ['method' => ['post'], 'call' => Auth::class . ':signout', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],
        '/employee/auth/usercenterurl' => ['method' => ['get'], 'call' => Auth::class . ':usercenterurl', 'middles' => [OrgWebMiddleware::class]],


        '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list'),
        //list for org
        '/employee/employee/list_for_org' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:listForOrg'),
        '/employee/employee/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:detail'),
        '/employee/employee/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:modify'),
        '/employee/employee/setPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setPwd'),
        '/employee/employee/userSetPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:userSetPwd'),
        '/employee/employee/getEmployeeListWithRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeListWithRole'),
        //机构批量分配课管(course consultant)
        '/employee/employee/assign_cc' => ['method' => ['post'], 'call' => Employee::class . ':assignCC'],
        //请求机构cc列表，分配cc用
        '/employee/employee/cc_list' => ['method' => ['get'], 'call' => Employee::class . ':CCList'],

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
        //外部机构的学生列表接口
        '/student/student/list_for_external' => ['method' => ['get'], 'call' => Student::class . ':list'],
        // 管理员可以查看所有学生，或者指定机构下学生 for super admin
        '/student/student/list_by_org' => ['method' => ['get'], 'call' => Student::class . ':listByOrg'],
        //机构后台cc角色查看其负责的学生列表
        '/student/student/list_for_cc' => ['method' => ['get'], 'call' => Student::class . ':list'],
        //新学生管理功能接口，列表、详情等
        '/student/student/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:detail'),
        '/student/student/updateAddAssistantStatus' => array('method' => array('post'), 'call' => '\App\Controllers\Student\Student:updateAddAssistantStatus'),
        '/student/student/searchList' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:searchList'),
        '/student/student/allotCollection' => array('method' => array('post'), 'call' => '\App\Controllers\Student\Student:allotCollection'),
        '/student/student/allotAssistant' => array('method' => array('post'), 'call' => '\App\Controllers\Student\Student:allotAssistant'),
        '/org_web/channel/getChannels' => array('method' => array('get'), 'call' => '\App\Controllers\Student\Student:getSChannels'),
        '/org_web/student/syncDataToCrm' => ['method' => ['post'], 'call' => OrgWebStudent::class . ':syncDataToCrm'],
        '/org_web/student/allot_course_manage' => ['method' => ['post'], 'call' => OrgWebStudent::class . ':allotCourseManage'],
        '/org_web/student/student_mobile' => ['method' => ['get'], 'call' => OrgWebStudent::class . ':getStudentMobile'],
        '/org_web/student/fuzzy_search_student' => ['method' => ['get'], 'call' => OrgWebStudent::class . ':fuzzySearchStudent'],
        // 学生跟进记录
        '/student/student_remark/add' => array('method' => array('post'), 'call' => '\App\Controllers\Student\StudentRemark:add'),
        '/student/student_remark/remark_list' => array('method' => array('get'), 'call' => '\App\Controllers\Student\StudentRemark:remarkList'),
        '/student/student/updateAddWeChatAccount' => array('method' => array('post'), 'call' => '\App\Controllers\Student\Student:updateAddWeChatAccount'),

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
        // 管理员可以查看所有老师，或者指定机构下老师 for super admin
        '/teacher/teacher/list_by_org' => ['method' => ['get'], 'call' => Teacher::class . ':listByOrg'],
        // 用于编辑前的查看
        '/teacher/teacher/info' => ['method' => ['get'], 'call' => Teacher::class . '::info'],


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
        //学员上课记录 1对1 for org manager
        '/schedule/schedule/ai_attend_record' => ['method' => ['get'], 'call' => ScheduleRecord::class . ':AIAttendRecord',],
        //非1对1上课记录 for org manager
        '/schedule/schedule/attend_record' => ['method' => ['get'], 'call' => ScheduleRecord::class . ':attendRecord',],


        // 机构相关接口
        // 添加或更新机构 for super admin
        '/org_web/org/add_or_update' => ['method' => ['post'], 'call' => Org::class . ':addOrUpdate'],
        // 机构列表 for super admin
        '/org_web/org/list' => ['method' => ['get'], 'call' => Org::class . ':list'],
        // 模糊搜索机构 for super admin
        '/org_web/org/fuzzy_search' => ['method' => ['get'], 'call' => Org::class . ':fuzzySearch'],
        // 机构详情 for super admin
        '/org_web/org/detail' => [ 'method' => ['get'], 'call' => Org::class . ':detail'],
        '/org_web/org/bind_unbind_student' => ['method' => ['post'], 'call' => Org::class . ':bindUnbindStudent'],
        '/org_web/org/bind_unbind_teacher' => ['method' => ['post'], 'call' => Org::class . ':bindUnbindTeacher'],
        //老师学生二维码
        '/org_web/org/qrcode' => ['method' => ['get'], 'call' => Org::class . ':qrcode'],
        //转介绍二维码
        '/org_web/org/referee_qrcode' => ['method' => ['get'], 'call' => Org::class . ':refereeQrcode'],
        //学生渠道列表
        '/org_web/org/channel_list' => ['method' => ['get'], 'call' => Org::class . ':channelList'],
        //机构后台查询学生练习日报
        '/org_web/org/report_for_org' => ['method' => ['get'], 'call' => BackendPlayRecord::class . ':reportForOrg'],


        //机构管理员绑定老师和学生
        '/org_web/teacher/bind_student' => ['method' => ['post'], 'call' => Teacher::class . ':bindStudent'],
        //机构管理员解绑老师和学生
        '/org_web/teacher/unbind_student' => ['method' => ['post'], 'call' => Teacher::class . ':unbindStudent'],


        // /boss/gift_code
        '/boss/gift_code/list' => ['method' => ['get'], 'call' => GiftCode::class . ':list'],
        // for org
        '/boss/gift_code/list_for_org' => ['method' => ['get'], 'call' => GiftCode::class . ':listForOrg'],
        '/boss/gift_code/add' => ['method' => ['post'], 'call' => GiftCode::class . ':add'],
        '/boss/gift_code/abandon' => ['method' => ['post'], 'call' => GiftCode::class . ':abandon'],
        '/boss/gift_code/abandon_for_org' => ['method' => ['post'], 'call' => GiftCode::class . ':abandonForOrg'],


        //内部管理员查看机构账号列表
        '/org_web/org_account/list' => ['method' => ['get'], 'call' => OrgAccount::class . ':list'],
        //内部管理员查看机构账号详情
        '/org_web/org_account/detail' => ['method' => ['get'], 'call' => OrgAccount::class . ':detail'],
        //内部管理员修改机构账号
        '/org_web/org_account/modify' => ['method' => ['post'], 'call' => OrgAccount::class . ':modify'],
        //机构管理员查看本机构下账号
        '/org_web/org_account/list_for_org' => ['method' => ['get'], 'call' => OrgAccount::class . ':listForOrg'],
        //机构修改自己的机构密码(password in org_account)
        '/org_web/org_account/modify_password' => ['method' => ['post'], 'call' => OrgAccount::class . ':modifyPassword'],


        //内部用订单列表
        '/bill/bill/list' => ['method' => ['get'], 'call' => Bill::class . ':list'],
        //订单详情
        '/bill/bill/detail' => ['method' => ['get'], 'call' => Bill::class . ':detail'],
        //机构用订单列表
        '/bill/bill/list_for_org' => ['method' => ['get'], 'call' => Bill::class . ':listForOrg'],
        //机构添加订单
        '/bill/bill/add' => ['method' => ['post'], 'call' => Bill::class . ':add'],
        //机构废除订单
        '/bill/bill/disable' => ['method' => ['post'], 'call' => Bill::class . ':disable'],
        // 已审核订单导出
        '/bill/bill/exportBill' => ['method' => ['get'], 'call' => Bill::class . ':exportBill'],
        // 课消数据导出
        '/bill/bill/exportReduce' => ['method' => ['get'], 'call' => Bill::class . ':exportReduce'],
        // 从有赞等第三方渠道导入流水号
        '/bill/third_part_bill/import' => ['method' => ['post'], 'call' => ThirdPartBill::class . ':import'],
        '/bill/third_part_bill/list' => ['method' => ['get'], 'call' => ThirdPartBill::class . ':list'],
        '/bill/third_part_bill/download_template' => ['method' => ['get'], 'call' => ThirdPartBill::class . ':downloadTemplate'],

        //机构许可证，创建
        '/org_web/org_license/create' => ['method' => ['post'], 'call' => OrgLicense::class . ':create'],
        //机构许可证，废除
        '/org_web/org_license/disable' => ['method' => ['post'], 'call' => OrgLicense::class . ':disable'],
        //机构许可证，激活
        '/org_web/org_license/active' => ['method' => ['post'], 'call' => OrgLicense::class . ':active'],
        //机构许可证，列表
        '/org_web/org_license/list' => ['method' => ['get'], 'call' => OrgLicense::class . ':list'],


        '/org_web/erp/exchange_gift_code' => ['method' => ['post'], 'call' => Erp::class . ':exchangeGiftCode', 'middles' => [ErpMiddleware::class]],
        '/org_web/erp/abandon_gift_code' => ['method' => ['post'], 'call' => Erp::class . ':abandonGiftCode', 'middles' => [ErpMiddleware::class]],
        '/org_web/erp/student_gift_code' => ['method' => ['get'], 'call' => Erp::class . ':studentGiftCode', 'middles' => [ErpMiddleware::class]],
        '/org_web/erp/gift_code_transfer' => ['method' => ['post'], 'call' => Erp::class . ':giftCodeTransfer', 'middles' => [ErpMiddleware::class]],
        '/org_web/erp/ai_play/play_list' => ['method' => ['get'], 'call' => Erp::class . ':recentPlayed', 'middles' => [ErpMiddleware::class]],
        '/org_web/erp/ai_play/play_detail' => ['method' => ['get'], 'call' => Erp::class . ':recentDetail', 'middles' => [ErpMiddleware::class]],


        '/org_web/approval/add_config' => ['method' => ['post'], 'call' => Approval::class . ':addConfig',],
        '/org_web/approval/discard_config' => ['method' => ['post'], 'call' => Approval::class . ':discardConfig'],
        '/org_web/approval/revoke' => ['method' => ['post'], 'call' => Approval::class . ':revoke'],
        '/org_web/approval/approve' => ['method' => ['post'], 'call' => Approval::class . ':approve'],
        //待审核列表
        '/org_web/approval/list' => ['method' => ['get'], 'call' => Approval::class . ':list'],
        //审批配置列表
        '/org_web/approval/config_list' => ['method' => ['get'], 'call' => Approval::class . ':configList'],


        // 标签
        '/org_web/flags/list' => ['method' => ['get'], 'call' => Flags::class . ':list'],
        '/org_web/flags/add' => ['method' => ['post'], 'call' => Flags::class . ':add'],
        '/org_web/flags/modify' => ['method' => ['post'], 'call' => Flags::class . ':modify'],
        '/org_web/flags/student_flags_modify' => ['method' => ['post'], 'call' => Flags::class . ':studentFlagsModify'],
        '/org_web/flags/valid_flags' => ['method' => ['get'], 'call' => Flags::class . ':validFlags'],


        // 过滤器
        '/org_web/flags/filter_list' => ['method' => ['get'], 'call' => Flags::class . ':filterList'],
        '/org_web/flags/filter_add' => ['method' => ['post'], 'call' => Flags::class . ':filterAdd'],
        '/org_web/flags/filter_modify' => ['method' => ['post'], 'call' => Flags::class . ':filterModify'],


        // 后台管理
        '/org_web/admin/fake_sms_code' => ['method' => ['post'], 'call' => Admin::class . ':fakeSMSCode'],

        // 考级模拟题
        '/org_web/question/add_edit'     => ['method' => ['post'], 'call' => Question::class . ':addEdit'],
        '/org_web/question/detail'       => ['method' => ['get'], 'call' => Question::class . ':detail'],
        '/org_web/question/list'         => ['method' => ['get'], 'call' => Question::class . ':list'],
        '/org_web/question/status'       => ['method' => ['post'], 'call' => Question::class . ':status'],
        '/org_web/question/catalog'      => ['method' => ['get'], 'call' => Question::class . ':catalog'],
        '/org_web/question_tag/add_edit' => ['method' => ['post'], 'call' => QuestionTag::class . ':addEdit'],
        '/org_web/question_tag/tags'     => ['method' => ['get'], 'call' => QuestionTag::class . ':tags'], //所有状态正常的标签

        // 点评课
        '/org_web/review_course/students' => ['method' => ['get'], 'call' => ReviewCourse::class . ':students'],
        '/org_web/review_course/student_reports' => ['method' => ['get'], 'call' => ReviewCourse::class . ':studentReports'],
        '/org_web/review_course/student_report_detail' => ['method' => ['get'], 'call' => ReviewCourse::class . ':studentReportDetail'],
        '/org_web/review_course/student_report_detail_dynamic' => ['method' => ['get'], 'call' => ReviewCourse::class . ':studentReportDetailDynamic'],
        '/org_web/review_course/student_report_detail_ai' => ['method' => ['get'], 'call' => ReviewCourse::class . ':studentReportDetailAI'],
        '/org_web/review_course/student_report_detail_class' => ['method' => ['get'], 'call' => ReviewCourse::class . ':studentReportDetailClass'],
        '/org_web/review_course/simple_review' => ['method' => ['post'], 'call' => ReviewCourse::class . ':simpleReview'],
        '/org_web/review_course/tasks' => ['method' => ['get'], 'call' => ReviewCourse::class . ':tasks'],
        '/org_web/review_course/config' => ['method' => ['get'], 'call' => ReviewCourse::class . ':config'],
        '/org_web/review_course/play_detail' => ['method' => ['get'], 'call' => ReviewCourse::class . ':playDetail'],
        '/org_web/review_course/upload_review_audio' => ['method' => ['post'], 'call' => ReviewCourse::class . ':uploadReviewAudio'],
        '/org_web/review_course/send_review' => ['method' => ['post'], 'call' => ReviewCourse::class . ':sendReview'],

        // 点评课推广页微信客服
        '/org_web/review_course/get_wechatcs_list' => ['method' => ['get'], 'call' => WeChatCS::class . ':getWeChatCSList'],
        '/org_web/review_course/add_wechatcs' => ['method' => ['post'], 'call' => WeChatCS::class . ':addWeChatCS'],
        '/org_web/review_course/set_wechatcs' => ['method' => ['post'], 'call' => WeChatCS::class . ':setWeChatCS'],

        // 班级添加
        '/classv1/class/add' => ['method' => ['post'], 'call' => ClassV1::class . ':add'],
        '/classv1/class/list' => ['method' => ['get'], 'call' => ClassV1::class . ':list'],
        '/classv1/class/detail' => ['method'=> ['get'], 'call' => ClassV1::class . ':detail'],
        '/classv1/class/modify' => ['method'=> ['post'], 'call' => ClassV1::class . ':modify'],

        // 转介绍
        '/org_web/referral/config' => ['method' => ['get'], 'call' => Referral::class . ':config'],
        '/org_web/referral/referred_list' => ['method' => ['get'], 'call' => Referral::class . ':referredList'],
        '/org_web/referral/award_list' => ['method' => ['get'], 'call' => Referral::class . ':awardList'],
        '/org_web/referral/update_award' => ['method' => ['post'], 'call' => Referral::class . ':updateAward'],

        //转介绍海报
        '/org_web/poster/add' => ['method' => ['post'], 'call' => Poster::class . ':add'],
        '/org_web/poster/list' => ['method' => ['get'], 'call' => Poster::class . ':list'],
        '/org_web/poster/detail' => ['method'=> ['get'], 'call' => Poster::class . ':detail'],
        '/org_web/poster/modify' => ['method'=> ['post'], 'call' => Poster::class . ':modify'],

        //学生公众号数据配置
        '/org_web/wechat_config/set' => ['method' => ['post'], 'call' => WechatConfig::class . ':setWechatOfficeConfig'],
        '/org_web/wechat_config/detail' => ['method' => ['get'], 'call' => WechatConfig::class . ':detail'],
        '/org_web/wechat_config/list' => ['method' => ['get'], 'call' => WechatConfig::class . ':list'],
        '/org_web/wechat_config/send_message' => ['method' => ['post'], 'call' => WechatConfig::class . ':sendSelfMessage'],

        //学生集合
        '/org_web/collection/add' => ['method' => ['post'], 'call' => Collection::class . ':add'],
        '/org_web/collection/detail' => ['method' => ['get'], 'call' => Collection::class . ':detail'],
        '/org_web/collection/modify' => ['method' => ['post'], 'call' => Collection::class . ':modify'],
        '/org_web/collection/list' => ['method' => ['get'], 'call' => Collection::class . ':list'],
        '/org_web/collection/assistant_list' => ['method' => ['get'], 'call' => Collection::class . ':getAssistantList'],
        '/org_web/collection/total_list' => ['method' => ['get'], 'call' => Collection::class . ':totalList'],
        '/org_web/collection/getCollectionDropDownList' => ['method' => ['get'], 'call' => Collection::class . ':getCollectionDropDownList'],
        '/org_web/collection/getCollectionPackageList' => ['method' => ['get'], 'call' => Collection::class . ':getCollectionPackageList'],
        '/org_web/collection/reAllotCollectionAssistant' => ['method' => ['post'], 'call' => Collection::class . ':reAllotCollectionAssistant'],
        '/org_web/collection/event_task_list' => ['method' => ['get'], 'call' => Collection::class . ':getEventTasksList'],

        //课包管理接口
        '/org_web/package/packageDictDetail' => ['method' => ['get'], 'call' => Package::class . ':packageDictDetail'],
        '/org_web/package/packageDictEdit' => ['method' => ['post'], 'call' => Package::class . ':packageDictEdit'],

        //banner管理接口
        '/org_web/banner/list' => ['method' => ['get'], 'call' => Banner::class . ':list'],
        '/org_web/banner/add' => ['method' => ['post'], 'call' => Banner::class . ':add'],
        '/org_web/banner/detail' => ['method' => ['get'], 'call' => Banner::class . ':detail'],
        '/org_web/banner/edit' => ['method' => ['post'], 'call' => Banner::class . ':edit'],

        // 转介绍活动
        '/org_web/activity/event' => ['method' => ['get'], 'call' => Activity::class . ':eventTasks'],
        '/org_web/activity/list' => ['method' => ['get'], 'call' => Activity::class . ':list'],
        '/org_web/activity/detail' => ['method' => ['get'], 'call' => Activity::class . ':detail'],
        '/org_web/activity/add' => ['method' => ['post'], 'call' => Activity::class . ':add'],
        '/org_web/activity/modify' => ['method' => ['post'], 'call' => Activity::class . ':modify'],
        '/org_web/activity/update_status' => ['method' => ['post'], 'call' => Activity::class . ':updateStatus'],
        '/org_web/activity/send_msg' => ['method' => ['post'], 'call' => Activity::class . ':sendMsg'],
        '/org_web/activity/push_weixin_msg' => ['method' => ['post'], 'call' => Activity::class . ':pushWeixinMsg'],
        '/org_web/share_poster/list' => ['method' => ['get'], 'call' => SharePoster::class . ':list'],
        '/org_web/share_poster/approved' => ['method' => ['post'], 'call' => SharePoster::class . ':approved'],
        '/org_web/share_poster/refused' => ['method' => ['post'], 'call' => SharePoster::class . ':refused'],

        '/org_web/play_record/statistics' => ['method' => ['get'], 'call' => PlayRecord::class . ':playStatistics'],
        '/org_web/poster_template/individualityList' => ['method' => ['get'], 'call' => PosterTemplate::class . ':individualityList'],
        '/org_web/poster_template/standardList' => ['method' => ['get'], 'call' => PosterTemplate::class . ':standardList'],
        '/org_web/poster_template/individualityAdd' => ['method' => ['post'], 'call' => PosterTemplate::class . ':individualityAdd'],
        '/org_web/poster_template/standardAdd' => ['method' => ['post'], 'call' => PosterTemplate::class . ':standardAdd'],
        '/org_web/poster_template/getPosterInfo' => ['method' => ['get'], 'call' => PosterTemplate::class . ':getPosterInfo'],
        '/org_web/poster_template/editPosterInfo' => ['method' => ['post'], 'call' => PosterTemplate::class . ':editPosterInfo'],
        '/org_web/poster_template_word/addWord' => ['method' => ['post'], 'call' => PosterTemplateWord::class . ':addWord'],
        '/org_web/poster_template_word/wordList' => ['method' => ['get'], 'call' => PosterTemplateWord::class . ':wordList'],
        '/org_web/poster_template_word/getWordInfo' => ['method' => ['get'], 'call' => PosterTemplateWord::class . ':getWordInfo'],
        '/org_web/poster_template_word/editWordInfo' => ['method' => ['post'], 'call' => PosterTemplateWord::class . ':editWordInfo'],


        //社群返现
        '/org_web/return_money_poster/list' => ['method' => ['get'], 'call' => CommunitySharePoster::class . ':list'],
        '/org_web/return_money_poster/approved' => ['method' => ['post'], 'call' => CommunitySharePoster::class . ':approved'],
        '/org_web/return_money_poster/refused' => ['method' => ['post'], 'call' => CommunitySharePoster::class . ':refused'],

        '/org_web/community_red_pack/config' => ['method' => ['get'], 'call' => CommunityRedPack::class . ':config'],
        '/org_web/community_red_pack/awardList' => ['method' => ['get'], 'call' => CommunityRedPack::class . ':awardList'],
        '/org_web/community_red_pack/updateAward' => ['method' => ['post'], 'call' => CommunityRedPack::class . ':updateAward'],
        //话术管理
        '/org_web/faq/add' => ['method' => ['post'], 'call' => Faq::class . ':add'],
        '/org_web/faq/modify' => ['method' => ['post'], 'call' => Faq::class . ':modify'],
        '/org_web/faq/detail' => ['method' => ['get'], 'call' => Faq::class . ':detail'],
        '/org_web/faq/list' => ['method' => ['get'], 'call' => Faq::class . ':list'],
        '/org_web/faq/search' => ['method' => ['get'], 'call' => Faq::class . ':search','middles'=>[]],

        // 产品包管理
        '/org_web/package/list' => ['method' => ['get'], 'call' => Package::class . ':list'],
        '/org_web/package/edit' => ['method' => ['post'], 'call' => Package::class . ':edit'],

        // 部门
        '/org_web/dept/tree' => ['method' => ['get'], 'call' => Dept::class . ':tree'],
        '/org_web/dept/list' => ['method' => ['get'], 'call' => Dept::class . ':list'],
        '/org_web/dept/modify' => ['method' => ['post'], 'call' => Dept::class . ':modify'],
        '/org_web/dept/dept_privilege' => ['method' => ['get'], 'call' => Dept::class . ':deptPrivilege'],
        '/org_web/dept/privilege_modify' => ['method' => ['post'], 'call' => Dept::class . ':privilegeModify'],

        // 学生账户
        '/org_web/student/accounts' => ['method' => ['get'], 'call' => Student::class . ':getStudentAccounts'],
        '/org_web/student/account_detail' => ['method' => ['get'], 'call' => Student::class . ':getStudentAccountDetail'],

        //微信自动回复
        '/org_web/auto_replay/question_edit' => ['method' => ['post'], 'call' => AutoReply::class . ':questionEdit'],
        '/org_web/auto_replay/question_add' => ['method' => ['post'], 'call' => AutoReply::class . ':questionAdd'],
        '/org_web/auto_replay/question_one' => ['method' => ['get'], 'call' => AutoReply::class . ':questionOne'],

        '/org_web/auto_replay/answer_add' => ['method' => ['post'], 'call' => AutoReply::class . ':answerAdd'],
        '/org_web/auto_replay/answer_edit' => ['method' => ['post'], 'call' => AutoReply::class . ':answerEdit'],
        '/org_web/auto_replay/answer_one' => ['method' => ['get'], 'call' => AutoReply::class . ':answerOne'],

        '/org_web/auto_replay/question_list' => ['method' => ['get'], 'call' => AutoReply::class . ':list'],

        // 学生证书管理
        '/org_web/student_certificate/add' => ['method' => ['post'], 'call' => StudentCertificate::class . ':add'],
        // 专属售卖链接
        '/org_web/personal_link/list' => ['method' => ['get'], 'call' => PersonalLink::class . ':list'],
        '/org_web/personal_link/create' => ['method' => ['post'], 'call' => PersonalLink::class . ':create']
    ];
}