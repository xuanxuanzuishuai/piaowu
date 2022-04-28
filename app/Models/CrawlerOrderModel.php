<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/4/24
 * Time: 4:49 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class CrawlerOrderModel extends Model
{
    public static $table = 'crawler_order';
    //数据来源:1管易 2抖店
    const SOURCE_GY = 1;
    const SOURCE_DD = 2;
    //爬虫订单数据，加密key
    const CRAWLER_ORDER_AUTH_KEY = 'crawler_order';
    //抖店店铺
    const AI_DOU_DIAN_SHOP_ID = 36298433;//小叶子AI智能陪练
    //管易店铺
    const GUAN_YI_SHOP_ID = 464181282468;//抖音（陪练新）
    //管易店铺和抖店店铺ID映射关系
    const GUAN_YI_DOU_DIAN_SHOP_ID_MAP = [
        self::GUAN_YI_SHOP_ID => self::AI_DOU_DIAN_SHOP_ID
    ];
    //抖店动态设置登陆信息缓存key
    const DD_DYNAMIC_SETTING_LOGIN_CACHE_KEY = 'crawler_dd_dynamic_setting_login';
    //账号爬取数据状态是否可用：1可用 0不可用
    const ACCOUNT_CRAWLER_STATUS_CACHE_KEY = 'crawler_account_status_';
    //商品平台单号推送erp，redis锁，只有一个脚本可以推送，避免多个脚本推送同一个订单
    const GOODS_CODE_PUSH_ERP_LOCK_CACHE_KEY = 'crawler_goods_code_push_erp_lock_';
}