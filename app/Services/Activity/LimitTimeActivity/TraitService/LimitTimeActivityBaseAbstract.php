<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\TemplatePosterModel;
use App\Services\DictService;
use App\Services\Queue\Activity\LimitTimeAward\LimitTimeAwardConsumerService;

/**
 * 活动客户端管理基础服务接口类：定义一些方法，由子类去实现
 */
abstract class LimitTimeActivityBaseAbstract implements LimitTimeActivityBaseInterface
{
    public $studentInfo = [];
    public $fromType    = '';
    public $appId       = 0;
    //上传截图缓存锁key前缀
    const UPLOAD_LOCK_KEY_PREFIX = "limit_time_award_upload_lock_";
    // 截图审核缓存锁key前缀
    const VERIFY_SHARE_POSTER_LOCK_KEY_PREFIX = "limit_time_activity_verify_sp_lock_";

    //活动基础字段
    const RETURN_ACTIVITY_BASE_DATA_FIELDS = [
        'a.activity_name',
        'a.activity_id',
        'a.activity_type',
        'a.start_time',
        'a.end_time',
        'a.target_user_type',
        'a.target_user',
        'c.remark',
        'c.share_poster',
        'c.guide_word',
        'c.share_word',
        'c.banner',
        'c.share_button_img',
        'c.award_detail_img',
        'c.upload_button_img',
        'c.strategy_img',
        'c.personality_poster_button_img',
        'c.poster_prompt',
        'c.poster_make_button_img',
        'c.share_poster_prompt',
        'c.retention_copy',
        'c.award_rule',
        'c.remark',
        'c.create_time',
        'c.update_time',
        'c.operator_id',
        'c.first_poster_type_order',
    ];
    // 实例化对象列表
    private static $objList = [];

    /**
     * 获取示例话对象
     * @param $appId
     * @param array $initData
     * @param bool $isNewObj
     * @return DssService
     * @throws RunTimeException
     */
    public static function getAppObj($appId, $initData = [], $isNewObj = false)
    {
        if ($isNewObj == false && !empty(self::$objList[$appId])) {
            return self::$objList[$appId];
        }
        switch ($appId) {
            case Constants::SMART_APP_ID;
                $obj = new DssService($initData['student_info'], $initData['from_type']);
                break;
            case Constants::REAL_APP_ID;
                $obj = new RealService($initData['student_info'], $initData['from_type']);
                break;
            default:
                throw new RunTimeException(['app_id_invalid']);
        }
        self::$objList[$appId] = $obj;
        return self::$objList[$appId];
    }

    /**
     * 根据不同类型的客户端获取注册渠道ID
     * @return array|mixed|null
     */
    public function getChannelByFromType()
    {
        return DictConstants::get(DictConstants::LIMIT_TIME_ACTIVITY_CONFIG, $this->fromType . '_channel_id');
    }

    /**
     * 获取海报列表
     * @param array $shaPosterList
     * @param int $firstPosterTypeOrder
     * @return array
     */
    public static function getSharePosterList(array $shaPosterList, int $firstPosterTypeOrder = 0): array
    {
        $returnData = [];
        if (empty($shaPosterList)) {
            return $returnData;
        }
        $posterIds = $shaPosterList[TemplatePosterModel::STANDARD_POSTER];
        $personalityPosterIds = $shaPosterList[TemplatePosterModel::INDIVIDUALITY_POSTER];
        $field = [
            'id',
            'name',
            'poster_id',
            'poster_path',
            'example_id',
            'example_path',
            'order_num',
            'practise',
            'type'
        ];

		$where['id'] = array_merge($posterIds, $personalityPosterIds);
		if ($firstPosterTypeOrder == TemplatePosterModel::POSTER_ORDER) {
			$where['ORDER'] = ['type' => 'DESC'];
		} else {
			$where['ORDER'] = ['type' => 'ASC'];
		}
        $templatePosterList = TemplatePosterModel::getRecords($where, $field);
        foreach ($templatePosterList as $item) {
            $item['poster_url'] = AliOSS::replaceCdnDomainForDss($item['poster_path']);
            $item['example_url'] = !empty($item['example_path']) ? AliOSS::replaceCdnDomainForDss($item['example_path']) : '';
            $returnData[$item['type']][] = $item;
        }
        unset($item);
        return $returnData;
    }

    /**
     * 格式化数据
     * @param array $activityInfo
     * @return array
     */
    public static function formatActivityInfo(array $activityInfo): array
    {
        $info = self::formatActivityTimeStatus($activityInfo);
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS,
            $activityInfo['enable_status']);
        $info['banner_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        $info['share_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['share_button_img']);
        $info['award_detail_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        $info['upload_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['upload_button_img']);
        $info['strategy_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['strategy_img']);
        $info['guide_word'] = Util::textDecode($activityInfo['guide_word']);
        $info['share_word'] = Util::textDecode($activityInfo['share_word']);
        $info['personality_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['personality_poster_button_img']);
        $info['poster_prompt'] = Util::textDecode($activityInfo['poster_prompt']);
        $info['poster_make_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['poster_make_button_img']);
        $info['share_poster_prompt'] = Util::textDecode($activityInfo['share_poster_prompt']);
        $info['retention_copy'] = Util::textDecode($activityInfo['retention_copy']);
        $info['delay_day'] = $activityInfo['delay_second'] / Util::TIMESTAMP_ONEDAY;
        $info['format_target_user_first_pay_time_start'] = !empty($activityInfo['target_user_first_pay_time_start']) ? date("Y-m-d H:i:s",
            $activityInfo['target_user_first_pay_time_start']) : '';
        $info['format_target_user_first_pay_time_end'] = !empty($activityInfo['target_user_first_pay_time_end']) ? date("Y-m-d H:i:s",
            $activityInfo['target_user_first_pay_time_end']) : '';
        $info['award_rule'] = Util::textDecode($info['award_rule']);
        $info['format_target_user'] = self::formatTargetUser(json_decode($activityInfo['target_user'],
            true));
        return $info;
    }

    /**
     * 格式化目标用户信息
     * @param $targetUser
     * @return mixed
     */
    public static function formatTargetUser($targetUser)
    {
        !empty($targetUser['target_user_first_pay_time_start']) && $targetUser['format_target_user_first_pay_time_start'] = date("Y-m-d H:i:s",
            $targetUser['target_user_first_pay_time_start']);
        !empty($targetUser['target_user_first_pay_time_end']) && $targetUser['format_target_user_first_pay_time_end'] = date("Y-m-d H:i:s",
            $targetUser['target_user_first_pay_time_end']);
        return $targetUser;
    }

    /**
     * 获取活动开始文字
     * @param array $activityInfo
     * @param int $time
     * @return array
     */
    public static function formatActivityTimeStatus(array $activityInfo, int $time = 0): array
    {
        if (empty($time)) {
            $time = time();
        }
        if ($activityInfo['start_time'] <= $time && $activityInfo['end_time'] >= $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_ONGOING;
        } elseif ($activityInfo['start_time'] > $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_PENDING;
        } elseif ($activityInfo['end_time'] < $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_FINISHED;
        }
        $activityInfo['activity_time_status_zh'] = DictService::getKeyValue('activity_time_status',
            $activityInfo['activity_time_status']);
        return $activityInfo;
    }

    /**
     * 获取指定时间内启用的活动列表
     * @param int $appId
     * @param int $startTime
     * @param int $endTime
     * @param string $activityCountryCode
     * @return array
     */
    public static function getRangeTimeEnableActivity(
        int    $appId,
        int    $startTime,
        int    $endTime,
        string $activityCountryCode = ''
    ): array
    {
        $conflictWhere = [
            'start_time[<=]' => $endTime,
            'end_time[>=]'   => $startTime,
            'enable_status'  => OperationActivityModel::ENABLE_STATUS_ON,
            'app_id'         => $appId,
            'ORDER'          => ['activity_id' => 'DESC'],
        ];
        // 如果活动指定了投放地区，搜索时需要区分投放地区
        $activityCountryCode && $conflictWhere['activity_country_code'] = OperationActivityModel::getWeekActivityCountryCode($activityCountryCode);
        $conflictData = LimitTimeActivityModel::getRecords($conflictWhere);
        SimpleLogger::info("LimitTimeActivityBaseAbstract:getRangeTimeEnableActivity", [$conflictWhere, $conflictData]);
        return $conflictData;
    }

    /**
     * 替换url中的参数
     * @param $url
     * @param $params
     * @return mixed|string|string[]
     */
    public static function getMsgJumpUrl($url, $params)
    {
        foreach ($params as $key => $item) {
            $url = str_replace('{{' . $key . '}}', $item, $url);
        }
        unset($key, $item);
        return $url;
    }

    /**
     * 获取推送消息id
     * @param int $appId
     * @param int $activityType
     * @param int $sendAwardStatus
     * @param int $verifyStatus
     * @param int $nextAwardNodeStep
     * @return array|mixed|null
     */
    public static function getWxMsgId(
        int $appId,
        int $activityType,
        int $sendAwardStatus,
        int $verifyStatus,
        int $nextAwardNodeStep = 0
    )
    {
        $type = self::getPushWxMsgAppType($appId);
        $keyCode = '';
        if ($verifyStatus == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
            // 审核拒绝通知
            $keyCode = 'refused_poster';
        } elseif ($verifyStatus == SharePosterModel::VERIFY_STATUS_QUALIFIED && $sendAwardStatus == OperationActivityModel::SEND_AWARD_STATUS_GIVE) {
            // 奖励到账通知
            $keyCode = 'send_award_success';
        } elseif ($verifyStatus == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            // 分享活动审核通过通知
            $keyCode = $activityType . '-verify-pass';
            if ($activityType == OperationActivityModel::ACTIVITY_TYPE_FULL_ATTENDANCE && !empty($nextAwardNodeStep)) {
                // 全勤活动审核通过没有奖励的节点通知
                $keyCode .= '-no-award-node';
            }
        }
        if (empty($keyCode)) {
            return 0;
        }
        return DictService::getKeyValue($type, $keyCode);
    }

    public static function getPushWxMsgAppType($appId) {
        return 'limit_time_activity_msg_' . $appId;
    }

    /**
     * 获取奖励单位
     * @param $awardType
     * @param bool $isMsg
     * @param int $verifyStatus
     * @return string
     */
    public static function getAwardUnit($awardType, $isMsg = true, $verifyStatus = SharePosterModel::VERIFY_STATUS_QUALIFIED): string
    {
        $awardUnit = '';
        switch ($awardType) {
            case Constants::AWARD_TYPE_TIME:
                $awardUnit = $isMsg ? '天学习时长' : '天';
                if ($verifyStatus == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
                    $awardUnit = $isMsg ? '学习时长' : '';
                }
                break;
            case Constants::AWARD_TYPE_GOLD_LEAF:
                $awardUnit = '金叶子';
                break;
            case Constants::AWARD_TYPE_MAGIC_STONE:
                $awardUnit = '魔法石';
                break;
        }
        return $awardUnit;
    }

	/**
	 * 获取海报模版占用情况：用于海报下线判断使用
	 * @param int $posterTemplateId		海报模板ID
	 * @return array
	 */
	public static function getPosterTemplateOccupation(int $posterTemplateId): array
	{
		$activityIds = [];
		$nowTime = time();
		$activityList = LimitTimeActivityModel::searchList(['start_time_e' => $nowTime, 'end_time_s' => $nowTime], [], [], ['c.share_poster']);
		if (empty($activityList[0])) {
			return $activityIds;
		}
		foreach ($activityList[0] as $avl) {
			foreach (json_decode($avl['share_poster'], true) as $sv) {
				if (in_array($posterTemplateId, $sv)) {
					$activityIds[] = $avl['activity_id'];
				}
			}
		}
		return $activityIds;
	}
}