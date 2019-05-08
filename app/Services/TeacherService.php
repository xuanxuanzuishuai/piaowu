<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/25
 * Time: 12:30 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\ResponseError;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Models\TeacherModel;
use App\Models\TeacherOrgModel;

class TeacherService
{
    public static function saveAndUpdateTeacher($params)
    {
        // 必填参数
        $update['mobile'] = $params['mobile'] ?? '';
        $update['name']   = $params['name'] ?? '';

        // 可选参数
        $update['gender']               = empty($params['gender']) ? TeacherModel::GENDER_UNKNOWN : $params['gender'];
        $update['birthday']             = $params['birthday'] ?? null;
        $update['thumb']                = $params['thumb'] ?? '';
        $update['country_code']         = $params['country_code'] ?? '';
        $update['province_code']        = $params['province_code'] ?? '';
        $update['city_code']            = $params['city_code'] ?? '';
        $update['district_code']        = $params['district_code'] ?? '';
        $update['address']              = $params['address'] ?? '';
        $update['channel_id']           = empty($params['channel_id']) ? null : $params['channel_id'];
        $update['id_card']              = $params['id_card'] ?? '';
        $update['bank_card_number']     = $params['bank_card_number'] ?? '';
        $update['opening_bank']         = $params['opening_bank'] ?? '';
        $update['bank_reserved_mobile'] = $params['bank_reserved_mobile'] ?? null;
        $update['type']                 = empty($params['type']) ? null : $params['type'];
        $update['level']                = empty($params['level']) ? null : $params['level'];
        $update['start_year']           = empty($params['start_year']) ? null : $params['start_year'];
        $update['learn_start_year']     = empty($params['learn_start_year']) ? null : $params['learn_start_year'];
        $update['college_id']           = empty($params['college_id']) ? null : $params['college_id'];
        $update['major_id']             = empty($params['major_id']) ? null : $params['major_id'];
        $update['graduation_date']      = empty($params['graduation_date']) ? null : $params['graduation_date'];
        $update['education']            = empty($params['education']) ? null : $params['education'];
        $update['music_level']          = empty($params['music_level']) ? null : $params['music_level'];
        $update['teach_experience']     = $params['teach_experience'] ?? '';
        $update['prize']                = $params['prize'] ?? '';
        $update['teach_results']        = $params['teach_results'] ?? null;
        $update['teach_style']          = $params['teach_style'] ?? null;
        $update['status']               = empty($params['status']) ? TeacherModel::ENTRY_REGISTER : $params['status'];

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);
        $auth = true;

        $authResult = $userCenter->teacherAuthorization($update['mobile'], $update['name'], '',
            $update['birthday'], strval($update['gender']), $update['thumb'], $auth);
        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }

        $uuid = $authResult['uuid'];
        $teacher = TeacherModel::getRecord([
            'uuid' => $uuid
        ],'*',false);

        if(empty($teacher)) {
            $update['uuid'] = $uuid;
            $update['update_time'] = time();
            $update['create_time'] = time();
            $teacherId = TeacherModel::insertRecord($update, false);
            if (empty($teacherId)) {
                return Valid::addErrors([], 'teacher', 'save_teacher_fail');
            }
        } else {
            $teacherId = $teacher['id'];
            $update['update_time'] = time();
            $affectRows = TeacherModel::updateRecord($teacherId, $update, false);
            if($affectRows == 0) {
                return Valid::addErrors([], 'teacher', 'update_teacher_fail');
            }
        }

        $modifyResult = $userCenter->modifyTeacher($uuid, $update['mobile'], $update['name'],
            $update['birthday'],strval($update['gender']));
        if(isset($modifyResult['code'])) {
            return $modifyResult; //已经用Valid::addErrors包装过
        }

        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $teacherId
            ]
        ];
    }
    /**
     * 插入或更新老师数据
     * @param $teacherId
     * @param $params
     * @return int|mixed|null|string
     */
    public static function updateTeacher($teacherId, $params)
    {
        $update['mobile'] = $params['mobile'] ?? '';
        $update['name']   = $params['name'] ?? '';

        // 可选参数
        $update['gender']               = empty($params['gender']) ? TeacherModel::GENDER_UNKNOWN : $params['gender'];
        $update['birthday']             = $params['birthday'] ?? null;
        $update['thumb']                = $params['thumb'] ?? '';
        $update['country_code']         = $params['country_code'] ?? '';
        $update['province_code']        = $params['province_code'] ?? '';
        $update['city_code']            = $params['city_code'] ?? '';
        $update['district_code']        = $params['district_code'] ?? '';
        $update['address']              = $params['address'] ?? '';
        $update['channel_id']           = empty($params['channel_id']) ? null : $params['channel_id'];
        $update['id_card']              = $params['id_card'] ?? '';
        $update['bank_card_number']     = $params['bank_card_number'] ?? '';
        $update['opening_bank']         = $params['opening_bank'] ?? '';
        $update['bank_reserved_mobile'] = $params['bank_reserved_mobile'] ?? null;
        $update['type']                 = empty($params['type']) ? null : $params['type'];
        $update['level']                = empty($params['level']) ? null : $params['level'];
        $update['start_year']           = empty($params['start_year']) ? null : $params['start_year'];
        $update['learn_start_year']     = empty($params['learn_start_year']) ? null : $params['learn_start_year'];
        $update['college_id']           = empty($params['college_id']) ? null : $params['college_id'];
        $update['major_id']             = empty($params['major_id']) ? null : $params['major_id'];
        $update['graduation_date']      = empty($params['graduation_date']) ? null : $params['graduation_date'];
        $update['education']            = empty($params['education']) ? null : $params['education'];
        $update['music_level']          = empty($params['music_level']) ? null : $params['music_level'];
        $update['teach_experience']     = $params['teach_experience'] ?? '';
        $update['prize']                = $params['prize'] ?? '';
        $update['teach_results']        = $params['teach_results'] ?? null;
        $update['teach_style']          = $params['teach_style'] ?? null;

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);

        $teacher = TeacherModel::getById($teacherId);
        if (empty($teacher)) {
            return Valid::addErrors([], 'teacher', 'teacher_is_not_exist');
        }

        $update['uuid'] = $teacher['uuid'];
        $update['update_time'] = time();

        $affectRows = TeacherModel::updateRecord($teacherId, $update, false);
        if ($affectRows == 0) {
            return Valid::addErrors([], 'teacher', 'update_teacher_fail');
        }

        $update['gender'] = empty($update['gender']) ? TeacherModel::GENDER_UNKNOWN : strval($update['gender']);

        $modifyResult = $userCenter->modifyTeacher($update['uuid'], $update['mobile'], $update['name'],
            $update['birthday'],strval($update['gender']));

        if(isset($modifyResult['code'])) {
            return $modifyResult; //已经用Valid::addErrors包装过
        }

        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $teacherId
            ]
        ];
    }

    /**
     * 获取老师列表
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getList($orgId, $page, $count, $params)
    {
        $ta_role_id = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, 'TA_ROLE_ID');
        list($teachers, $totalCount) = TeacherModel::getTeacherList($orgId, $page, $count, $params, $ta_role_id);

        foreach($teachers as &$t) {
            $t['status']    = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $t['status']);
            $t['gender']    = DictService::getKeyValue(Constants::DICT_TYPE_GENDER, $t['gender']);
            $t['type']      = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_TYPE, $t['type']);
            $t['level']     = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $t['level']);
            $t['education'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_EDUCATION, $t['education']);
        }

        return [$teachers, $totalCount];
    }

    /**
     * 绑定老师和机构，数据库操作失败时才返回错误，已经绑定不会返回错误，正确时候返回操作行的id
     * @param $orgId
     * @param $teacherId
     * @return ResponseError|int|mixed|null|string
     */
    public static function bindOrg($orgId, $teacherId)
    {
        $record = TeacherOrgModel::getRecord([
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
        ]);

        if(empty($record)) {
            $lastId = TeacherOrgModel::insertRecord([
                'teacher_id'  => $teacherId,
                'org_id'      => $orgId,
                'status'      => TeacherOrgModel::STATUS_NORMAL,
                'update_time' => time(),
                'create_time' => time(),
            ], false);
            if(empty($lastId)) {
                return new ResponseError('save_teacher_org_fail');
            }
            return $lastId;
        } else {
            if($record['status'] != TeacherOrgModel::STATUS_NORMAL) {
                $affectRows = TeacherOrgModel::updateRecord($record['id'],[
                    'status' => TeacherOrgModel::STATUS_NORMAL
                ]);
                if($affectRows == 0) {
                    return new ResponseError('update_teacher_org_status_fail');
                }
                return $record['id'];
            }
            return $record['id'];
        }
    }

    /**
     * 查询指定机构下老师，不区分状态
     * @param $orgId
     * @param $teacherId
     * @return array|null
     */
    public static function getOrgTeacherById($orgId, $teacherId)
    {
        return TeacherModel::getOrgTeacherById($orgId, $teacherId);
    }

    /**
     * 更新老师和机构的绑定状态
     * @param $orgId
     * @param $teacherId
     * @param $status
     * @return int|null
     */
    public static function updateStatusWithOrg($orgId, $teacherId, $status)
    {
        return TeacherOrgModel::updateStatus($orgId, $teacherId, $status);
    }

    public static function getTeacherByIds($tIds) {
        return TeacherOrgModel::getRecords(['teacher_id'=>$tIds,'status'=>TeacherOrgModel::STATUS_NORMAL]);
    }
}