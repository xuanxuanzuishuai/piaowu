<?php
/**
 * 智能 - 邀请有奖
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
use App\Models\InviteActivityModel;
use App\Models\OperationActivityModel;
use App\Models\TemplatePosterModel;

class InviteActivityService
{
    /**
     * 添加活动
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
            'app_id' => Constants::REAL_APP_ID,
            'create_time' => $time,
        ];
        $inviteActivityData = [
            'name' => $activityData['name'],
            'activity_id' => 0,
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'banner' => $data['banner'] ?? '',
            'make_poster_button_img' => $data['make_poster_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'operator_id' => $employeeId,
            'create_time' => $time,
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
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
            SimpleLogger::info("InviteActivityService:add insert operation_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["add invite activity fail"]);
        }
        // 保存周周领奖配置信息
        $inviteActivityData['activity_id'] = $activityId;
        $inviteActivityId = InviteActivityModel::insertRecord($inviteActivityData);
        if (empty($inviteActivityId)) {
            $db->rollBack();
            SimpleLogger::info("InviteActivityService:add insert week_activity fail", ['data' => $inviteActivityId]);
            throw new RunTimeException(["add invite activity fail"]);
        }
        // 保存周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $activityExtId = ActivityExtModel::insertRecord($activityExtData);
        if (empty($activityExtId)) {
            $db->rollBack();
            SimpleLogger::info("InviteActivityService:add insert activity_ext fail", ['data' => $activityExtData]);
            throw new RunTimeException(["add invite activity fail"]);
        }
        // 保存海报关联关系
        $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $data['poster']);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("InviteActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add invite activity fail"]);
        }
        $db->commit();
        return $activityId;
    }

    /**
     * 活动列表
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
        list($list, $total) = InviteActivityModel::searchList($params, $limitOffset);

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
        $info = WeekActivityService::formatActivityTimeStatus($activityInfo);
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS, $activityInfo['enable_status']);
        $info['banner_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        $info['make_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['make_poster_button_img']);
        $info['award_detail_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        $info['share_word'] = Util::textDecode($activityInfo['share_word']);

        if (empty($info['remark'])) {
            $info['remark'] = $extInfo['remark'] ?? '';
        }
        if (!empty($info['award_rule'])) {
            $awardRule = $info['award_rule'];
        } else {
            $awardRule = $extInfo['award_rule'] ?? '';
        }
        $info['award_rule'] = Util::textDecode($awardRule);
        return $info;
    }

    /**
     * 获取活动详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId)
    {
        $activityInfo = InviteActivityModel::getDetailByActivityId($activityId);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 获取活动海报
        $activityInfo['poster'] = PosterService::getActivityPosterList($activityInfo);
        return self::formatActivityInfo($activityInfo, []);
    }

    /**
     * 更改活动的状态
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
        $activityInfo = InviteActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        // 如果是启用 检查时间是否冲突
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            $startActivity = InviteActivityModel::checkTimeConflict($activityInfo['start_time'], $activityInfo['end_time']);
            if (!empty($startActivity)) {
                throw new RunTimeException(['activity_time_conflict_id', '', '', array_column($startActivity, 'activity_id')]);
            }
        }

        // 修改启用状态
        $res = InviteActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
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
     * 修改活动
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
        $inviteActivityInfo = InviteActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($inviteActivityInfo)) {
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
        $inviteActivityData = [
            'name' => $activityData['name'],
            'activity_id' => $activityId,
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'banner' => $data['banner'] ?? '',
            'make_poster_button_img' => $data['make_poster_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'operator_id' => $employeeId,
            'update_time' => $time,
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
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
            SimpleLogger::info("InviteActivityService:add update operation_activity fail", ['data' => $activityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖配置信息
        $res = InviteActivityModel::batchUpdateRecord($inviteActivityData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("InviteActivityService:add update week_activity fail", ['data' => $inviteActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("InviteActivityService:add update activity_ext fail", ['data' => $activityExtData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 当海报有变化时删除原有的海报
        if ($isDelPoster) {
            // 删除海报关联关系
            $res = ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("InviteActivityService:add is del activity_poster fail", ['data' => $data, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
            // 写入新的活动与海报的关系
            $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $data['poster']);
            if (empty($activityPosterRes)) {
                $db->rollBack();
                SimpleLogger::info("InviteActivityService:add batch insert activity_poster fail", ['data' => $data, 'activity_id' => $activityId]);
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
