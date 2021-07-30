<?php
/**
 * Landing页召回
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssChannelModel;
use App\Models\LandingRecallLogModel;
use App\Models\LandingRecallModel;
use App\Services\Queue\DssPushMessageTopic;
use Exception;

class LandingRecallService
{
    /**
     * 添加Recall规则
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function add($data, $employeeId)
    {
        $checkAllow = self::checkAllow($data);
        if (!empty($checkAllow)) {
            throw new RunTimeException([$checkAllow]);
        }
        $time = time();
        $data['create_time'] = $time;
        $data['operator_id'] = $employeeId;
        
        // 保存活动总表信息
        $activityId = LandingRecallModel::insertRecord($data);
        
        return $activityId;
    }
    
    /**
     * 修改Recall规则
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function edit($data, $employeeId)
    {
        $checkAllow = self::checkAllow($data);
        if (!empty($checkAllow)) {
            throw new RunTimeException([$checkAllow]);
        }
        $data['update_time'] = time();
        $data['operator_id'] = $employeeId;
        $id = $data['id'];
        unset($data['id']);
        // 更新周周领奖配置信息
        LandingRecallModel::batchUpdateRecord($data, ['id' => $id]);
        return true;
    }
    
    /**
     * 检查是否允许启用
     * @param $data
     * @return bool
     */
    public static function checkAllow($data)
    {
        $id = $data['id'] ?? 0;
        $target = $data['target_population'] ?? 0;
        $sendTime = $data['send_time'] ?? 0;
        $enableStatus = $data['enable_status'] ?? 0;
        if ($enableStatus == LandingRecallModel::ENABLE_STATUS_ON) {
            $res = LandingRecallModel::checkAllow($target, $sendTime);
            if (!empty($res)) {
                if (empty($id)) {   //新增时已有启用状态的规则
                    return 'already_has_enable_rule';
                } elseif ($id != $res['id']) {   //更新时已有启用状态的规则不是本身
                    return 'already_has_enable_rule';
                }
            }
        }
        return '';
    }
    
    /**
     * 获取规则列表
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function searchList($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        list($list, $total) = LandingRecallModel::searchList($params, $limitOffset);
        
        $returnData = ['total_count' => $total, 'list' => []];
        $extInfo = self::getExtInfo();
        foreach ($list as $item) {
            $returnData['list'][] = self::formatActivityInfo($item, $extInfo);
        }
        return $returnData;
    }
    
    /**
     * 获取详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId)
    {
        $activityInfo = LandingRecallModel::getRecord(['id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        $extInfo = self::getExtInfo();
        return self::formatActivityInfo($activityInfo, $extInfo);
    }
    
    /**
     * 格式化数据
     * @param $activityInfo
     * @param $extInfo
     * @return mixed
     */
    public static function formatActivityInfo($activityInfo, $extInfo)
    {
        $targetZh = array_column($extInfo['target_population_zh_map'], 'value','code');
        $sendTimeZh = array_column($extInfo['send_time_zh_map'], 'value','code');
        $enableStatusZh = array_column($extInfo['enable_status_zh_map'], 'value','code');
        $activityInfo['target_population_zh'] = $targetZh[$activityInfo['target_population']] ?? '';
        $activityInfo['send_time_zh'] = $sendTimeZh[$activityInfo['send_time']] ?? '';
        $activityInfo['enable_status_zh'] = $enableStatusZh[$activityInfo['enable_status']] ?? '';
        $activityInfo['channel'] = empty($activityInfo['channel'])?[]:explode(',', $activityInfo['channel']);
        $activityInfo['create_date'] = date('Y-m-d H:i:s', $activityInfo['create_time']);
        $activityInfo['update_date'] = date('Y-m-d H:i:s', $activityInfo['update_time']);
        return $activityInfo;
    }
    
    /**
     * 获取筛选项
     */
    public static function getExtInfo()
    {
        $channel = DssChannelModel::getRecords(['parent_id' => 0, 'status' => DssChannelModel::STATUS_ENABLE]);
        $channelMap = array_column($channel, 'name', 'id');
        return [
            'target_population_zh_map' => self::formatExt(DictConstants::getSet(DictConstants::LANDING_RECALL_TARGET)),
            'send_time_zh_map' => self::formatExt(DictConstants::getSet(DictConstants::LANDING_RECALL_SEND_TIME)),
            'enable_status_zh_map' => self::formatExt(LandingRecallModel::ENABLE_STATUS_MAP),
            'channel_map' => self::formatExt($channelMap),
        ];
    }
    
    /**
     * 格式化Ext
     * @param $data
     * @return array
     */
    private static function formatExt($data)
    {
        $res = [];
        foreach ($data as $k => $v) {
            $res[] = [
                'code' => $k,
                'value' => $v,
            ];
        }
        return $res;
    }
    
    /**
     * 修改启用状态
     * @param $activityId
     * @param $enableStatus
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function editEnableStatus($activityId, $enableStatus, $employeeId)
    {
        if (!in_array(
            $enableStatus,
            [
                LandingRecallModel::ENABLE_STATUS_OFF,
                LandingRecallModel::ENABLE_STATUS_ON,
                LandingRecallModel::ENABLE_STATUS_DISABLE
            ]
        )) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = LandingRecallModel::getRecord(['id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        $data = $activityInfo;
        $data['enable_status'] = $enableStatus;
        $checkAllow = self::checkAllow($data);
        if (!empty($checkAllow)) {
            throw new RunTimeException([$checkAllow]);
        }
        // 修改启用状态
        $res = LandingRecallModel::updateRecord(
            $activityId,
            ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]
        );
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }
        return true;
    }
    
    /**
     * 发送测试短信
     * @param $activityId
     * @param $mobile
     * @return bool
     * @throws RunTimeException
     */
    public static function sendMsg($activityId, $mobile)
    {
        $activityInfo = LandingRecallModel::getRecord(['id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        $smsContent = $activityInfo['sms_content'];
        $voiceCallType = $activityInfo['voice_call_type'];
        $url = DictConstants::get(DictConstants::LANDING_RECALL_URL, 'landing_recall_url');
        if ($_ENV['ENV_NAME'] != 'dev') {
            self::sendSmsAndVoiceProduct($mobile, null, $url, $smsContent, $voiceCallType);
        }
        return true;
    }
    
    /**
     * 发送消息给DSS,在DSS系统发送短信和语音
     * @param $mobile
     * @param $countryCode
     * @param $url
     * @param $smsContent
     * @param $voiceCallType
     * @throws \Exception
     */
    public static function sendSmsAndVoiceProduct($mobile, $countryCode, $url, $smsContent, $voiceCallType)
    {
        $pushMessageData = [
            'mobile' => $mobile,
            'country_code' => $countryCode,
            'base_url' => $url,
            'sms_content' => $smsContent,
            'voice_call_type' => $voiceCallType,
        ];
        try {
            (new DssPushMessageTopic())->sendLandingRecall($pushMessageData)->publish();
        } catch (Exception $e) {
            SimpleLogger::error('landing_recall_send_sms_error', [$e->getMessage()]);
        }
    }
    
    /**
     * 发送短信统计
     * @param $date
     * @return array|null
     */
    public static function sendCount($date)
    {
        $res = LandingRecallLogModel::getSendCount($date);
        $result = [];
        foreach ($res[0]??[] as $k => $v) {
            $result[] = [
                'name' => $k,
                'count' => $v,
            ];
        }
        return $result;
    }
}
