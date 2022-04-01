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
        $hasTestAb =  $params['has_ab_test'] ?? OperationActivityModel::HAS_AB_TEST_NO;
        $hasTestAbList = [];
        if (!empty($params['has_ab_test']) && !empty($params['ab_poster_list']) && !empty($params['poster'])) {
            if ($hasTestAb == OperationActivityModel::HAS_AB_TEST_ALLOCATION) {
                list($contrastAllocation, $allocation) = self::calculateAllocation(count($params['ab_poster_list']));
            } else {
                $contrastAllocation = $allocation = $params['ab_poster_list'][0]['allocation'];
            }
            $createTime = time();
            $hasTestAbList[] = [
                'activity_id' => $activityId,
                'poster_id'   => $params['poster'][0],
                'allocation'  => $contrastAllocation,
                'ab_name'     => $params['contrast_name'] ?? '对照组',
                'is_contrast' => WeekActivityPosterAbModel::IS_CONTRAST_YES,
                'create_time' => $createTime,
                'poster_type' => TemplatePosterModel::STANDARD_POSTER,
            ];
            foreach ($params['ab_poster_list'] as $info) {
                $hasTestAbList[] = [
                    'activity_id' => $activityId,
                    'poster_id'   => $info['poster_id'],
                    'allocation'  => $allocation,
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
                throw new RunTimeException(['saveAllocationData'], [$activityId, $params]);
            }
        }
        return [$hasTestAb, $hasTestAbList];
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
     * 获取对照组合实验组的分配比例
     * @param $countNum
     * @return array
     */
    public static function calculateAllocation($countNum)
    {
        $maxAllocation = 100;
        $allocation = intval($maxAllocation/($countNum+1));
        $contrastAllocation = $maxAllocation-($allocation*($countNum));
        return [$contrastAllocation, $allocation];
    }

    /**
     * 获取活动的实验海报列表
     * @param $activityId
     * @return array
     */
    public static function getTestAbList($activityId)
    {
        $list = WeekActivityPosterAbModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['is_contrast' => 'DESC']]);
        if (empty($list)) {
            return [];
        }
        $field = ['id', 'name', 'poster_id', 'poster_path', 'example_id', 'example_path', 'order_num', 'practise', 'type'];
        $where = [
            'id' => array_column($list, 'poster_id'),
        ];
        $posterList = TemplatePosterModel::getRecords($where, $field);
        $posterList = array_column($posterList, null, 'id');
        $res = [];
        foreach ($list as $p) {
            $templatePosterInfo = $posterList[$p['poster_id']] ?? [];
            if (empty($templatePosterInfo)) {
                continue;
            }
            $res[] = [
                'activity_id'        => $p['activity_id'],
                'poster_id'          => $p['poster_id'],
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
        }
        return $res;
    }
}