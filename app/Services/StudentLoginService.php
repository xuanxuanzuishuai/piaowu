<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\AppleDevicesModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentBrushModel;
use App\Models\StudentLoginInfoModel;
use App\Models\StudentModel;

class StudentLoginService
{
    /**
     * 用户登录信息处理及刷单信息判断
     * @param $params
     * @return bool
     */
    public static function handleLoginInfo($params)
    {
        $params['time'] = time();

        if (!isset($params['mobile']) || empty($params['mobile'])) {
            return false;
        }

        $studentInfo = StudentModel::getRecord(['mobile' => $params['mobile']]);
        if (empty($studentInfo)) {
            return false;
        }

        $params['sub_end_time'] = strtotime(date($studentInfo['sub_end_date'] . " 23:59:59"));
        $params['is_experience'] = StudentLoginInfoModel::IS_EXPERIENCE_FALSE;

        if ($studentInfo['has_review_course'] == StudentLoginInfoModel::STUDENT_PAY_TYPE_EXPERIENCE && $params['sub_end_time'] >= $params['time']) {
            $params['is_experience'] = StudentLoginInfoModel::IS_EXPERIENCE_TRUE;
        }

        //学生信息入表
        $studentId = self::insertLoginInfo($params, $studentInfo);
        if (empty($studentId)) {
            return false;
        }

        if ($params['is_experience'] == StudentLoginInfoModel::IS_EXPERIENCE_FALSE) {
            return false;
        }

        //统计该设备目前登录的用户数
        $studentIds = self::getStudentIdList($params);
        if (count(array_unique($studentIds['experienceStudentIds'])) < 3) {
            return false;
        }

        //生成同一用户刷单标识
        $brushNo = $params['time'] . '_' . $studentId . '_' . Util::randString(4);

        //插入或更新刷单表信息
        self::insertBrushInfo(array_unique($studentIds['allStudentIds']), $brushNo, $params['time']);
        return true;
    }

    /**
     * @param $params
     * @param $studentInfo
     * @return mixed
     * 用户登录信息入表
     */
    public static function insertLoginInfo($params,$studentInfo)
    {
        $insertData = [
            'student_id'        => $studentInfo['id'],
            'token'             => $params['token'] ?? '',
            'device_model'      => $params['device_model'] ?? '',
            'os'                => $params['os'] ?? '',
            'idfa'              => $params['idfa'] ?? '',
            'imei'              => $params['imei'] ?? '',
            'android_id'        => $params['android_id'] ?? '',
            'has_review_course' => $studentInfo['has_review_course'],
            'sub_end_time'      => $params['sub_end_time'],
            'is_experience'     => $params['is_experience'],
            'create_time'       => $params['time'],
        ];

        StudentLoginInfoModel::insertRecord($insertData);
        return $studentInfo['id'];
    }

    /**
     * 获取统一设备登录的所有学员ID
     * @param $params
     * @return array
     */
    public static function getStudentIdList($params)
    {
        if (isset($params['idfa']) && $params['idfa'] != '00000000-0000-0000-0000-000000000000') {
            $where['idfa'] = $params['idfa'];
        } elseif (isset($params['imei']) && !empty($params['imei']) && strlen($params['imei']) > 10) {
            $where['imei'] = $params['imei'];
        } elseif (isset($params['android_id']) && !empty($params['android_id']) && strlen($params['android_id']) > 10) {
            $where['android_id'] = $params['android_id'];
        }

        if (!isset($where) || empty($where)) {
            return [];
        }

        $studentIdList = StudentLoginInfoModel::getRecords($where, ['student_id', 'is_experience']);
        if (!isset($studentIdList) || empty($studentIdList)) {
            return [];
        }

        foreach ($studentIdList as $value) {
            if ($value['is_experience'] == StudentLoginInfoModel::STUDENT_PAY_TYPE_EXPERIENCE) {
                $experienceStudentIds[] = $value['student_id'];
            }
            $allStudentIds[] = $value['student_id'];
        }

        return [
            'experienceStudentIds' => $experienceStudentIds ?? [],
            'allStudentIds'        => $allStudentIds ?? [],
        ];
    }

    /**
     * @param $studentIdsOnSameDevice
     * @param $brushNo
     * @param $time
     * @return bool
     * 插入或更新刷单表
     */
    public static function insertBrushInfo($studentIdsOnSameDevice, $brushNo, $time)
    {
        $studentBrushInfo = StudentBrushModel::getRecords(['student_id' => $studentIdsOnSameDevice], ['student_id', 'brush_no'], false);
        $recordStudentIds = $recordBrushNo = $insertData = [];
        if (!empty($studentBrushInfo)) {
            foreach ($studentBrushInfo as $value) {
                $recordStudentIds[] = $value['student_id'];
                $recordBrushNo[] = $value['brush_no'];
            }
        }

        $insertStudentIds = array_diff($studentIdsOnSameDevice, $recordStudentIds);
        $insertData = [];
        if (!empty($insertStudentIds)){
            foreach ($insertStudentIds as $value) {
                $insertData[] = [
                    'student_id'  => $value,
                    'brush_no'    => $brushNo,
                    'create_time' => $time,
                ];
            }
        }

        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            if (!empty($insertData)) {
                StudentBrushModel::insertRecord($insertData);
            }
            if (!empty($brushNo)) {
                StudentBrushModel::batchUpdateRecord(['brush_no' => $brushNo], ['brush_no' => $recordBrushNo], false);
            }
            $db->commit();
        } catch (\RuntimeException $e) {
            $db->rollBack();
            SimpleLogger::info('insert or update student_brush_info fail', ['error' => $e->getMessage()]);
        }
        return true;
    }

    /**
     * @param $studentId
     * @return bool
     * 获取学员是否有刷单嫌疑
     */
    public static function getStudentBrush($studentId)
    {
        if (empty($studentId)) {
            return false;
        }
        $brushInfo = StudentBrushModel::getRecord(['student_id' => $studentId], ['student_id'], false);
        return empty($brushInfo) ? false : true;
    }

    /**
     * @param $studentId
     * @return bool
     * 红包判断刷单状态
     */
    public static function getStudentBrushByLogin($studentId)
    {
        if (empty($studentId)) {
            return false;
        }
        $brushInfo = StudentBrushModel::getStudentBrushByLogin($studentId);

        if (empty($brushInfo)){
            return false;
        }

        $subEndTime = strtotime(date($brushInfo[0]['sub_end_date'] . " 23:59:59"));
        if ($brushInfo[0]['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_49 && $subEndTime > time()) {
            return true;
        }

        return false;
    }

    /**
     * @param $studentId
     * @return array
     * 根据学生ID获取用户刷单列表
     */
    public static function getStudentBrushList($studentId)
    {
        if (empty($studentId)) {
            return [];
        }
        $brushList = StudentBrushModel::getBrushList($studentId);
        if (empty($brushList)) {
            return [];
        }

        $appleDeviceMap = AppleDevicesModel::getAppleDevicesMap();
        $dataList = [];
        foreach ($brushList as $key => $value) {
            if (key_exists($value['student_id'], $dataList)) {
                $deviceModel = self::getDeviceModel($value, $appleDeviceMap) ?? '';
                if (!in_array($deviceModel, $dataList[$value['student_id']]['device_model'])) {
                    $dataList[$value['student_id']]['device_model'][] = $deviceModel;
                }
            } else {
                $dataList[$value['student_id']]['student_id'] = $value['student_id'];
                $dataList[$value['student_id']]['student_name'] = $value['student_name'];
                $dataList[$value['student_id']]['assistant_name'] = $value['assistant_name'];
                $dataList[$value['student_id']]['collection_name'] = $value['collection_name'];
                $dataList[$value['student_id']]['mobile'] = Util::hideUserMobile($value['mobile']);
                $dataList[$value['student_id']]['join_class_time'] = date('Y-m-d H:i:s', $value['join_class_time']);
                $dataList[$value['student_id']]['register_time'] = date('Y-m-d H:i:s', $value['register_time']);
                $dataList[$value['student_id']]['is_login_account'] = false;
                $dataList[$value['student_id']]['device_model'][] = self::getDeviceModel($value, $appleDeviceMap);
                if ($value['student_id'] == $studentId) {
                    $dataList[$value['student_id']]['is_login_account'] = true;
                }
            }
        }
        return $dataList ?? [];
    }

    /**
     * @param $params
     * @param $appleDeviceMap
     * @return string
     * 整合登录设备信息
     */
    public static function getDeviceModel($params, $appleDeviceMap)
    {
        if (!empty($params['idfa'])) {
            if ($params['idfa'] == '00000000-0000-0000-0000-000000000000') {
                $deviceModel = ($appleDeviceMap[$params['device_model']] ?? '未知设备') . "(--)";
            } else {
                $deviceModel = ($appleDeviceMap[$params['device_model']] ?? '未知设备') . "(" . substr($params['idfa'], -4, 4) . ")";
            }
        } elseif (!empty($params['imei'])) {
            $deviceModel = $params['device_model'] . "(" . substr($params['imei'], -4, 4) . ")";
        } elseif (!empty($params['android_id'])) {
            $deviceModel = $params['device_model'] . "(" . substr($params['android_id'], -4, 4) . ")";
        } else {
            $deviceModel = $params['device_model'] . "(--)";
        }
        return $deviceModel;
    }

}