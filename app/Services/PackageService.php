<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/2
 * Time: 3:14 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Util;
use App\Models\PackageExtModel;

class PackageService
{
    /**
     * 产品包列表
     * @param $params
     * @return array
     */
    public static function packageList($params)
    {
        $where = [];

        if (!empty($params['package_id'])) {
            $where['id'] = $params['package_id'];
        } elseif (!empty($params['package_name'])) {
            $where['name'] = Util::sqlLike($params['package_name']);
        }

        if (isset($params['package_status'])) {
            $where['status'] = $params['package_status'];
        }

        if (!empty($params['package_type'])) {
            $where['package_type'] = $params['package_type'];
        }

        if (!empty($params['trial_type'])) {
            $where['trial_type'] = $params['trial_type'];
        }

        if (!empty($params['apply_type'])) {
            $where['apply_type'] = $params['apply_type'];
        }

        $totalCount = PackageExtModel::getPackagesCount($where);

        if ($totalCount <= 0) {
            return [[], 0];
        }

        list($page, $count) = Util::formatPageCount($params);
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $records = PackageExtModel::getPackages($where);

        $packageStatusDict = DictConstants::getSet(DictConstants::PACKAGE_STATUS);
        $packageChannelDict = DictConstants::getSet(DictConstants::PACKAGE_CHANNEL);
        $packageTypeDict = DictConstants::getSet(DictConstants::PACKAGE_TYPE);
        $applyTypeDict = DictConstants::getSet(DictConstants::APPLY_TYPE);
        $trialTypeDict = DictConstants::getSet(DictConstants::TRIAL_TYPE);

        foreach($records as &$r) {
            $r['package_status_zh'] = $packageStatusDict[$r['package_status']] ?? '-';

            if (!empty($r['package_channel'])) {
                $channelZh = [];
                $channels = explode(',', $r['package_channel']);
                foreach ($channels as $channel) {
                    $channelZh[] = $packageChannelDict[$channel];
                }
                $r['package_channel_zh'] = implode(',', $channelZh);
            } else {
                $r['package_channel_zh'] = '-';
            }

            $r['package_type_zh'] = $packageTypeDict[$r['package_type']] ?? '-';
            $r['apply_type_zh'] = $applyTypeDict[$r['apply_type']] ?? '-';
            $r['trial_type_zh'] = $trialTypeDict[$r['trial_type']] ?? '-';

            $r['price'] = sprintf("%.2f", $r['price'] / 100);
        }
        return [$records, $totalCount];
    }

    /**
     * 编辑课包扩展信息
     * @param $params
     * @param $operator
     * @return null
     */
    public static function packageEdit($params, $operator)
    {
        if (empty($params['package_id'])) {
            return null;
        }

        $update = [
            'update_time' => time(),
            'operator' => $operator,
        ];

        if (isset($params['apply_type'])) {
            $update['apply_type'] = $params['apply_type'];
        }

        if (isset($params['package_type'])) {
            $update['package_type'] = $params['package_type'];
        }

        if (isset($params['trial_type'])) {
            $update['trial_type'] = $params['trial_type'];
        }

        $id = PackageExtModel::getRecord(['package_id' => $params['package_id']], 'id');
        if (empty($id)) {
            $update['package_id'] = $params['package_id'];
            $update['create_time'] = $update['update_time'];
            PackageExtModel::insertRecord($update);
        } else {
            PackageExtModel::batchUpdateRecord($update, ['package_id' => $params['package_id']]);
        }

        return null;
    }

}