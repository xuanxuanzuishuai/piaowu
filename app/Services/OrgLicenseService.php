<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/27
 * Time: 5:45 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Models\OrgLicenseModel;

class OrgLicenseService
{
    /**
     * 创建机构智能琴房APP许可信息
     *
     * @param int $orgId 机构id
     * @param int $type 类型 1智能琴房 2钢琴教室
     * @param int $num 同时登录数量
     * @param int $duration 时长
     * @param string $durationUnit 时长单位(日月年)
     * @param int $operator 操作人（TheONE内部人员）
     * @return int|null 许可id
     */
    public static function create($orgId, $type, $num, $duration, $durationUnit, $operator)
    {
        return OrgLicenseModel::insertRecord([
            'org_id' => $orgId,
            'type' => $type,
            'license_num' => $num,
            'create_time' => time(),
            'create_operator' => $operator,
            'duration' => $duration,
            'duration_unit' => $durationUnit,
            'status' => Constants::STATUS_TRUE,
        ], false);
    }

    /**
     * 激活许可，智能机构自己操作
     *
     * @param int $licenseId 许可id
     * @param int $orgId 机构id
     * @param int $operator 操作人（机构人员）
     * @return int|null
     */
    public static function active($licenseId, $orgId, $operator)
    {
        $license = OrgLicenseModel::getById($licenseId);
        if (empty($license)) {
            return null;
        }

        if ($license['org_id'] != $orgId) {
            return null;
        }

        if (!empty($license['active_time'])) {
            return null;
        }

        $expire = self::getExpire($license['duration'], $license['duration_unit']);

        return OrgLicenseModel::updateRecord($licenseId, [
            'active_time' => time(),
            'active_operator' => $operator,
            'expire_time' => $expire,
        ], false);
    }

    /**
     * 禁用许可
     *
     * @param int $licenseId
     * @param int $operator 操作人（TheONE内部人员）
     * @return int|null
     */
    public static function disable($licenseId, $operator)
    {
        $license = OrgLicenseModel::getById($licenseId);
        if (empty($license) || $license['status'] == Constants::STATUS_FALSE) {
            return null;
        }

        return OrgLicenseModel::updateRecord($licenseId, [
            'status' => Constants::STATUS_FALSE,
            'status_operator' => $operator,
        ], false);
    }

    /**
     * 计算过期时间
     *
     * @param int $duration 时长
     * @param string $unit 时长单位(日月年)
     * @return false|int
     */
    public static function getExpire($duration, $unit)
    {
        $str = "today +{$duration} {$unit}";
        return strtotime($str);
    }

    /**
     * 获取机构当前可用许可数量
     *
     * @param $orgId
     * @param $type
     * @return number
     */
    public static function getLicenseNum($orgId, $type)
    {
        $num = OrgLicenseModel::getValidNum($orgId, $type);
        return $num ?? 0;
    }

    public static function selectList($params)
    {
        list($records, $total) = OrgLicenseModel::selectList($params);
        foreach($records as &$r) {
            $r['duration']  .= DictService::getKeyValue(Constants::DICT_TYPE_ORG_LICENSE_DURATION_UNIT, $r['duration_unit']);
            $r['status_zh'] = DictService::getKeyValue(Constants::DICT_TYPE_ORG_LICENSE_STATUS, $r['status']);
            $r['type'] = DictConstants::get(DictConstants::LICENSE_TYPE, $r['type']);
        }
        return [$records, $total];
    }

    /**
     * 机构许可信息
     * [
     *   'valid_num' => x,
     *   'min_active_time' => y,
     *   'max_expire_time' => z
     * ]
     *
     * @param $orgId
     * @param $type
     * @return mixed
     */
    public static function getLicenseInfo($orgId, $type)
    {
        $info = OrgLicenseModel::getInfo($orgId, $type);
        return [
            'valid_num' => (int)$info['valid_num'],
            'min_active_time' => (int)$info['min_active_time'],
            'max_expire_time' => (int)$info['max_expire_time'],
        ];
    }
}