<?php

namespace App\Models\Erp;


use App\Libs\Util;

class ErpPackageV1Model extends ErpModel
{
    public static $table = 'erp_package_v1';
    const PACKAGE_IS_NOT_CUSTOM = 0; // 标准化产品包
    const PACKAGE_IS_CUSTOM = 1; // 自定义产品包
    //售卖渠道 1 Android 2 IOS 4 公众号 8 ERP 16 CRM代客下单 32运营系统代理
    const CHANNEL_ANDROID = 1;
    const CHANNEL_IOS = 2;
    const CHANNEL_WX = 4;
    const CHANNEL_ERP = 8;
    const CHANNEL_OP_AGENT = 32;

    /**
     * 获取课包列表
     * @param $where
     * @param $map
     * @param $having
     * @param $page
     * @param $count
     * @return array
     */
    public static function list($where, $map, $having, $page, $count)
    {
        //结果变量
        $data = [
            'count' => 0,
            'list' => []
        ];
        //设置数据表别名
        $p = self::$table;
        $g = ErpGoodsV1Model::$table;
        $s = ErpPackageStockV1Model::$table;
        $pg = ErpPackageGoodsV1Model::$table;
        //获取数据库对象
        $db = self::dbRO();
        //数据总量
        $total = $db->queryAll("SELECT count(sa.id) count 
                        FROM (select p.id,
                        group_concat(g.id) goods_ids,
                        group_concat(g.name) goods_names
                        FROM {$p} p
                        INNER JOIN {$pg} pg ON p.id = pg.package_id
                        INNER JOIN {$g} g ON g.id = pg.goods_id
                        LEFT JOIN {$s} s ON s.package_id = p.id WHERE {$where} GROUP BY p.id HAVING {$having}) sa", $map);

        if (empty($total[0]['count'])) {
            return $data;
        }
        $data['count'] = $total[0]['count'];
        //生成limit sql
        $limit = Util::limitation($page, $count);
        //获取列表
        $data['list'] = $db->queryAll("SELECT p.id,
                       group_concat(g.id) goods_ids,
                       group_concat(g.name) goods_names,
                       p.thumbs->>'$.thumbs[0]' cover,
                       p.sort,
                       p.name,
                       p.sale_shop,
                       p.channel,
                       p.consume_stock,
                       s.stock,
                       p.price_json,
                       p.status,
                       p.update_time,
                       p.create_time,
                       p.start_time,
                       p.end_time,
                       p.extension
                       FROM {$p} p
                       INNER JOIN {$pg} pg ON p.id = pg.package_id
                       INNER JOIN {$g} g ON g.id = pg.goods_id
                       LEFT JOIN {$s} s ON s.package_id = p.id
                       WHERE {$where}
                       GROUP BY p.id HAVING {$having}
                       ORDER BY p.id DESC {$limit}
                       ", $map);
        return $data;
    }

    /**
     * 根据课包ID和售卖渠道获取数据
     * @param $packageIds
     * @param $channel
     * @param $status
     * @return array|null
     */
    public static function getPackageInfoByIdChannel($packageIds, $channel, $status)
    {
        //获取数据库对象
        $db = self::dbRO();
        $sql = "SELECT
                    id,name 
                FROM
                    erp_package_v1 
                WHERE
                    id IN ( ".implode(',', $packageIds)." ) 
                    AND channel & :channel 
                    AND status=" . $status;
        $map = [
            ":channel" => $channel
        ];
        return $db->queryAll($sql, $map);
    }
}