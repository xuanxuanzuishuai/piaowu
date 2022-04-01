<?php
/**
 * 真人周周领奖AB测逻辑
 * User: qingfeng.lian
 * Date: 2022/4/1
 * Time: 18:34
 */

namespace App\Services\TraitService;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\OperationActivityModel;
use App\Models\RealWeekActivityPosterAbModel;
use App\Models\RealWeekActivityUserAllocationABModel;
use App\Models\TemplatePosterModel;
use App\Services\MiniAppQrService;

trait TraitRealWeekActivityTestAbService
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
        $hasTestAb = $params['has_ab_test'] ?? OperationActivityModel::HAS_AB_TEST_NO;
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
                'is_contrast' => RealWeekActivityPosterAbModel::IS_CONTRAST_YES,
                'create_time' => $createTime,
                'poster_type' => TemplatePosterModel::STANDARD_POSTER,
            ];
            foreach ($params['ab_poster_list'] as $info) {
                $hasTestAbList[] = [
                    'activity_id' => $activityId,
                    'poster_id'   => $info['poster_id'],
                    'allocation'  => $allocation,
                    'ab_name'     => $info['ab_name'],
                    'is_contrast' => RealWeekActivityPosterAbModel::IS_CONTRAST_NO,
                    'create_time' => $createTime,
                    'poster_type' => TemplatePosterModel::STANDARD_POSTER,
                ];
            }
            if (array_sum(array_column($hasTestAbList, 'allocation')) != 100) {
                throw new RunTimeException(['allocation_error']);
            }
            // 保存数据
            $res = RealWeekActivityPosterAbModel::batchInsert($hasTestAbList);
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
        RealWeekActivityPosterAbModel::batchDelByActivity($activityId);
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
        $allocation = intval($maxAllocation / ($countNum + 1));
        $contrastAllocation = $maxAllocation - ($allocation * ($countNum));
        return [$contrastAllocation, $allocation];
    }

    /**
     * 获取活动的实验海报列表
     * @param $activityId
     * @return array
     */
    public static function getTestAbList($activityId)
    {
        $list = RealWeekActivityPosterAbModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['is_contrast' => 'DESC']]);
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

    // 获取学生命中的AB测海报
    public static function getStudentTestAbPoster($studentId, $activityId, $extData = [])
    {
        // 查询是否已经有命中的海报，如果有直接返回命中的海报
        $info = RealWeekActivityUserAllocationABModel::getRecord(['activity_id' => $activityId, 'student_id' => $studentId]);
        if (!empty($info)) {
            return self::formatTestAbPoster($info);
        }
        // 获取活动的实验海报列表
        $abPosterList = RealWeekActivityPosterAbModel::getRecords(['activity_id' => $activityId]);
        // 计算命中海报
        $hitPoster = self::calculateHitNode(array_column($abPosterList, 'allocation', 'poster_id'));
        // 生成小程序码
        if (!empty($extData) && !empty($extData['is_create_qr_id'])) {
            $studentType = $extData['user_type'] ?? DssUserQrTicketModel::STUDENT_TYPE;
            $channelId = $extData['channel_id'] ?? 0;
            $landingType = $extData['landing_type'] ?? 0;
            $qrInfo = MiniAppQrService::getUserMiniAppQr(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $studentId, $studentType, $channelId, $landingType,
                [
                    'activity_id' => $activityId,
                    'poster_id'   => $hitPoster['poster_id'],
                    'user_status' => $hitPoster['user_status'],
                ],
                false
            );
            $qrId = $qrInfo['qr_id'] ?? '';
        } else {
            $qrId = '';
        }
        // 保存学生命中海报信息
        self::saveStudentTestAbPosterQrId($hitPoster['poster_id'], $qrId);
        // 对照组海报模板id
        $contrastPosterId = array_column($abPosterList, 'poster_id', 'is_contrast')[1];
        // 返回命中的海报,以及对照组海报id
        return [$contrastPosterId, $hitPoster];
    }

    // 保存用户命中的测试海报数据QrId
    public static function saveStudentTestAbPosterQrId($abTestPosterId, $qrId)
    {
        // TODO qingfeng.lian 保存用户命中实验海报

    }

    // 计算命中
    public static function calculateHitNode($nodeAllocation)
    {
        // TODO qingfeng.lian 计算命中算法
        return [];
    }

    // 格式化测试海报数据让其保持和普通海报一样的数据格式和字段
    public static function formatTestAbPoster($testAbPosterInfo)
    {
        // TODO qingfeng.lian 格式化海报数据
        return $testAbPosterInfo;
    }
}