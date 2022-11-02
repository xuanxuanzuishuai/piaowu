<?php
/**
 * 微信openid关注信息表
 */

namespace App\Models;

class WechatOpenidListModel extends Model
{
    public static $table = 'wechat_openid_list';

    //关注公众号信息 1 为关注微信公众号 2为取关微信公众号',
    const SUBSCRIBE_WE_CHAT   = 1;
    const UNSUBSCRIBE_WE_CHAT = 2;

    /**
     * 关注公众号
     * @param $openid
     * @param $appid
     * @return void
     */
    public static function subscribe($openid, $appid)
    {
        $time = time();
        $hasSub = self::getRecord(['openid' => $openid, 'appid' => $appid]);
        if (empty($hasSub)) {
            self::insertRecord([
                'openid'              => $openid,
                'appid'               => $appid,
                'status'              => self::SUBSCRIBE_WE_CHAT,
                'last_subscribe_time' => $time,
                'create_time'         => $time,
            ]);
        } else {
            self::updateRecord($hasSub['id'], [
                'status'              => self::SUBSCRIBE_WE_CHAT,
                'last_subscribe_time' => $time,
                'update_time'         => $time,
            ]);
        }
    }

    /**
     * 取消关注
     * @param $openid
     * @param $appid
     * @return void
     */
    public static function unsubscribe($openid, $appid)
    {
        self::batchUpdateRecord(
            [
                'status'      => self::UNSUBSCRIBE_WE_CHAT,
                'update_time' => time(),
            ],
            [
                'openid' => $openid,
                'appid'  => $appid,
            ]
        );
    }

    /**
     * 获取关注列表
     * @param $openid
     * @param $appid
     * @return array
     */
    public static function getSubList($openid, $appid)
    {
        if (empty($openid)) {
            return [];
        }
        $list = self::getRecords([
            'openid' => $openid,
            'appid'  => $appid,
            'status' => self::SUBSCRIBE_WE_CHAT,
        ]);
        return is_array($list) ? $list : [];
    }
}