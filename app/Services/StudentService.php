<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 19:58
 *
 * 客户相关数据service
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\ResponseError;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;

class StudentService
{
    //默认分页条数
    const DEFAULT_COUNT = 20;


    /**
     * 查看机构下学生
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectStudentByOrg($orgId, $page, $count, $params)
    {
        list($records, $total) = StudentModel::selectStudentByOrg($orgId, $page, $count, $params);
        foreach($records as &$r) {
            $r['student_level'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_LEVEL, $r['student_level']);
            $r['gender']        = DictService::getKeyValue(Constants::DICT_TYPE_GENDER, $r['gender']);
            //ai陪练到期日
            if($r['sub_status'] == StudentModel::SUB_STATUS_NORMAL) {
                if(empty($r['sub_end_date'])) {
                    $r['sub_end_date'] = StudentModel::NOT_ACTIVE_TEXT;
                }
            } else {
                $r['sub_end_date'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_SUB_STATUS, $r['sub_status']);
            }
        }

        return [$records, $total];
    }

    /**
     * 更新学生详细信息
     * @param $studentId
     * @param $params
     * @return array|int
     */
    public static function updateStudentDetail($studentId, $params)
    {
        $student = StudentModel::getById($studentId);

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);

        $userCenter = new UserCenter($appId, $appSecret);
        $updResult = $userCenter->modifyStudent($student['uuid'], $params['name'], $params['birthday'], strval($params['gender']));
        if (!empty($updResult) && $updResult['code'] != 0){
            return $updResult;
        }

        $affectRow = StudentModel::updateStudent($studentId, $params);

        return $affectRow;
    }

    /**
     * 学生注册
     * @param $params
     * @param $operatorId
     * @return int|mixed|null|string
     * @throws \Exception
     */
    public static function studentRegister($params, $operatorId = 0)
    {
        //添加学生
        $res = self::insertStudent($params, $operatorId);
        if($res['code'] != Valid::CODE_SUCCESS){
            return $res;
        }

        $studentId = $res['data']['studentId'];

        return [
            'code'       => 0,
            'student_id' => $studentId,
        ];
    }

    /**
     * 添加学生
     * 本地如果已经存在此学生，更新学生信息，返回学生id
     * 如果没有，新增学生记录，返回id
     * @param $params
     * @param int $operatorId
     * @return array
     */
    public static function insertStudent($params, $operatorId = 0)
    {
        $birthday   = $params['birthday'] ?? '';
        $gender     = $params['gender'] ?? StudentModel::GENDER_UNKNOWN;

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);

        $authResult = $userCenter->studentAuthorization(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            $params['mobile'], $params['name'], '',$birthday, strval($gender));

        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }

        $uuid = $authResult['uuid'];

        $student = StudentModel::getRecord([
            'uuid' => $uuid,
        ],[],false);

        if(empty($student)) {
            $studentId = StudentModel::saveStudent($params, $authResult["uuid"], $operatorId);
            if(empty($studentId)) {
                return Valid::addErrors([], 'student', 'save_student_fail');
            }
        } else {
            $studentId = $student['id'];
            $params['update_time'] = time();
            $affectRows = StudentModel::updateRecord($studentId, $params, false);
            if($affectRows == 0) {
                return Valid::addErrors([], 'student', 'update_student_fail');
            }
        }

        return ['code' => Valid::CODE_SUCCESS, 'data' => ['studentId' => $studentId]];
    }

    /**
     * 查询一条指定机构和学生id的记录
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return array|null
     */
    public static function getOrgStudent($orgId, $studentId, $status = null)
    {
        return StudentModel::getOrgStudent($orgId, $studentId, $status);
    }

    /**
     * 绑定学生和机构，返回lastId
     * 关系存在时候，只更新，不插入新的记录
     * 已经绑定的不会返回错误，同样返回lastId
     * @param $orgId
     * @param $studentId
     * @return ResponseError|int|mixed|null|string
     */
    public static function bindOrg($orgId, $studentId)
    {
        $studentOrg = StudentOrgModel::getRecord(['org_id' => $orgId, 'student_id' => $studentId]);
        if(empty($studentOrg)) {
            $now = time();
            //save
            $lastId = StudentOrgModel::insertRecord([
                'org_id'      => $orgId,
                'student_id'  => $studentId,
                'status'      => StudentOrgModel::STATUS_NORMAL,
                'update_time' => $now,
                'create_time' => $now,
            ]);
            if($lastId == 0) {
                return new ResponseError('save_student_org_fail');
            }
            return $lastId;
        } else {
            //update
            if($studentOrg['status'] != StudentOrgModel::STATUS_NORMAL) {
                $affectRows = StudentOrgModel::updateStatus($orgId, $studentId, StudentOrgModel::STATUS_NORMAL);
                if($affectRows == 0) {
                    return new ResponseError('update_student_org_status_error');
                }
                return $studentOrg['id'];
            }
            return $studentOrg['id'];
        }
    }

    /**
     * 更新学生和机构关系的状态(解绑/绑定)
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateStatusWithOrg($orgId, $studentId, $status) {
        return StudentOrgModel::updateStatus($orgId, $studentId, $status);
    }

    public static function getStudentByIds($sIds)
    {
        return StudentOrgModel::getRecords(['student_id'=>$sIds,'status'=>StudentOrgModel::STATUS_NORMAL]);
    }

    /**
     * 批量给学生分配课管
     * @param $studentIds
     * @param $orgId
     * @param $ccId
     * @return int
     */
    public static function assignCC($studentIds, $orgId, $ccId)
    {
        return StudentOrgModel::assignCC($studentIds, $orgId, $ccId);
    }
}