<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/8
 * Time: 6:00 PM
 */

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
$db = MysqlDB::getDB();

global $roles;
$roles = $db->select('role', '*');
$roles = array_combine(array_column($roles, 'id'), $roles);

global $groups;
$groups = $db->select('group', '*');
$groups = array_combine(array_column($groups, 'id'), $groups);

$groupPrivileges = $db->select('group_privilege', '*');
global $groupPrivilegeList;
$groupPrivilegeList = [];
foreach ($groupPrivileges as $gp) {
    $groupPrivilegeList[$gp['group_id']][] = $gp['privilege_id'];
}

global $privileges;
$privileges = $db->select('privilege', '*');
$privileges = array_combine(array_column($privileges, 'id'), $privileges);

$info = [
    'role_cnt' => count($roles),
    'group_cnt' => count($groups),
    'group_privilege_cnt' => count($groupPrivileges),
    'privilege_cnt' => count($privileges),
];

//print_r($info);

/*
 * 构建角色的菜单
 *
use:
sudo -u www-data php ./privilege_check.php 17

ROLE: [17] 机构管理员
[324 | task] 教务管理
    [84 | course_list] 课程单元列表 URI:/goods/course/list
    [325 | task_list] 班级列表 URI:/schedule/task/list
    [351 | task_schedule_list] 班级课次列表 URI:/schedule/schedule/list
[344 | org_manage] 机构管理
    [330 | org_teacher_list] 老师列表 URI:/teacher/teacher/list
    [332 | org_student_list] 学生列表 URI:/student/student/list
    [352 | org_student_attend_record] 学员上课记录 URI:/schedule/schedule/attend_record
    [360 | org_account_list_for_org] 机构账号列表 URI:/org_web/org_account/list_for_org
    [362 | org_play_record_daily_report] 学员练习日报 URI:/org_web/org/report_for_org
    [367 | campus_list] 校区列表 URI:/boss/campus/list
    [388 | classroom_list] 教室列表 URI:/boss/classroom/list
[52 | employee_management] 员工管理
    [343 | org_employee_list] 员工列表 URI:/employee/employee/list_for_org
[228 | activationCode] 激活码中心
    [349 | org_code_list] 激活码列表 URI:/boss/gift_code/list_for_org
[57 | bill_management] 订单管理
    [363 | bill_org_list] 订单列表 URI:/bill/bill/list_for_org
 */
function buildGroupMenu($groupId, &$root) {
    global $groupPrivilegeList;
    global $privileges;
    $privilegeIds = $groupPrivilegeList[$groupId];


    foreach ($privilegeIds as $privilegeId) {
        $privilege = $privileges[$privilegeId];
        if (!$privilege['is_menu']) { continue; }
        if (!empty($privilege['parent_id'])) {
            $root[$privilege['parent_id']][] = $privilegeId;
        }
    }
}

function buildRoleMenu($roleId) {
    global $roles;
    $role = $roles[$roleId];
    $groupIds = explode(',', $role['group_ids']);

    print("ROLE: [{$role['id']}] {$role['name']}\n");

    $roleMenu = [];
    foreach ($groupIds as $groupId) {
        buildGroupMenu($groupId, $roleMenu);
    }
    return $roleMenu;
}

function dumpMenu($root) {
    global $privileges;
    foreach ($root as $mainId => $main) {
        $mainPrivilege = $privileges[$mainId];
        print("[{$mainPrivilege['id']} | {$mainPrivilege['unique_en_name']}] {$mainPrivilege['menu_name']}\n");
        foreach ($main as $subId) {
            $subPrivilege = $privileges[$subId];
            print("    [{$subPrivilege['id']} | {$subPrivilege['unique_en_name']}] {$subPrivilege['menu_name']} URI:{$subPrivilege['uri']}\n");
        }
    }
}

$roleId = $argv[1] ?? -1;
dumpMenu(buildRoleMenu($roleId));


/********************************************/


function dumpGroupById($groupId) {
    global $groups;
    $group = $groups[$groupId];
    print(" [GROUP {$groupId}]: {$group['name']}] ");
}

global $invalidPrivilegeIds;
$invalidPrivilegeIds = [];

function dumpGroupPrivilege($groupId) {
    global $groupPrivilegeList;
    global $privileges;
    global $invalidPrivilegeIds;
    foreach ($groupPrivilegeList[$groupId] as $privilegeId) {
        $privilege = $privileges[$privilegeId];
        if (!empty($privilege)) {
            $menuFlag = $privilege['is_menu'] ? '=M=' : '-';
            print("\t[PRI {$privilegeId} | {$privilege['unique_en_name']} | {$menuFlag} : {$privilege['name']}] {$privilege['uri']}\n");
        } else {
            $invalidPrivilegeIds[] = $privilegeId;
        }
    }
}

function dumpRole($role) {
    $id = $role['id'];
    $name = $role['name'];
    $desc = $role['desc'];
    $orgType = $role['org_type'];

    print("\n=======================================\n\n");
    print("[ID:$id | ORG:$orgType] $name ($desc) || ");

    $groupIds = explode(',', $role['group_ids']);
    foreach ($groupIds as $groupId) {
        dumpGroupById($groupId);
    }
    print("\n");
    foreach ($groupIds as $groupId) {
        dumpGroupPrivilege($groupId);
    }

    print("\n\n=======================================\n\n");
}

/*
 * 列出所有权限
 * 检查不存在的 group_privilege
 *
foreach ($roles as $role) {
    dumpRole($role);
}

print("invalid privilege ids:\n");
print(implode(',', $invalidPrivilegeIds) . "\n");
*/