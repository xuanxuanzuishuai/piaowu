<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/2
 * Time: 3:14 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\ErpPackageModel;
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
            $where[ErpPackageModel::$table . '.id'] = $params['package_id'];
        } elseif (!empty($params['package_name'])) {
            $where[ErpPackageModel::$table . '.name[~]'] = Util::sqlLike($params['package_name']);
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

        if (!empty($params['app_id'])) {
            $where['app_id'] = $params['app_id'];
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
        $packageAppDict = DictConstants::getSet(DictConstants::PACKAGE_APP_NAME);

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
            $r['app_id_zh'] = $packageAppDict[$r['app_id']] ?? '-';

            $r['price'] = sprintf("%.2f", $r['price'] / 100);
        }
        return [$records, $totalCount];
    }

    /**
     * 编辑课包扩展信息
     * @param $params
     * @param $operator
     * @return null
     * @throws RunTimeException
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

        if (isset($params['package_type']) || isset($params['trial_type'])) {
            if ($params['package_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
                $params['trial_type'] = PackageExtModel::TRIAL_TYPE_NONE;
            }

            if (!PackageService::validateTrialType($params['package_type'], $params['trial_type'])) {
                throw new RunTimeException(['invalid_trial_type']);
            }

            $update['package_type'] = $params['package_type'];
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

    /**
     * 检查体验课类型是否合法
     * @param $packageType
     * @param $trialType
     * @return bool
     */
    public static function validateTrialType($packageType, $trialType)
    {
        if ($packageType === null || $trialType === null) {
            return false;
        }

        if ($packageType == PackageExtModel::PACKAGE_TYPE_NONE) {
            return $trialType == PackageExtModel::TRIAL_TYPE_NONE;

        } elseif ($packageType == PackageExtModel::PACKAGE_TYPE_TRIAL) {
            return $trialType == PackageExtModel::TRIAL_TYPE_49 || $trialType == PackageExtModel::TRIAL_TYPE_9;

        } elseif ($packageType == PackageExtModel::PACKAGE_TYPE_NORMAL) {
            return $trialType == PackageExtModel::TRIAL_TYPE_NONE;
        }

        return false;
    }

    /**
     * 获取App销售的课包列表
     * @param array $excludeTypes
     * @param array $excludeIds
     * @return array
     */
    public static function getAppPackages($excludeTypes = [], $excludeIds = [])
    {
        $erpPackages = ErpPackageModel::getOnSalePackages(ErpPackageModel::CHANNEL_APP);

        usort($erpPackages, function ($a, $b) {
            if ($a['oprice'] == $b['oprice']) {
                return $a['package_id'] > $b['package_id'];
            }
            return $a['oprice'] > $b['oprice'];
        });

        $packages = [];
        foreach ($erpPackages as $pkg) {
            if (in_array($pkg['package_id'], $excludeIds)) {
                continue;
            }
            if (in_array($pkg['package_type'], $excludeTypes)) {
                continue;
            }

            if ($pkg['package_type'] == PackageExtModel::PACKAGE_TYPE_TRIAL) {
                $type = 'trial';
            } elseif ($pkg['package_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
                $type = 'normal';
            } else {
                $type = '';
            }

            $packages[] = [
                'package_id' => $pkg['package_id'],
                'package_name' => $pkg['package_name'],
                'price' => strval(round($pkg['sprice'] / 100, 2)),
                'origin_price' => strval(round($pkg['oprice'] / 100, 2)),
                'start_time' => $pkg['start_time'],
                'end_time' => $pkg['end_time'],
                'type' => $type,
            ];
        }

        return $packages;
    }

    /**
     * @param $packageId
     * @return mixed
     */
    public static function getDetail($packageId)
    {
        return PackageExtModel::getByPackageId($packageId);
    }
}