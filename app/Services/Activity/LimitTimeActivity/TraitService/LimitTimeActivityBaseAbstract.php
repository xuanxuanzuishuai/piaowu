<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\OperationActivityModel;
use App\Models\TemplatePosterModel;
use App\Services\DictService;

/**
 * 活动客户端管理基础服务接口类：定义一些方法，由子类去实现
 */
abstract class LimitTimeActivityBaseAbstract
{
    public $studentInfo = [];
    public $fromType = '';
    public $appId = 0;
    //上传截图缓存锁key前缀
    const UPLOAD_LOCK_KEY_PREFIX = "limit_time_award_upload_lock_";

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

    /**
     * 获取活动基础数据
     * @param int $appId
     * @param int $countryCode
     * @return array
     */
    public function getActivityBaseData(int $appId, int $countryCode): array
    {
        //查询活动
        $nowTime      = time();
        $where        = [
            'start_time_e'          => $nowTime,
            'end_time_s'            => $nowTime,
            'enable_status'         => OperationActivityModel::ENABLE_STATUS_ON,
            'app_id'                => $appId,
            'activity_country_code' => OperationActivityModel::getWeekActivityCountryCode($countryCode),
        ];
        $activityInfo = LimitTimeActivityModel::searchList($where, [0, 1], [], self::RETURN_ACTIVITY_BASE_DATA_FIELDS);
        if (empty($activityInfo[0])) {
            return [];
        }
        return $activityInfo[0][0];
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
     * 根据uuid批量获取用户信息
     * @param array $uuids
     * @param array $fields
     * @return array
     */
    abstract public static function getStudentInfoByUUID(array $uuids, array $fields = []): array;

    /**
     * 根据手机号获取用户信息
     * @param array $mobiles
     * @param array $fields
     * @return array
     */
    abstract public static function getStudentInfoByMobile(array $mobiles, array $fields = []): array;

    /**
     * 根据学生名称模糊搜索用户信息
     * @param string $name
     * @param array $fields
     * @return array
     */
    abstract public static function getStudentInfoByName(string $name, array $fields = []): array;


    /**
     * 获取海报列表
     * @param $shaPosterList
     * @return array
     */
    public static function getSharePosterList($shaPosterList): array
    {
        $returnData = [];
        if (empty($shaPosterList)) {
            return $returnData;
        }
        $posterIds            = $shaPosterList[TemplatePosterModel::STANDARD_POSTER];
        $personalityPosterIds = $shaPosterList[TemplatePosterModel::INDIVIDUALITY_POSTER];
        $field                = [
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
        $templatePosterList   = TemplatePosterModel::getRecords([
            'id' => array_merge($posterIds, $personalityPosterIds)
        ],
            $field);
        foreach ($templatePosterList as $item) {
            $item['poster_url']          = AliOSS::replaceCdnDomainForDss($item['poster_path']);
            $item['example_url']         = !empty($item['example_path']) ? AliOSS::replaceCdnDomainForDss($item['example_path']) : '';
            $returnData[$item['type']][] = $item;
        }
        unset($item);
        return $returnData;
    }

    /**
     * 格式化数据
     * @param $activityInfo
     * @return array
     */
    public static function formatActivityInfo($activityInfo)
    {
        $info                                            = self::formatActivityTimeStatus($activityInfo);
        $info['format_start_time']                       = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time']                         = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time']                      = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh']                        = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS,
            $activityInfo['enable_status']);
        $info['banner_url']                              = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        $info['share_button_url']                        = AliOSS::replaceCdnDomainForDss($activityInfo['share_button_img']);
        $info['award_detail_url']                        = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        $info['upload_button_url']                       = AliOSS::replaceCdnDomainForDss($activityInfo['upload_button_img']);
        $info['strategy_url']                            = AliOSS::replaceCdnDomainForDss($activityInfo['strategy_img']);
        $info['guide_word']                              = Util::textDecode($activityInfo['guide_word']);
        $info['share_word']                              = Util::textDecode($activityInfo['share_word']);
        $info['personality_poster_button_url']           = AliOSS::replaceCdnDomainForDss($activityInfo['personality_poster_button_img']);
        $info['poster_prompt']                           = Util::textDecode($activityInfo['poster_prompt']);
        $info['poster_make_button_url']                  = AliOSS::replaceCdnDomainForDss($activityInfo['poster_make_button_img']);
        $info['share_poster_prompt']                     = Util::textDecode($activityInfo['share_poster_prompt']);
        $info['retention_copy']                          = Util::textDecode($activityInfo['retention_copy']);
        $info['delay_day']                               = $activityInfo['delay_second'] / Util::TIMESTAMP_ONEDAY;
        $info['format_target_user_first_pay_time_start'] = !empty($activityInfo['target_user_first_pay_time_start']) ? date("Y-m-d H:i:s",
            $activityInfo['target_user_first_pay_time_start']) : '';
        $info['format_target_user_first_pay_time_end']   = !empty($activityInfo['target_user_first_pay_time_end']) ? date("Y-m-d H:i:s",
            $activityInfo['target_user_first_pay_time_end']) : '';
        $info['award_rule']                              = Util::textDecode($info['award_rule']);
        $info['format_target_user']                      = json_decode($activityInfo['target_user'], true);
        return $info;
    }

    /**
     * 获取活动开始文字
     * @param $activityInfo
     * @param $time
     * @return array
     */
    public static function formatActivityTimeStatus($activityInfo, $time = 0)
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
     * @param $appId
     * @param $startTime
     * @param $endTime
     * @param string $activityCountryCode
     * @return array
     */
    public static function getRangeTimeEnableActivity($appId, $startTime, $endTime, $activityCountryCode = '')
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
}