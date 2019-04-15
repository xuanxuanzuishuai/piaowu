<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/8
 * Time: 5:59 PM
 */

namespace App\Services;


use App\Models\ChannelModel;

class ChannelService
{
    /**
     * 获取用户渠道信息
     * @param $parentId
     * @return array
     */
    public static function getChannels($parentId)
    {
        return ChannelModel::getChannels($parentId);
    }

    /**
     * 获取用户渠道
     * @param $id
     * @return mixed|null
     */
    public static function getChannelById($id)
    {
        return ChannelModel::getById($id);
    }

    /**
     * 获取渠道map
     * @param $chanelIdArray
     * @return array
     */
    public static function getChannelMap($chanelIdArray)
    {
        $records = ChannelModel::getRecordsByIds($chanelIdArray);
        $data = [];
        foreach($records as $record){
            $data[$record['id']] = $record['name'];
        }
        return $data;
    }

    /**
     * crm 同步channel数据
     * @param $id
     * @param $name
     * @param $createTime
     * @param $status
     * @param $parentId
     * @param $channelLevel
     * @param $appId
     * @return bool|int|mixed|null|string
     */
    public static function crmSyncRecord($id ,$name ,$createTime, $status, $parentId, $channelLevel, $appId)
    {
        $channel = ChannelModel::getById($id);
        if(empty($channel)){
            return ChannelModel::insertChannel($id ,$name ,$createTime, $status, $parentId, $channelLevel, $appId);
        }else{
            return ChannelModel::updateChannel($id ,$name ,$createTime, $status, $parentId, $channelLevel, $appId);
        }
    }
}