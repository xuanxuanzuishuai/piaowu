<?php
/**
 * 月月有奖
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\ActivityPosterModel;
use App\Models\MonthActivityModel;
use App\Models\OperationActivityModel;
use App\Models\TemplatePosterModel;

class MonthActivityService
{
    /**
     * 添加周周领奖活动
     * @param $data
     * @return bool
     * @throws RunTimeException
     */
    public static function add($data, $employeeId)
    {
        $checkAllowAdd = WeekActivityService::checkAllowAdd($data);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        $time = time();
        $activityData = [
            'name' => $data['name'] ?? '',
            'app_id' => Constants::SMART_APP_ID,
            'create_time' => $time,
        ];
        $monthActivityData = [
            'name' => $activityData['name'],
            'activity_id' => 0,
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'banner' => $data['banner'] ?? '',
            'make_poster_button_img' => $data['make_poster_button_img'] ?? '',
            'make_poster_tip_word' => !empty($data['make_poster_tip_word']) ? Util::textEncode($data['make_poster_tip_word']) : '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'create_poster_button_img' => $data['create_poster_button_img'] ?? '',
            'share_poster_tip_word' => !empty($data['share_poster_tip_word']) ? Util::textEncode($data['share_poster_tip_word']) : '',
            'operator_id' => $employeeId,
            'create_time' => $time,
        ];

        $activityExtData = [
            'activity_id' => 0,
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 保存活动总表信息
        $activityId = OperationActivityModel::insertRecord($activityData);
        if (empty($activityId)) {
            $db->rollBack();
            SimpleLogger::info("MonthActivityService:add insert operation_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["add month activity fail"]);
        }
        // 保存周周领奖配置信息
        $monthActivityData['activity_id'] = $activityId;
        $monthActivityId = MonthActivityModel::insertRecord($monthActivityData);
        if (empty($monthActivityId)) {
            $db->rollBack();
            SimpleLogger::info("MonthActivityService:add insert week_activity fail", ['data' => $monthActivityId]);
            throw new RunTimeException(["add month activity fail"]);
        }
        // 保存周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $activityExtId = ActivityExtModel::insertRecord($activityExtData);
        if (empty($activityExtId)) {
            $db->rollBack();
            SimpleLogger::info("MonthActivityService:add insert activity_ext fail", ['data' => $activityExtData]);
            throw new RunTimeException(["add month activity fail"]);
        }
        // 保存海报关联关系
        $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $data['poster']);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("MonthActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add month activity fail"]);
        }
        $db->commit();
        return $activityId;
    }

    /**
     * 月月有奖列表
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function searchList($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        if (!empty($params['start_time_s'])) {
            $params['start_time_s'] = strtotime($params['start_time_s']);
        }
        if (!empty($params['start_time_e'])) {
            $params['start_time_e'] = strtotime($params['start_time_e']);
        }
        list($list, $total) = MonthActivityModel::searchList($params, $limitOffset);

        // 获取备注
        $activityIds = array_column($list, 'activity_id');
        if (!empty($activityIds)) {
            $activityExtList = ActivityExtModel::getRecords(['activity_id' => $activityIds]);
            $activityExtArr = array_column($activityExtList, null, 'activity_id');
        }

        $returnData = ['total_count' => $total, 'list' => []];
        foreach ($list as $item) {
            $extInfo = $activityExtArr[$item['activity_id']] ?? [];
            $returnData['list'][] = self::formatActivityInfo($item, $extInfo);
        }
        return $returnData;
    }

    /**
     * 格式化数据
     * @param $activityInfo
     * @param $extInfo
     * @return mixed
     */
    public static function formatActivityInfo($activityInfo, $extInfo)
    {
        // 处理海报
        if (!empty($activityInfo['poster']) && is_array($activityInfo['poster'])) {
            foreach ($activityInfo['poster'] as $k => $p) {
                $activityInfo['poster'][$k]['poster_url'] = AliOSS::replaceCdnDomainForDss($p['poster_path']);
                $activityInfo['poster'][$k]['example_url'] = !empty($p['example_path']) ? AliOSS::replaceCdnDomainForDss($p['example_path']) : '';
            }
        }
        $info = $activityInfo;
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS, $activityInfo['enable_status']);
        $info['banner_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        $info['make_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['make_poster_button_img']);
        $info['award_detail_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        $info['create_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['create_poster_button_img']);
        $info['make_poster_tip_word'] = Util::textDecode($activityInfo['make_poster_tip_word']);
        $info['share_poster_tip_word'] = Util::textDecode($activityInfo['share_poster_tip_word']);

        $time = time();
        $info['activity_status_zh'] = WeekActivityService::getActivityStartDict($activityInfo, $time);

        if (empty($info['remark'])) {
            $info['remark'] = $extInfo['remark'] ?? '';
        }

        return $info;
    }

    /**
     * 获取月月有奖活动详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId)
    {
        $activityInfo = MonthActivityModel::getDetailByActivityId($activityId);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 获取海报列表
        $posterList = ActivityPosterModel::getListByActivityId($activityId);
        if (!empty($posterList)) {
            SimpleLogger::info("getDetailById_get_poster_is_empty", ['activity_id' => $activityId]);
            // 获取海报库图片信息
            $posterUrlList = TemplatePosterModel::getRecords(['id' => array_column($posterList, 'poster_id')]);
            $activityInfo['poster'] = $posterUrlList ?? [];
        }

        return self::formatActivityInfo($activityInfo, []);
    }

    /**
     * 更改月月有奖的状态
     * @param $activityId
     * @param $enableStatus
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function editEnableStatus($activityId, $enableStatus, $employeeId)
    {
        if (!in_array($enableStatus, [OperationActivityModel::ENABLE_STATUS_OFF, OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_DISABLE])) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = MonthActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        // 如果是启用 检查时间是否冲突
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            $startActivity = MonthActivityModel::checkTimeConflict($activityInfo['start_time'], $activityInfo['end_time']);
            if (!empty($startActivity)) {
                throw new RunTimeException(['activity_time_conflict_id', '', '', array_column($startActivity, 'activity_id')]);
            }
        }

        // 修改启用状态
        $res = MonthActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }

        // 删除缓存
        ActivityService::delActivityCache(
            $activityId,
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE,
            ],
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE . '_poster_type' => TemplatePosterModel::INDIVIDUALITY_POSTER,   // 月月领奖 - 个性化海报
            ]
        );

        return true;
    }

    /**
     * 添加周周领奖活动
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function edit($data, $employeeId)
    {
        $checkAllowAdd = WeekActivityService::checkAllowAdd($data);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        // 检查是否存在
        if (empty($data['activity_id'])) {
            throw new RunTimeException(['record_not_found']);
        }
        $activityId = intval($data['activity_id']);
        $monthActivityInfo = MonthActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($monthActivityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }

        // 判断海报是否有变化，没有变化不操作
        $isDelPoster = ActivityPosterModel::diffPosterChange($activityId, $data['poster']);

        // 开始处理更新数据
        $time = time();
        // 检查是否有海报
        $activityData = [
            'name' => $data['name'] ?? '',
            'update_time' => $time,
        ];
        $monthActivityData = [
            'name' => $activityData['name'],
            'activity_id' => $activityId,
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'banner' => $data['banner'] ?? '',
            'make_poster_button_img' => $data['make_poster_button_img'] ?? '',
            'make_poster_tip_word' => !empty($data['make_poster_tip_word']) ? Util::textEncode($data['make_poster_tip_word']) : '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'create_poster_button_img' => $data['create_poster_button_img'] ?? '',
            'share_poster_tip_word' => !empty($data['share_poster_tip_word']) ? Util::textEncode($data['share_poster_tip_word']) : '',
            'operator_id' => $employeeId,
            'create_time' => $time,
        ];

        $activityExtData = [
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动总表信息
        $res = OperationActivityModel::batchUpdateRecord($activityData, ['id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update operation_activity fail", ['data' => $activityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖配置信息
        $res = MonthActivityModel::batchUpdateRecord($monthActivityData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update week_activity fail", ['data' => $monthActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update activity_ext fail", ['data' => $activityExtData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 当海报有变化时删除原有的海报
        if ($isDelPoster) {
            // 删除海报关联关系
            $res = ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add is del activity_poster fail", ['data' => $data, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
            // 写入新的活动与海报的关系
            $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $data['poster']);
            if (empty($activityPosterRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $data, 'activity_id' => $activityId]);
                throw new RunTimeException(["add week activity fail"]);
            }
        }

        $db->commit();

        // 删除缓存
        ActivityService::delActivityCache(
            $activityId,
            [
                ActivityPosterModel::KEY_ACTIVITY_POSTER,
                ActivityExtModel::KEY_ACTIVITY_EXT,
                OperationActivityModel::KEY_CURRENT_ACTIVE,
            ],
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE . '_poster_type' => TemplatePosterModel::INDIVIDUALITY_POSTER,   // 月月领奖 - 个性化海报
            ]
        );
        return true;
    }
}