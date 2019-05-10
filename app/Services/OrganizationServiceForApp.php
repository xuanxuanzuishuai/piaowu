<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:05 PM
 */

namespace App\Services;


use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Models\HomeworkTaskModel;
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

        $orgInfo = self::getOrgInfo($orgAccount['org_id']);

        if ($orgInfo['status'] == OrganizationModel::STATUS_STOP) {
            return ['org_account_invalid'];
        }

        $orgInfo['account'] = $account;
        $orgInfo['license_num'] = (int)$orgAccount['license_num'];
        $orgTeachers = self::getTeachers($orgAccount['org_id']);

        $token = OrganizationModelForApp::genToken($orgAccount['org_id']);
        OrganizationModelForApp::setOrgToken($orgAccount['org_id'], $account, $token);

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
        $orgAccount = OrgAccountModel::getByAccount($account);
        if (empty($orgAccount)) {
            return ['org_account_invalid'];
        }

        if ($orgAccount['status'] != OrgAccountModel::STATUS_NORMAL) {
            return ['org_account_invalid'];
        }

        $cache = OrganizationModelForApp::getOrgCacheByToken($token);
        if (empty($cache) || empty($cache['account']) || $cache['account'] != $account) {
            return ['invalid_org_token'];
        }

        $orgInfo = self::getOrgInfo($orgAccount['org_id']);
        $orgInfo['account'] = $account;
        $orgInfo['license_num'] = (int)$orgAccount['license_num'];
        $orgTeachers = self::getTeachers($orgAccount['org_id']);

        $loginData = [
            'org_info' => $orgInfo,
            'teachers' => $orgTeachers,
            'org_token' => $token
        ];

        return [null, $loginData];
    }

    public static function teacherLogin($orgId, $account, $teacherId, $studentId)
    {
        $orgAccount = OrgAccountModel::getByAccount($account);

        $teacherToken = OrganizationModelForApp::genToken($teacherId);
        $teacherCacheData = [
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'token' => $teacherToken,
        ];

        $onlineTeachers = OrganizationModelForApp::getOnlineTeacher($orgId);
        $onlineTeachersNew = [$teacherCacheData];
        $licenseRemain = $orgAccount['license_num'] - 1;
        $willDelTokens = [];

        foreach ($onlineTeachers as $data) {
            $cache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgId, $data['token']);

            if (empty($cache)) {
                // 检测并删除超时失效的token
                $willDelTokens[] = $data['token'];

            } elseif ($data['teacher_id'] == $teacherId) {
                // 删除当前登录老师重复的token
                $willDelTokens[] = $data['token'];

            } else {
                if($licenseRemain <= 0) {
                    // 删除超出许可数量的token
                    $willDelTokens[] = $data['token'];

                } else {
                    $onlineTeachersNew[] = $data;
                    $licenseRemain--;
                }
            }
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
            'license_num' => $orgAccount['license_num']
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
        $onlineTeacherIds = [];
        foreach ($onlineTeachers as $data) {
            $cache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgId, $data['token']);
            if (!empty($cache)) {
                $onlineTeacherIds[] = $cache['teacher_id'];
            }
        }

        foreach ($teachers as $idx => $teacher) {
            $teachers[$idx]['id'] = intval($teachers[$idx]['id']);
            $teachers[$idx]['online'] = in_array($teacher['id'], $onlineTeacherIds) ? 1 : 0;
        }

        return $teachers;
    }

    public static function getStudents($orgId, $teacherId)
    {
        $students = OrganizationModelForApp::getOrgStudentsByTeacherId($orgId, $teacherId);
        if (empty($students)) {
            return [];
        }
        return $students;
    }
}
