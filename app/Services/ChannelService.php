<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/2/3
 * Time: 3:29 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Models\Dss\DssChannelModel;

class ChannelService
{
    /**
     * 获取一级、二级渠道列表
     * @param $params
     * @return mixed
     */
    public static function getChannels($params)
    {
        $where['status'] = (string)Constants::STATUS_TRUE;
        //业务线ID
        if (!empty($params['app_id'])) {
            $where['app_id'] = (int)$params['app_id'];
        }
        $where['parent_id'] = (int)$params['parent_channel_id'];
        return DssChannelModel::getRecords($where, ['id', 'name', 'app_id']);
    }
}