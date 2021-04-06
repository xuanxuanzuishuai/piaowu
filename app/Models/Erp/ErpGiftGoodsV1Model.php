<?php
namespace App\Models\Erp;

use App\Libs\AliOSS;

class ErpGiftGoodsV1Model extends ErpModel
{
    const STATUS_NORMAL = 1; //使用
    const STATUS_DISABLED = 2; //删除

    public static $table = 'erp_gift_goods_v1';

    /**
     * 产品包包含的赠品组
     * @param $package_id
     * @param bool $format
     * @return array
     */
    public static function getOnlineGroupGifts($package_id, $format = false)
    {
        $gift_group = ErpGiftGroupV1Model::getTableNameWithDb();
        $gift_goods = self::getTableNameWithDb();
        $goods      = ErpGoodsV1Model::getTableNameWithDb();

        $sql = "
          SELECT 
          `group`.id as gift_group_id,
          `group`.optional_num,
           gift.id as gift_goods_id,
           goods.id as goods_id,
           goods.name as goods_name,
           goods.thumbs ->> '$[0]' thumb
          FROM " . $gift_group . " `group`
          INNER JOIN " . $gift_goods . " gift on gift.gift_group_id = `group`.id and gift.status = " . self::STATUS_NORMAL . "
          INNER JOIN " . $goods . " goods on goods.id = gift.goods_id
          WHERE `group`.package_id = :package_id
          and   `group`.status = " . ErpGiftGroupV1Model::STATUS_ONLINE . "
          and   `group`.start_time <= :time
          and   `group`.end_time   >= :time
          ORDER BY  `group`.id desc";

        $map = [
            ':package_id' => $package_id,
            ':time'       => time()
        ];

        $result = self::dbRO()->queryAll($sql, $map);

        if ($format) {
            $giftGroupGoods = [];
            foreach ($result as $v) {
                $v['thumb'] = AliOSS::replaceShopCdnDomain($v['thumb']);
                $giftGroupGoods[$v['gift_group_id']][] = $v;
            }
            return array_values($giftGroupGoods);
        }
        return $result ?? [];
    }
}
