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
     * @param $appId
     * @return array
     */
    public static function getChannels($parentId, $appId = 0)
    {
        return ChannelModel::getChannels($parentId, $appId);
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
     * 同步channel数据
     * @param $msg
     * @return bool
     */
    public static function sync($msg)
    {
        if (empty($msg)) {
            return false;
        }

        $channel = ChannelModel::getById($msg['id']);
        if(empty($channel)) {
            return ChannelModel::insertChannel($msg['id'] ,
                $msg['name'] ,
                $msg['create_time'],
                $msg['status'],
                $msg['parent_id'],
                $msg['level'],
                $msg['app_id']);
        } else {
            return ChannelModel::updateChannel($msg['id'] ,
                $msg['name'] ,
                $msg['create_time'],
                $msg['status'],
                $msg['parent_id'],
                $msg['level'],
                $msg['app_id']);
        }
    }
}