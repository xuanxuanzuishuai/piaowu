<?php

namespace App\Models\Erp;

class ErpGiftGroupV1Model extends ErpModel
{
    public static $table = 'erp_gift_group_v1';
    const STATUS_NOT_ONLINE = 0; // 未上线
    const STATUS_ONLINE = 1;     // 已上线
    const STATUS_OFFLINE = 2;    // 已下线

    /**
     * 获取课包设置的赠品数据
     * @param $packageIds
     * @return array|mixed
     */
    public static function getOnlineGroup($packageIds)
    {
        if (empty($packageIds)) {
            return [];
        }
        $now = time();
        $data = self::getRecords([
            'package_id' => $packageIds,
            'status' => self::STATUS_ONLINE,
            'start_time[<=]' => $now,
            'end_time[>=]' => $now
        ]);
        return $data;
    }
    /**
     * 判断产品包是否绑定赠品组
     * @param $packageId
     * @return array|null
     */
    public static function getGiftGroupsByPackageId($packageId)
    {
        $sql = "SELECT 
                   *
                FROM
                    ". self::$table ."
                WHERE package_id = :packageId
                  AND status = :status
                  AND start_time <= :time
                  AND end_time   >= :time ";
        $map = [
            ':packageId' => $packageId,
            ':status'    => self::STATUS_ONLINE,
            ':time'      => time(),
        ];
        return self::dbRO()->queryAll($sql, $map);
    }
}
