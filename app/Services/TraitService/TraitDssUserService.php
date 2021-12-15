<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/24
 * desc: 智能业务线
 */

namespace App\Services\TraitService;

use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\SharePosterDesignateUuidModel;

trait TraitDssUserService
{

    private static $studentAttribute = [];

    /**
     * DSS - 检查uuid是否存在
     * @param $studentUuid
     * @param $activityId
     * @return array
     */
    public static function checkDssStudentUuidExists($studentUuid, $activityId): array
    {
        $returnData = [
            'no_exists_uuid'       => [],
            'activity_having_uuid' => [],
        ];
        if (empty($studentUuid)) {
            return $returnData;
        }
        $uuidChunkList = array_chunk($studentUuid, 900);
        foreach ($uuidChunkList as $_uuids) {
            $studentList = DssStudentModel::getRecords(['uuid' => $_uuids], ['uuid']);
            // 不存在 - 取不同
            $returnData['no_exists_uuid'] = array_merge($returnData['no_exists_uuid'], array_values(array_diff($_uuids, array_column($studentList, 'uuid'))));
            // 如果指定了活动，取活动中已经存在的UUID
            if (!empty($activityId)) {
                $activityUUIDList = SharePosterDesignateUuidModel::getRecords(['activity_id' => $activityId, 'uuid' => $_uuids], ['uuid']);
                // 存在 - 读到的都是存在的
                $returnData['activity_having_uuid'] = array_merge($returnData['activity_having_uuid'], array_column($activityUUIDList, 'uuid'));
            }
        }
        unset($_uuids);
        // 去重
        $returnData['activity_having_uuid'] = array_unique($returnData['activity_having_uuid']);
        $returnData['no_exists_uuid']       = array_unique($returnData['no_exists_uuid']);
        return $returnData;
    }

    /**
     * DSS - 获取学生身份属性
     * can_exchange_num>0 是有效用户
     * @param $studentId
     * @return array|mixed
     */
    public static function getDssStudentIdentityAttributeById($studentId)
    {
        if (empty($studentId)) {
            return [];
        }
        $key = Constants::SMART_APP_ID . '_' . $studentId;
        if (!isset(self::$studentAttribute[$key])) {
            self::$studentAttribute[$key] = (new Dss())->getUserCanExchangeNum(['student_id' => $studentId]);
        }
        SimpleLogger::info('getDssStudentIdentityAttributeById', [$studentId, self::$studentAttribute[$key]]);
        return self::$studentAttribute[$key];
    }

    /**
     * DSS - 检查用户是否是有效付费用户
     * @param $studentId
     * @param array $studentIdAttribute
     * @return array
     */
    public static function checkDssStudentIdentityIsNormal($studentId, array $studentIdAttribute = []): array
    {
        if (empty($studentIdAttribute)) {
            $studentIdAttribute = self::getDssStudentIdentityAttributeById($studentId);
            if (empty($studentIdAttribute)) {
                return [false, $studentIdAttribute];
            }
        }
        // 未付费 - 没有有效课时
        if (!isset($studentIdAttribute['can_exchange_num']) || $studentIdAttribute['can_exchange_num'] <= 0) {
            return [false, $studentIdAttribute];
        }

        // 没有付费时间
        if (!isset($studentIdAttribute['first_pay_time'])) {
            return [false, $studentIdAttribute];
        }
        return [true, $studentIdAttribute];
    }

    /**
     * DSS - 获取学生是否能够购买指定课包，系统判定的重复用户购买指定课包时会返回其他课包
     * 检查条件： 未购买过体验课，不是重复用户
     * @param string $uuid 学生uuid
     * @param numeric $pkg PayServices::getPackageIDByParameterPkg方法参数
     * @return array
     * @throws RunTimeException
     */
    public static function getDssStudentRepeatBuyPkg($uuid, $pkg)
    {
        // 检查用户是否是薅羊毛用户， 如果是走提价策略
        $studentIsRepeatInfo = (new Dss())->getStudentIsRepeatInfo($uuid, $pkg);
        if (!isset($studentIsRepeatInfo['is_repeat'])) {
            SimpleLogger::info("getDssStudentRepeatBuyPkg", ['msg' => 'service_busy_try_later', 'info' => $studentIsRepeatInfo]);
            throw new RunTimeException(['service_busy_try_later'], []);
        }
        if (!empty($studentIsRepeatInfo['has_trail'])) {
            throw new RunTimeException(['has_trialed']);
        }
        return [
            'is_repeat' => $studentIsRepeatInfo['is_repeat'],
            'old_pkg'   => $studentIsRepeatInfo['old_pkg'],
            'new_pkg'   => $studentIsRepeatInfo['new_pkg'],
            'has_trail' => $studentIsRepeatInfo['has_trail'],
            'is_check'  => $studentIsRepeatInfo['is_check'],
        ];
    }
}
