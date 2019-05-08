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
}