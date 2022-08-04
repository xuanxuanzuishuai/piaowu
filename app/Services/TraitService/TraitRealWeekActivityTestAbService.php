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
use App\Libs\Meta;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityPosterModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\OperationActivityModel;
use App\Models\RealWeekActivityModel;
use App\Models\RealWeekActivityPosterAbModel;
use App\Models\RealWeekActivityUserAllocationABModel;
use App\Models\TemplatePosterModel;
use App\Services\Activity\RealWeekActivity\RealWeekActivityClientService;
use App\Services\MiniAppQrService;
use App\Models\EmployeeModel;
use App\Services\PosterService;

trait TraitRealWeekActivityTestAbService
{
    /**
     * 保存到数据的实验数据信息
     * @param $activityId
     * @param $params
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function saveAllocationData($activityId, $params, $employeeId)
    {
        SimpleLogger::info('saveAllocationData', [$activityId, $params]);
        $abTestInfo = $params['ab_test'] ?? [];
        $hasTestAb = $abTestInfo['has_ab_test'] ?? OperationActivityModel::HAS_AB_TEST_NO;
        $allocationMode = $abTestInfo['allocation_mode'] ?? $abTestInfo['distribution_type'];
        $hasTestAbList = [];
        if (!empty($abTestInfo['has_ab_test']) && !empty($abTestInfo['ab_poster_list'])) {
            $createTime = time();
            $hasTestAbList[] = [
                'activity_id' => $activityId,
                'poster_id'   => $abTestInfo['control_group']['id'],
                'allocation'  => $abTestInfo['control_group']['allocation'] ?? 0,
                'ab_name'     => $abTestInfo['control_group']['ab_name'] ?? '对照组',
                'is_contrast' => RealWeekActivityPosterAbModel::IS_CONTRAST_YES,
                'create_time' => $createTime,
                'poster_type' => TemplatePosterModel::STANDARD_POSTER,
            ];
            foreach ($abTestInfo['ab_poster_list'] as $info) {
                $hasTestAbList[] = [
                    'activity_id' => $activityId,
                    'poster_id'   => $info['id'],
                    'allocation'  => $info['allocation'],
                    'ab_name'     => $info['ab_name'],
                    'is_contrast' => RealWeekActivityPosterAbModel::IS_CONTRAST_NO,
                    'create_time' => $createTime,
                    'poster_type' => TemplatePosterModel::STANDARD_POSTER,
                ];
            }
            if (array_sum(array_column($hasTestAbList, 'allocation')) != 100) {
                throw new RunTimeException(['allocation_error']);
            }
            // 检查是否有修改
            list($isChange, $changePosterList, $allChangePosterList) = self::checkAbPosterIsChange($activityId, $hasTestAbList);
            // 有变动
            if ($isChange) {
                // 删除原数据
                $res = RealWeekActivityPosterAbModel::batchDelByActivity($activityId);
                if (empty($res)) {
                    SimpleLogger::info('saveAllocationData_WeekActivityPosterAbModel_del', [$activityId, $params]);
                    throw new RunTimeException(['saveAllocationData']);
                }
                // 保存数据
                $res = RealWeekActivityPosterAbModel::batchInsert($hasTestAbList);
                if (empty($res)) {
                    SimpleLogger::info('saveAllocationData_WeekActivityPosterAbModel_batchInsert', [$activityId, $params]);
                    throw new RunTimeException(['saveAllocationData']);
                }
                // 保存日志
                $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
                (new Meta())->saveLog([
                    'table_name'    => RealWeekActivityPosterAbModel::$table,
                    'data_id'       => $activityId,
                    'old_value'     => $allChangePosterList['old'],
                    'new_value'     => $allChangePosterList['new'],
                    'menu_name'     => '真人保存周周领奖实验海报',
                    'event_type'    => 6001,
                    'operator_uuid' => $employeeInfo['uuid'],
                    'operator_name' => $employeeInfo['name'],
                    'operator_time' => time(),
                    'source_app_id' => Constants::SELF_APP_ID,
                ]);
            }
            // 计算分配比例的关键数据有变动
            if ($isChange && !empty($changePosterList)) {
                SimpleLogger::info("saveAllocationData_WeekActivityPosterAbModel_change", [$activityId, $params, $isChange, $changePosterList]);
                // 重新分配比例时，生成新的版本
                RealWeekActivityClientService::createVersion($activityId, $hasTestAbList, $employeeId);
                // 初始化分配比例
                self::initNodeAllocation($activityId, $hasTestAbList);
            }
        }
        return [$hasTestAb, $allocationMode, $hasTestAbList];
    }

    /**
     * 检查海报是否有变化
     * @param $activityId
     * @param $abPosterList
     * @return array
     */
    public static function checkAbPosterIsChange($activityId, $abPosterList)
    {
        $isChange = false;      // 是否有变动
        $changePosterList = []; // 影响计算分配比例的关键数据变动
        $allChangePosterList = []; // 任何数据有变动
        $orglist = array_column(RealWeekActivityPosterAbModel::getRecords(['activity_id' => $activityId]) ?? [] , null, 'poster_id');
        // 检查数据
        foreach ($abPosterList as $item) {
            // 检查是否是新增
            $_dbInfo = $orglist[$item['poster_id']] ?? [];
            if (empty($_dbInfo)) {
                $isChange = true;
                $allChangePosterList['new'][] = $changePosterList['new'][] = $item;
                continue;
            }
            unset($orglist[$item['poster_id']]);    // 移除本次提交已经有的海报
            // 检查是否修改了比例
            if ($_dbInfo['allocation'] != $item['allocation']) {
                $isChange = true;
                $allChangePosterList['old'][] = $changePosterList['old'][] = $_dbInfo;
                $allChangePosterList['new'][] = $changePosterList['new'][] = $item;
                continue;
            }
            // 检查名称是否有改动
            if ($_dbInfo['ab_name'] != $item['ab_name']) {
                $isChange = true;
                $allChangePosterList['old'][] = $_dbInfo;
                $allChangePosterList['new'][] = $item;
                continue;
            }
        }
        if (!empty($orglist)) {
            // 剩下的就是被删除的
            $allChangePosterList['old'] = array_merge($allChangePosterList['old'] ?? [], $orglist);
            $changePosterList['old'] = array_merge($changePosterList['old'] ?? [], $orglist);
        }
        return [$isChange, $changePosterList, $allChangePosterList];
    }

    /**
     * 获取活动的实验海报列表
     * @param $activityId
     * @return array
     */
    public static function getTestAbList($activityId)
    {
        $contrastInfo = $abPosterList = [];
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
                'practise'           => $templatePosterInfo['practise'],
            ];
            // 区分是对照组还是实验组
            $p['is_contrast'] == RealWeekActivityPosterAbModel::IS_CONTRAST_YES ? $contrastInfo = $_abTestPosterInfo : $abPosterList[] = $_abTestPosterInfo;
        }
        return [$contrastInfo, $abPosterList];
    }

    /**
     * 获取学生命中的AB测海报
     * @param $studentId
     * @param $activityId
     * @param $extData
     * @return array
     * @throws RunTimeException
     */
    public static function getStudentTestAbPoster($studentId, $activityId, $extData = [])
    {
        // 查询是否已经有命中的海报，如果有直接返回命中的海报
        $info = self::getStudentHitPoster($activityId, $studentId);
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        SimpleLogger::info("qingfeng-test-real-getStudentTestAbPoster", ['msg' => 'msg-start', 'student_id' => $studentId, 'activity_id' => $activityId, 'has_ab_test' => $activityInfo['has_ab_test']]);
        // 是否开启了ab测， 没有开启直接返回已经命中的海拔或者空
        if (empty($info) && empty($activityInfo['has_ab_test'])) {
            return self::formatTestAbPoster($info);
        }
        if (empty($info)) {
            // 计算命中海报 - 计算命中海报一直有锁最终会返回空
            list($hitPosterId, $hitNode, $abVersion) = self::calculateHitNode($activityId);
            SimpleLogger::info("qingfeng-test-real-getStudentTestAbPoster", ['msg' => 'msg-hit_poster', 'student_id' => $studentId, 'activity_id' => $activityId, 'has_ab_test' => $activityInfo['has_ab_test'], 'hit_poster_id' => $hitPosterId]);
            if ($hitPosterId <= 0) {
                return self::formatTestAbPoster([]);
            }
            $info['ab_poster_id'] = $hitPosterId;
            // 首次命中海报 - 保存学生命中海报信息
            self::saveStudentTestAbPosterQrId($studentId, $activityId, $info['ab_poster_id'], $abVersion ?? 0);
        }
        // 是否生成小程序码
        if (!empty($extData) && !empty($extData['is_create_qr_id'])) {
            $hitPosterInfo = TemplatePosterModel::getPosterInfo($info['ab_poster_id']);
            if (!empty($hitPosterInfo)) {
                // 海报图：
                $posterConfig = PosterService::getPosterConfig();
                $studentType = $extData['user_type'] ?? DssUserQrTicketModel::STUDENT_TYPE;
                $channelId = $extData['channel_id'] ?? 0;
                $landingType = $extData['landing_type'] ?? 0;
                $qrInfo = MiniAppQrService::getUserMiniAppQr(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $studentId, $studentType, $channelId, $landingType,
                    [
                        'activity_id' => $activityId,
                        'poster_id'   => $hitPosterInfo['poster_id'],
                        'user_status' => $extData['user_status'],
                    ],
                    false
                );
                $poster = PosterService::generateQRPoster(
                    $hitPosterInfo['poster_path'],
                    $posterConfig,
                    $studentId,
                    DssUserQrTicketModel::STUDENT_TYPE,
                    $extData['channel_id'],
                    ['user_current_status' => $extData['user_status'], 'poster_id' => $hitPosterInfo['poster_id']],
                    [
                        'qr_id'   => $qrInfo['qr_id'],
                        'qr_path' => $qrInfo['qr_path'],
                    ]
                );
                $info['poster_url'] = $poster['poster_save_full_path'];
            }
        }
        // 返回命中的海报
        return self::formatTestAbPoster($info);
    }

    /**
     * 保存用户命中的测试海报数据QrId
     * @param $studentId
     * @param $activityId
     * @param $abPosterId
     * @param $abVersion
     * @return int|mixed|string|null
     */
    public static function saveStudentTestAbPosterQrId($studentId, $activityId, $abPosterId, $abVersion)
    {
        return RealWeekActivityUserAllocationABModel::insertRecord([
            'activity_id'  => $activityId,
            'student_id'   => $studentId,
            'ab_poster_id' => $abPosterId,
            'ab_version'   => $abVersion,
            'create_time'  => time(),
        ]);
    }

    /**
     * 获取redis key
     * @param $activityId
     * @return string[]
     */
    public static function getRedisKey($activityId)
    {
        return ['real_week_activity_ab_node_num-' . $activityId, 'real_week_activity_ab_node_lock-' . $activityId, 'real_week_activity_ab_version-' . $activityId];
    }

    /**
     * 计算命中节点
     * @param $activityId
     * @return array
     * @throws RunTimeException
     */
    public static function calculateHitNode($activityId)
    {
        SimpleLogger::info('calculateHitNode_start', [$activityId]);
        $redis = RedisDB::getConn();
        list($redisKey, $redisKeyLockKey, $redisKeyAbVersion) = self::getRedisKey($activityId);
        $hitNode = $assignedCount = $hitPosterId = $saturation = $version = 0;
        $sortAllocation = [];
        try {
            $lock = Util::setLock($redisKeyLockKey, 3, 5);
            if (!$lock) {
                SimpleLogger::info("calculateHitNode is lock", [$activityId]);
                return [0, []];
            }
            $nodeList = json_decode($redis->get($redisKey), true);
            if (empty($nodeList)) {
                SimpleLogger::info('calculateHitNode_node_empty', [$activityId, $nodeList]);
                $nodeList = self::initNodeAllocation($activityId, [], false);
            }
            // 获取版本
            $version = $redis->get($redisKeyAbVersion);
            // 初始化部分默认值
            foreach ($nodeList as $_posterId => $_item) {
                $assignedCount += $_item['node_assigned_num'];
                $sortAllocation[] = $_item['allocation'];
                if ($_item['allocation'] > $hitNode) {
                    $hitNode = $_item['allocation'];
                    $hitPosterId = $_posterId;
                }
            }
            unset($_posterId, $_item);
            if ($assignedCount > 0) {
                // 排序
                $sortNodeList = $nodeList;
                array_multisort($sortAllocation, SORT_DESC, $sortNodeList);
                // 计算命中
                foreach ($sortNodeList as $_node) {
                    $_ratio = ($_node['node_assigned_num'] / $assignedCount) * 100;
                    if ($_ratio >= $_node['allocation']) {
                        continue;
                    }
                    $_saturation = $_node['allocation'] - $_ratio;
                    if ($_saturation > $saturation) {
                        // 饱和度差距最大的值
                        $saturation = $_saturation;
                        $hitNode = $_node['allocation'];
                        $hitPosterId = $_node['poster_id'];
                    }
                }
            }
            $nodeList[$hitPosterId]['node_assigned_num'] += 1;
            $redis->set($redisKey, json_encode($nodeList));
        } finally {
            Util::unLock($redisKeyLockKey);
        }
        SimpleLogger::info('calculateHitNode_end', [$activityId, $hitPosterId, $hitNode, $nodeList]);
        return [$hitPosterId, $hitNode, $version];
    }

    /**
     * 初始化每个分配比例节点
     * @param $activityId
     * @param $nodeAllocation
     * @param bool $isLock 是否加上锁， 如果已经加锁了这里就不能再加锁也加不上锁
     * @return array
     * @throws RunTimeException
     */
    public static function initNodeAllocation($activityId, $nodeAllocation = [], $isLock = true)
    {
        if (empty($activityId)) {
            SimpleLogger::info('initNodeAllocation_activity_id_empty', [$activityId, $nodeAllocation]);
            throw new RunTimeException(['initNodeAllocation_activity_id_empty']);
        }
        $redis = RedisDB::getConn();
        $tmpData = [];
        list($redisKey, $redisKeyLockKey, $redisKeyAbVersion) = self::getRedisKey($activityId);
        try {
            if ($isLock) {
                $lock = Util::setLock($redisKeyLockKey, 3,5);
                if (!$lock) {
                    SimpleLogger::info("calculateHitNode is lock", []);
                    return [0, []];
                }
            }
            if (empty($nodeAllocation)) {
                $nodeAllocation = RealWeekActivityPosterAbModel::getRecords(['activity_id' => $activityId]);
            }
            if (empty($nodeAllocation)) {
                SimpleLogger::info('initNodeAllocation_node_allocation_empty', [$activityId, $nodeAllocation]);
                throw new RunTimeException(['initNodeAllocation_node_allocation_empty']);
            }
            foreach ($nodeAllocation as $item) {
                $tmpData[$item['poster_id']] = ['allocation' => $item['allocation'], 'node_assigned_num' => 0, 'poster_id' => $item['poster_id']];
            }
            $redis->set($redisKey, json_encode($tmpData));
            $redis->set($redisKeyAbVersion, RealWeekActivityClientService::getCurrentVersion($activityId));
        } finally {
            $isLock == true && Util::unLock($redisKeyLockKey);
        }
        return $tmpData;
    }

    /**
     * 格式化测试海报数据让其保持和普通海报一样的数据格式和字段
     * @param $testAbPosterInfo
     * @return array
     */
    public static function formatTestAbPoster($testAbPosterInfo)
    {
        if (empty($testAbPosterInfo)) {
            return [false, []];
        }
        $posterInfo = TemplatePosterModel::getPosterInfo($testAbPosterInfo['ab_poster_id']);
        if (empty($posterInfo)) {
            // 没找到海报或海报已下线
            return [false, []];
        }
        $posterInfo['practise_zh'] = TemplatePosterModel::$practiseArray[$posterInfo['practise']] ?? '否';
        $posterInfo['poster_ascription'] = ActivityPosterModel::POSTER_ASCRIPTION_STUDENT;
        $posterInfo['poster_url'] = $testAbPosterInfo['poster_url'] ?? '';
        return [true, $posterInfo];
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
            // 检查是否有重复的海报
            $abPosterIds = array_column($abTestInfo['ab_poster_list'], 'id');
            $abPosterIds[] = $abTestInfo['control_group']['id'];
            $uniqueAbPosterIds = array_unique($abPosterIds);
            if (count($uniqueAbPosterIds) != count($abPosterIds)) {
                return 'week_activity_ab_poster_repeat';
            }
        }
        return '';
    }

    /**
     * 获取学生命中海报
     * @param $activityId
     * @param $studentId
     * @return mixed
     */
    public static function getStudentHitPoster($activityId, $studentId)
    {
        $redis = RedisDB::getConn();
        $redisData = self::getRedisKey($activityId);
        return RealWeekActivityUserAllocationABModel::getRecord([
            'activity_id' => $activityId,
            'student_id' => $studentId,
            'ab_version' => $redis->get($redisData[2]),
        ]);
    }
}