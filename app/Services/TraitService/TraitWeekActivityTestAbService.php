<?php
/**
 * 智能周周领奖AB测逻辑
 * User: qingfeng.lian
 * Date: 2022/4/1
 * Time: 18:34
 */

namespace App\Services\TraitService;

use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\OperationActivityModel;
use App\Models\TemplatePosterModel;
use App\Models\WeekActivityPosterAbModel;

trait TraitWeekActivityTestAbService
{
    /**
     * 保存到数据的实验数据信息
     * @param $activityId
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function saveAllocationData($activityId, $params)
    {
        SimpleLogger::info('saveAllocationData', [$activityId, $params]);
        $abTestInfo     = $params['ab_test'] ?? [];
        $hasTestAb      = $abTestInfo['has_ab_test'] ?? OperationActivityModel::HAS_AB_TEST_NO;
        $allocationMode = $abTestInfo['allocation_mode'] ?? $abTestInfo['distribution_type'];
        $hasTestAbList  = [];
        if (!empty($abTestInfo['has_ab_test']) && !empty($abTestInfo['ab_poster_list'])) {
            $createTime      = time();
            $hasTestAbList[] = [
                'activity_id' => $activityId,
                'poster_id'   => $abTestInfo['control_group']['id'],
                'allocation'  => $abTestInfo['control_group']['allocation'] ?? 0,
                'ab_name'     => $abTestInfo['control_group']['ab_name'] ?? '对照组',
                'is_contrast' => WeekActivityPosterAbModel::IS_CONTRAST_YES,
                'create_time' => $createTime,
                'poster_type' => TemplatePosterModel::STANDARD_POSTER,
            ];
            foreach ($abTestInfo['ab_poster_list'] as $info) {
                $hasTestAbList[] = [
                    'activity_id' => $activityId,
                    'poster_id'   => $info['id'],
                    'allocation'  => $info['allocation'],
                    'ab_name'     => $info['ab_name'],
                    'is_contrast' => WeekActivityPosterAbModel::IS_CONTRAST_NO,
                    'create_time' => $createTime,
                    'poster_type' => TemplatePosterModel::STANDARD_POSTER,
                ];
            }
            if (array_sum(array_column($hasTestAbList, 'allocation')) != 100) {
                throw new RunTimeException(['allocation_error']);
            }
            // 保存数据
            $res = WeekActivityPosterAbModel::batchInsert($hasTestAbList);
            if (empty($res)) {
                SimpleLogger::info('saveAllocationData_WeekActivityPosterAbModel_batchInsert', [$activityId, $params]);
                throw new RunTimeException(['saveAllocationData']);
            }
        }
        return [$hasTestAb, $allocationMode, $hasTestAbList];
    }

    /**
     * 更新实验组数据
     * @param $activityId
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function updateAllocationData($activityId, $params)
    {
        WeekActivityPosterAbModel::batchDelByActivity($activityId);
        return self::saveAllocationData($activityId, $params);
    }

    /**
     * 获取活动的实验海报列表
     * @param $activityId
     * @return array
     */
    public static function getTestAbList($activityId)
    {
        $contrastInfo = $abPosterList = [];
        $list = WeekActivityPosterAbModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['is_contrast' => 'DESC']]);
        if (empty($list)) {
            return [];
        }
        $field      = ['id', 'name', 'poster_id', 'poster_path', 'example_id', 'example_path', 'order_num', 'practise', 'type'];
        $where      = [
            'id' => array_column($list, 'poster_id'),
        ];
        $posterList = TemplatePosterModel::getRecords($where, $field);
        $posterList = array_column($posterList, null, 'id');
        $res        = [];
        foreach ($list as $p) {
            $templatePosterInfo = $posterList[$p['poster_id']] ?? [];
            if (empty($templatePosterInfo)) {
                continue;
            }
            $_abTestPosterInfo = [
                'activity_id'        => $p['activity_id'],
                'poster_id'          => $p['poster_id'],
                'id'                 => $p['poster_id'],        // 兼容前端后台添加、修改、详情时使用，下次需求需要修改掉
                'poster_type'        => $p['poster_type'],
                'allocation'         => intval($p['allocation']),
                'ab_name'            => $p['ab_name'],
                'create_time'        => $p['create_time'],
                'format_create_time' => date("Y-m-d H:i:s", $p['create_time']),
                'is_contrast'        => $p['is_contrast'],
                'poster_path'        => $templatePosterInfo['poster_path'],
                'poster_name'        => $templatePosterInfo['name'],
                'poster_url'         => AliOSS::replaceCdnDomainForDss($templatePosterInfo['poster_path']),
                'practise_zh'        => TemplatePosterModel::$practiseArray[$templatePosterInfo['practise']] ?? '否',
            ];
            // 区分是对照组还是实验组
            $p['is_contrast'] == WeekActivityPosterAbModel::IS_CONTRAST_YES ? $contrastInfo = $_abTestPosterInfo : $abPosterList[] = $_abTestPosterInfo;
        }
        return [$contrastInfo, $abPosterList];
    }

    /**
     * 检查AB测海报信息
     * @param $params
     * @return string
     */
    public static function checkAbPoster($params)
    {
        // 开启实验海报，则实验海报和标准海报都不能为空
        $abTestInfo = $params['ab_test'] ?? [];
        if ($abTestInfo['has_ab_test'] > 0) {
            if (empty($abTestInfo['ab_poster_list'])) {
                return 'week_activity_ab_poster_test_empty';
            }
            if (empty($abTestInfo['control_group'])) {
                return 'week_activity_standard_poster';
            }
            $allocationMode = $abTestInfo['allocation_mode'] ?? $abTestInfo['distribution_type'];
            if (!in_array($allocationMode, [OperationActivityModel::ALLOCATION_MODE_HAND, OperationActivityModel::ALLOCATION_MODE_AUTO])) {
                return 'allocation_mode_error';
            }
        }
        return '';
    }
}