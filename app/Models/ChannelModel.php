<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:33 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class ChannelModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    public static $table = "channel";
    public static $redisExpire = 0;
    public static $redisDB;

    // 渠道等级
    public static $levelsKey = ['S', 'A', 'B', 'C'];

    const ALLOC_STYLE_IMMEDIAT = 0;
    /** 立即分配 */
    const ALLOC_STYLE_DELAY = 1;
    /** 延迟分配 */

    const ALLOCATE_TYPE_TMK = 1;
    const ALLOCATE_TYPE_CC = 2;

    const CHANNEL_VALID = '1';
    const CHANNEL_INVALID = '0';

    /**
     * 获取渠道列表
     * @param $where
     * @return mixed
     */
    public static function getChannelList($where)
    {
        $list = MysqlDB::getDB()->select(self::$table, [
            '[>]' . self::$table . '(parent_channel)' => ['parent_id' => 'id']
        ], [
            self::$table . '.id',
            self::$table . '.name',
            self::$table . '.create_time',
            self::$table . '.status',
            self::$table . '.parent_id',
            self::$table . '.level',
            self::$table . '.app_id',
            self::$table . '.allocate_type',
            self::$table . '.is_delay_allocate',
            self::$table . '.sales_talk',
            'parent_channel.name(parent_name)'
        ], $where);
        return $list;
    }

    /**
     * 获取一级、二级渠道列表
     * @param int $parentId 0 一级渠道 >0 二级渠道
     * @return mixed
     */
    public static function getChannels($parentId = 0)
    {
        return MysqlDB::getDB()->select(self::$table, '*', [
            'AND' => ['status' => '1', 'parent_id' => $parentId],
            'ORDER' => ['name']
        ]);
    }

    /**
     * 添加渠道
     * @param $id
     * @param $name
     * @param $createTime
     * @param $status
     * @param $parentId
     * @param $channelLevel
     * @param $appId
     * @return int|mixed|null|string
     */
    public static function insertChannel($id ,$name ,$createTime, $status, $parentId, $channelLevel, $appId)
    {
        $data = [];
        $data['id'] = $id;
        $data['name'] = $name;
        $data['create_time'] = $createTime;
        $data['status'] = $status;
        $data['parent_id'] = $parentId;
        $data['level'] = $channelLevel;
        $data['app_id'] = $appId;
        return self::insertRecord($data);
    }

    /**
     * 更新渠道
     * @param $id
     * @param $name
     * @param $createTime
     * @param $status
     * @param $parentId
     * @param $channelLevel
     * @param $appId
     * @return bool
     */
    public static function updateChannel($id ,$name ,$createTime, $status, $parentId, $channelLevel, $appId)
    {
        $data = [];
        $data['name'] = $name;
        $data['create_time'] = $createTime;
        $data['status'] = $status;
        $data['parent_id'] = $parentId;
        $data['level'] = $channelLevel;
        $data['update_time'] = time();
        $data['app_id'] = $appId;
        return self::updateRecord($id, $data);
    }

    /**
     * 获取渠道总数
     * @param $where
     * @return number
     */
    public static function getCount($where)
    {
        return MysqlDB::getDB()->count(self::$table, $where);
    }

    /**
     * 获取记录
     * @param $ids array | string
     * @return array
     */
    public static function getRecordsByIds($ids)
    {
        return self::getRecords(['id'=>$ids]);
    }

}
