<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/26
 * Time: 6:19 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ErpPackageModel extends Model
{
    public static $table = 'erp_package';

    const APP_AI = 8;

    // 1 APP 2 公众号 3 ERP
    const CHANNEL_APP = 1;
    const CHANNEL_WX = 2;
    const CHANNEL_ERP = 3;

    public static function getPackAgeList($where)
    {
        return MysqlDB::getDB()->select(self::$table, ['id', 'name'], $where);
    }

    /**
     * 获取正在售卖的产品包
     * @param $channel
     * @return array
     */
    public static function getOnSalePackages($channel)
    {
        if (!in_array($channel, [self::CHANNEL_APP, self::CHANNEL_WX, self::CHANNEL_ERP])) {
            return [];
        }

        $sql = "SELECT 
    p.id AS package_id,
    p.name AS package_name,
    p.start_time,
    p.end_time,
    SUM(g.oprice) oprice,
    SUM(g.sprice) sprice,
    SUM(g.dprice) dprice,
    SUM(g.num + g.free_num) num,
    g.type goods_type,
    pe.package_type,
    pe.trial_type,
    pe.apply_type
FROM
    erp_package AS p
        INNER JOIN
    erp_goods_package AS gp ON gp.package_id = p.id AND gp.status = 1
        INNER JOIN
    erp_goods AS g ON g.id = gp.goods_id
        LEFT JOIN
    package_ext AS pe ON pe.package_id = p.id
WHERE
    app_id = 8 AND is_show = 1
        AND is_sale = 1
        AND p.status = 1
        AND p.start_time <= UNIX_TIMESTAMP()
        AND p.end_time >= UNIX_TIMESTAMP()
        AND channel LIKE '%{$channel}%'
GROUP BY p.id;";

        $records = MysqlDB::getDB()->queryAll($sql);
        return $records ?? [];
    }
}