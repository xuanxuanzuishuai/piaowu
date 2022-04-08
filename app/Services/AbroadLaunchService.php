<?php
/**
 * 海外投放
 * author: qingfeng.lian
 * date: 2022/4/7
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Models\AbroadLaunchLeadsInputRecordModel;
use App\Services\TraitService\TraitDssAbroadLaunchService;
use App\Services\TraitService\TraitRealAbroadLaunchService;

class AbroadLaunchService
{
    use TraitDssAbroadLaunchService;
    use TraitRealAbroadLaunchService;

    const INPUT_STATUS_ZH = [
        AbroadLaunchLeadsInputRecordModel::INPUT_STATUA_SUCCESS => '已入库',
        AbroadLaunchLeadsInputRecordModel::INPUT_STATUS_REPEAT => '号码重复',
    ];

    /**
     * 海外指定渠道合作商线索入库
     * @param $appId
     * @param $employeeId
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function ChannelSaveLeads($appId, $employeeId, $params)
    {
        SimpleLogger::info("ChannelSaveLeads", [$appId, $employeeId, $params]);
        if ($appId == Constants::REAL_APP_ID) {
            $params['channel_id'] = RealDictConstants::get(RealDictConstants::REAL_CHANNEL_LEADS_CONFIG, 'leads_channel_id');
            (new self())->RealChannelSaveLeads($employeeId, $params);
        } elseif ($appId == Constants::SMART_APP_ID) {
            (new self())->DssChannelSaveLeads($employeeId, $params);
        } else {
            throw new RunTimeException(["app_id_is_required"]);
        }
        return true;
    }

    /**
     * 获取列表
     * @param $appId
     * @param $params
     * @return array
     */
    public static function getList($appId, $params)
    {
        $params['app_id'] = $appId;
        $data = AbroadLaunchLeadsInputRecordModel::getList($params, $params['page'], $params['count']);
        if (!empty($data['list'])) {
            foreach ($data['list'] as &$info) {
                $info = self::formatInfo($info);
            }
        }
        return $data;
    }

    public static function formatInfo($info)
    {
        $info['format_create_time'] = date("Y-m-d H:i:s", $info['create_time']);
        $info['input_status_zh'] = self::INPUT_STATUS_ZH[$info['input_status']] ?? '';
        $info['input_status'] = (int)$info['input_status'];
        return $info;
    }
}