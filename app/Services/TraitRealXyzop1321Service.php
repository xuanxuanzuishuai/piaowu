<?php
/**
 * 真人业务线XYZOP-1321需求
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\RealDictConstants;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\QueueService;

trait TraitRealXyzop1321Service
{
    /**
     * 获取指定活动列表
     * code 意义：  1：代表用户可参与， 2：代表用户不可参与，走原有逻辑
     * @param $studentData
     * @return array
     */
    public static function xyzopGetWeekActivityList($studentData): array
    {
        $returnData = ['code' => 1, 'list' => []];
        // 首次付费时间在指定时间内的， 读取指定的活动
        $activityIds = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, 'real_xyzop_1321_activity_ids');
        // 当前用户首付付费时间是否在指定时间范围内 code = 2
        if (!self::xyzopCheckCondition($studentData)) {
            $returnData['code'] = 2;
            return $returnData;
        }
        // 获取指定活动
        $activityIds  = explode(',', $activityIds);
        $activityList = RealWeekActivityModel::getRecords(
            [
                'activity_id' => $activityIds,
                'ORDER'       => ['id' => 'ASC']
            ],
            [
                'name',
                'activity_id',
                'share_word',
                'banner',
                'share_button_img',
                'award_detail_img',
                'upload_button_img',
                'strategy_img',
                'personality_poster_button_img',
                'share_poster_prompt',
                'retention_copy',
                'poster_order',
                'start_time',
                'end_time',
            ]
        );
        if (empty($activityList)) {
            return $returnData;
        }
        $returnData['list'] = $activityList;
        return $returnData;
    }

    /**
     * 周周领奖tab是否展示
     * @param $studentData
     * @return array
     */
    public static function xyzopWeekActivityTabShowList($studentData): array
    {
        list($firstPayStartTime, $firstPayEndTime) = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, [
            'real_xyzop_1321_first_pay_time_start',
            'real_xyzop_1321_first_pay_time_end',
        ]);
        if (!empty($studentData['first_pay_time']) && $studentData['first_pay_time'] >= $firstPayStartTime && $studentData['first_pay_time'] <= $firstPayEndTime) {
            $weekTab = [
                'title'   => '周周领奖',
                'aw_type' => 'week'
            ];
        }
        return !empty($weekTab) ? $weekTab : [];
    }

    /**
     * 检查是否满足参与条件
     * @param $studentData
     * @return bool
     */
    public static function xyzopCheckCondition($studentData): bool
    {
        $studentFirstPayTime = $studentData['first_pay_time'];
        // 首次付费时间在指定时间内的， 读取指定的活动
        list($startTime, $endTime) = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, [
            'real_xyzop_1321_first_pay_time_start',
            'real_xyzop_1321_first_pay_time_end',
        ]);
        // 当前用户首付付费时间是否在指定时间范围内
        if ($studentFirstPayTime < $startTime || $studentFirstPayTime > $endTime) {
            return false;
        }
        return true;
    }

    /**
     * 检查活动id是否是允许的活动id - 用户审核是否校验活动id
     * true : 活动id在允许不校验活动id范围内， false: 不在范围内
     * @param $specialActivityInfo
     * @return bool
     */
    public static function xyzopCheckIsSpecialActivityId($specialActivityInfo): bool
    {
        // 在指定ID活动内，不校验活动状态
        $specialActivityIds = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, 'real_xyzop_1321_activity_ids');
        $specialActivityIds = explode(',', $specialActivityIds);
        // 当前海报对应的活动是不是在指定活动内
        if (!in_array($specialActivityInfo['activity_id'], $specialActivityIds)) {
            return false;
        }
        return true;
    }

    /**
     * 检查海报对应的活动是不是在特定的活动中
     * true: 在， false: 不在
     * @param array $specialActivityInfo 指定的活动数组 ['activity_id'=>xxx]
     * @param array $allowActivityInfo 生成海报时对应的活动信息 ['activity_id'=>xxx]
     * @return bool
     */
    public static function xyzopCheckIsAllowActivityId(array $specialActivityInfo, array $allowActivityInfo): bool
    {
        // 检查当前上传的活动id是不是特定的活动id
        if (!self::xyzopCheckIsSpecialActivityId($specialActivityInfo)) {
            return false;
        }
        // 检查生成海报对应的活动id是否在允许活动id中
        $allowActivityIds = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, 'real_xyzop_1321_normal_activity_ids');
        $allowActivityIds = explode(',', $allowActivityIds);
        // 当前海报对应的活动是不是在指定活动内
        if (!in_array($allowActivityInfo['activity_id'], $allowActivityIds)) {
            return false;
        }
        return true;
    }

    /**
     * 推送消息 - 推送成功也说明是在特定活动中
     * @param $studentId
     * @param $data
     * @return bool
     */
    public static function xyzopPushRealMsg($studentId, $data): bool
    {
        // 获取模板消息配置
        list($msgId, $msgUrl) = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, [
            'real_xyzop_1321_msg_id',
            'real_xyzop_1321_msg_url',
        ]);
        // 检查是不是在特定活动id中
        if (!self::xyzopCheckIsSpecialActivityId(['activity_id' => 1])) {
            return false;
        }
        // 推送消息
        QueueService::sendUserWxMsg(Constants::REAL_APP_ID, $studentId, $msgId, [
            'replace_params' => [
                'task_name' => $data['activity_name'] ?? '',
                'url'       => $msgUrl,
            ],
        ]);
        return true;
    }

    /**
     * 格式化海报信息 - 特定的活动替换奖励类型和奖励数量
     * @param array $sharePosterInfo
     * @return array
     */
    public static function xyzopFormatOne(array $sharePosterInfo): array
    {
        //获取奖励发放配置
        list($wkIds) = RealDictConstants::get(RealDictConstants::REAL_XYZOP_1321_CONFIG, [
            'real_xyzop_1321_activity_ids',
        ]);
        $wkIds = explode(',', $wkIds);
        if (in_array($sharePosterInfo['activity_id'], $wkIds)) {
            $sharePosterInfo['default_award_amount'] = "人工发放";
            $sharePosterInfo['default_award_copywriting'] = "活动结束后人工发放";
            $sharePosterInfo['award_type'] = 0;
        }
        return $sharePosterInfo;
    }
}
