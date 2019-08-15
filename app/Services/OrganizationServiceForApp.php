<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:05 PM
 */

namespace App\Services;

use App\Libs\SimpleLogger;
use App\Models\OrgAccountModel;
use App\Models\OrganizationModel;
use App\Models\OrganizationModelForApp;
use App\Models\TeacherModelForApp;

class OrganizationServiceForApp
{
    const ORG_SECURITY_KEY = "dkUwIxVLchlXB9Unvr68dJoT";

    /**
     * 机构登录
     * @param $account
     * @param $password
     * @return array
     */
    public static function login($account, $password)
    {
        $orgAccount = OrgAccountModel::getByAccount($account);
        if (empty($orgAccount)) {
            return ['org_account_invalid'];
        }

        if ($orgAccount['status'] != OrgAccountModel::STATUS_NORMAL) {
            return ['org_account_invalid'];
        }

        if ($password != $orgAccount['password']) {
            return ['org_account_password_error'];
        }

        $orgId = $orgAccount['org_id'];
        $orgInfo = self::getOrgInfo($orgId);

        if ($orgInfo['status'] == OrganizationModel::STATUS_STOP) {
            return ['org_is_disabled'];
        }

        $orgInfo['account'] = $account;
        $licenseInfo = OrgLicenseService::getLicenseInfo($orgId);
        $orgInfo['license_num'] = $licenseInfo['valid_num'];
        $orgInfo['start_time'] = (string)$licenseInfo['min_active_time'];
        $orgInfo['end_time'] = (string)$licenseInfo['max_expire_time'];
        $orgTeachers = self::getTeachers($orgId);

        $token = OrganizationModelForApp::genToken($orgId);
        OrganizationModelForApp::setOrgToken($orgId, $account, $token);

        OrgAccountModel::updateRecord($orgAccount['id'], ['last_login_time' => time()], false);

        $loginData = [
            'org_info' => $orgInfo,
            'teachers' => $orgTeachers,
            'org_token' => $token
        ];

        return [null, $loginData];
    }

    /**
     * token登录
     *
     * @param string $account 手机号
     * @param string $token 登录返回的token
     * @return array [0]errorCode [1]登录数据
     */
    public static function loginWithToken($account, $token)
    {
        $cache = OrganizationModelForApp::getOrgCacheByToken($token);
        if (empty($cache) || empty($cache['account']) || $cache['account'] != $account) {
            return ['invalid_org_token'];
        }

        $orgAccount = OrgAccountModel::getByAccount($account);
        if (empty($orgAccount)) {
            return ['org_account_invalid'];
        }

        if ($orgAccount['status'] != OrgAccountModel::STATUS_NORMAL) {
            return ['org_account_invalid'];
        }

        $orgId = $orgAccount['org_id'];
        $orgInfo = self::getOrgInfo($orgId);

        if ($orgInfo['status'] == OrganizationModel::STATUS_STOP) {
            return ['org_is_disabled'];
        }

        $orgInfo['account'] = $account;
        $licenseInfo = OrgLicenseService::getLicenseInfo($orgId);
        $orgInfo['license_num'] = $licenseInfo['valid_num'];
        $orgInfo['start_time'] = (string)$licenseInfo['min_active_time'];
        $orgInfo['end_time'] = (string)$licenseInfo['max_expire_time'];
        $orgTeachers = self::getTeachers($orgId);

        $loginData = [
            'org_info' => $orgInfo,
            'teachers' => $orgTeachers,
            'org_token' => $token
        ];

        return [null, $loginData];
    }

    /**
     * 选择老师学生
     * 生成teacherToken，占用一个license并开始上课
     *
     * cache结构
     * TeacherCache:[teacher_id => 1, student_id => [1, 2, 3], token => 'abc']
     * OnlineTeachers:Array(TeacherCache)
     *
     * @param $orgId
     * @param $teacherId
     * @param $studentIds
     * @return array
     */
    public static function teacherLogin($orgId, $teacherId, $studentIds)
    {
        $teacherToken = OrganizationModelForApp::genToken($teacherId);
        $teacherCacheData = [
            'teacher_id' => $teacherId,
            'student_id' => $studentIds,
            'token' => $teacherToken,
        ];

        $licenseNum = OrgLicenseService::getLicenseNum($orgId);
        if ($licenseNum < 1) {
            return ['no_license_num'];
        }

        $onlineTeachers = OrganizationModelForApp::getOnlineTeacher($orgId);
        $onlineTeachersNew = [$teacherCacheData];
        $licenseRemain = $licenseNum - 1;
        $willDelTokens = [];

        foreach ($onlineTeachers as $data) {
            $cache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgId, $data['token']);

            if (empty($cache)) { // 检测并删除超时失效的token
                $willDelTokens[] = $data['token'];
                continue;
            }

            // 从之前已经上课的学生里踢出本次新登录的学生
            $onlineStudentIds = array_values(array_diff($data['student_id'], $studentIds));
            $onlineCount = count($onlineStudentIds);

            if ($onlineCount < 1) { // 所有学生都被踢出课堂,直接下课,删除token
                $willDelTokens[] = $data['token'];
                continue;
            } elseif (count($onlineStudentIds) != count($data['student_id'])) { // 删除被踢出的学生
                $data['student_id'] = $onlineStudentIds;
                OrganizationModelForApp::setOrgTeacherToken($data, $orgId, $data['token']);
            }

            if ($licenseRemain <= 0) { // 删除超出许可数量的token
                $willDelTokens[] = $data['token'];
                continue;
            }

            $onlineTeachersNew[] = $data;
            $licenseRemain--;
        }

        OrganizationModelForApp::setOrgTeacherToken($teacherCacheData, $orgId, $teacherToken);
        OrganizationModelForApp::setOnlineTeacher($onlineTeachersNew, $orgId);
        if (!empty($willDelTokens)) {
            OrganizationModelForApp::delOrgTeacherTokens($orgId, $willDelTokens);
        }
        SimpleLogger::debug(__FILE__ . __LINE__ . " >>> org teacher login <<<", [
            '$onlineTeachers' => $onlineTeachers,
            '$onlineTeachersNew' => $onlineTeachersNew,
            '$willDelTokens' => $willDelTokens,
            'license_num' => $licenseNum
        ]);

        $loginData = [
            'org_teacher_token' => $teacherToken,
        ];

        return [null, $loginData];
    }

    public static function getOrgInfo($orgId)
    {
        $org = OrganizationModelForApp::getById($orgId);
        if (empty($org)) {
            return [];
        }
        $orgInfo = [
            "id" => $org['id'],
            "name" => $org['name'],
            "start_time" => $org['start_time'],
            "end_time" => $org['end_time'],
            "status" => $org['status']
        ];

        return $orgInfo;
    }

    public static function getTeachers($orgId)
    {
        $teachers = TeacherModelForApp::getTeacherNameByOrg($orgId);
        $onlineTeachers = OrganizationModelForApp::getOnlineTeacher($orgId);
        $onlineTeacherCount = [];
        foreach ($onlineTeachers as $data) {
            $cache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgId, $data['token']);
            if (empty($cache)) {
                continue;
            }

            $teacherId = $cache['teacher_id'];
            $onlineTeacherCount[$teacherId] = empty($onlineTeacherCount[$teacherId]) ? 1 : $onlineTeacherCount[$teacherId] + 1;
        }

        foreach ($teachers as $idx => $teacher) {
            $teachers[$idx]['id'] = intval($teachers[$idx]['id']);
            $teachers[$idx]['online'] = $onlineTeacherCount[$teacher['id']] ?? 0;
        }

        return $teachers;
    }

    public static function getStudents($orgId, $teacherId)
    {
        $students = OrganizationModelForApp::getOrgStudentsByTeacherId($orgId, $teacherId);
        if (empty($students)) {
            return [];
        }

        $onlineStudentIds = [];
        $onlineTeachers = OrganizationModelForApp::getOnlineTeacher($orgId);
        foreach ($onlineTeachers as $data) {
            if ($data['teacher_id'] == $teacherId) {
                $cache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgId, $data['token']);
                if (!empty($cache)) {
                    $onlineStudentIds = array_merge($onlineStudentIds, $cache['student_id']);
                }
            }
        }

        foreach ($students as $i => $student) {
            $students[$i]['online'] = in_array($student['id'], $onlineStudentIds) ? 1 : 0;
        }

        return $students;
    }
}
