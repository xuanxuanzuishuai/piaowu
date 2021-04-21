<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/1/18
 * Time: 11:22 上午
 */

namespace App\Models\Dss;

use App\Libs\MysqlDB;
use App\Libs\TPNS;

class DssPushDeviceModel extends DssModel
{
    public static $table = "push_token";

    const STATUS_NORMAL = 1;
    const STATUS_DEL = 2;

    /**
     * @param $userType
     * @return array
     * 根据会员类型获取对应的deviceToken
     */
    public static function getDeviceTokenByUserType($userType)
    {
        $pushDevice = self::$table;
        $student = DssStudentModel::$table;
        $time = date('Ymd', time());

        $sql = "SELECT
                    pt.device_token,
                    pt.platform
                FROM
                    {$pushDevice} AS pt
                    INNER JOIN {$student} AS s ON pt.mobile = s.mobile 
                WHERE
                    pt.`status` = " . self::STATUS_NORMAL . " 
                    AND s.has_review_course = {$userType}
                    AND s.sub_end_date < {$time}";

        $result = DssModel::dbRO()->queryAll($sql);
        if (empty($result)) {
            return [];
        }
        $deviceTokenList['android'] = $deviceTokenList['ios'] = [];
        foreach ($result as $value) {
            if ($value['platform'] == TPNS::PLATFORM_ANDROID) {
                $deviceTokenList['android'][] = $value['device_token'];
            } elseif ($value['platform'] == TPNS::PLATFORM_IOS) {
                $deviceTokenList['ios'][] = $value['device_token'];
            }
        }

        return $deviceTokenList ?? [];
    }

    /**
     * @param $uuid
     * @return array
     * 根据UUID获取deviceToken
     */
    public static function getDeviceTokenByUUid($uuid)
    {
        $pushDevice = self::$table;
        $student = DssStudentModel::$table;
        $uuidStr = implode(',',$uuid);

        $sql = "SELECT
                    pt.device_token,
                    pt.platform
                FROM
                    {$pushDevice} AS pt
                    INNER JOIN {$student} AS s ON pt.mobile = s.mobile 
                WHERE
                    pt.`status` = " . self::STATUS_NORMAL . " 
                    AND s.uuid IN ({$uuidStr})";

        $result = DssModel::dbRO()->queryAll($sql);
        return $result ?? [];
    }

}