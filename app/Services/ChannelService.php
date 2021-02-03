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
     * @param int $parentId 父类ID
     * @param $appId
     * @return mixed
     */
    public static function getChannels(int $parentId, int $appId)
    {
        $where['parent_id'] = $parentId;
        $where['app_id'] = $appId;
        $where['status'] = Constants::STATUS_TRUE;
        return DssChannelModel::getRecords($where, ['id', 'name']);
    }
}