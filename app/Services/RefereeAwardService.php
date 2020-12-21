<?php
namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Services\Queue\QueueService;

class RefereeAwardService
{
    /**
     * 前端传相应的期望节点
     */
    const EXPECT_REGISTER          = 1; //注册
    const EXPECT_TRAIL_PAY         = 2; //付费体验卡
    const EXPECT_YEAR_PAY          = 3; //付费年卡
    const EXPECT_FIRST_NORMAL      = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    const EVENT_TYPE_UPLOAD_POSTER = 4; // 上传分享海报

    /**
     * 节点对应的所有task
     * @param $node
     * @return false|string[]
     */
    public static function getNodeRelateTask($node)
    {
        return explode(',', DictConstants::get(DictConstants::NODE_RELATE_TASK, $node));
    }

    /**
     * @return int
     * 当前生效的转介绍注册任务
     */
    public static function getDssRegisterTaskId()
    {
        $arr = self::getNodeRelateTask(self::EXPECT_REGISTER);
        return reset($arr);
    }

    /**
     * @param int $index
     * @return int
     * 当前生效的体验付费任务
     */
    public static function getDssTrailPayTaskId($index = 0)
    {
        $arr = self::getNodeRelateTask(self::EXPECT_TRAIL_PAY);
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return reset($arr);
    }

    /**
     * @param int $index
     * @return int
     * 当前生效的年卡付费任务
     */
    public static function getDssYearPayTaskId($index = 0)
    {
        $arr = self::getNodeRelateTask(self::EXPECT_YEAR_PAY);
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return reset($arr);
    }

    /**
     * 判断是否应该完成任务及发放奖励
     * @param $student
     * @param $package
     * @return bool
     */
    public static function dssShouldCompleteEventTask($student, $package)
    {
        // 真人业务不发奖
        if ($package['app_id'] != DssPackageExtModel::APP_AI) {
            return false;
        }

        // 升级
        if ($package['package_type'] > $student['has_review_course']) {
            return true;
        } else {
            // 年包 && 首购智能陪练正式课
            if ($package['package_type'] == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
                $res = DssGiftCodeModel::hadPurchasePackageByType($student['id'], DssPackageExtModel::PACKAGE_TYPE_NORMAL);
                $hadPurchaseCount = count($res);
                if ($hadPurchaseCount <= 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 红包审核
     * @param $params
     * @return array
     */
    public static function getAwardList($params)
    {
        list($page, $count) = Util::appPageLimit($params);
        if (!empty($params['student_name'])) {
            $params['student_uuid'] = array_column(DssStudentModel::getRecords(['name[~]' => $params['student_name']], ['uuid']), 'uuid');
            if (empty($params['student_uuid'])) {
                return ['list' => [], 'total_count' => 0];
            }
            unset($params['student_name']);
        }
        //筛选项增加uuid
        if (!empty($params['uuid'])) {
            if (isset($params['student_uuid']) && !in_array($params['uuid'], $params['student_uuid'])) {
                return ['list' => [], 'total_count' => 0];
            }
            $params['student_uuid'] = [$params['uuid']];
            unset($params['uuid']);
        }

        if (!empty($params['event_task_id'])) {
            $params['event_task_id'] = self::expectTaskRelateRealTask($params['event_task_id']);
        }
        return ErpUserEventTaskAwardModel::getAward($page, $count, $params);
    }

    /**
     * 前端传期望看到的task和实际对应的task关系
     * @param $expectTaskId
     * @return false|int[]|string[]
     */
    public static function expectTaskRelateRealTask($expectTaskId)
    {
        if ($expectTaskId == self::EXPECT_UPLOAD_SCREENSHOT) {
            $eventTask = EventService::getEventTasksList(0, self::EVENT_TYPE_UPLOAD_POSTER);
            return array_column($eventTask, 'id');
        }
        $allNodeRelate = DictConstants::getSet(DictConstants::NODE_RELATE_TASK);
        return !empty($allNodeRelate[$expectTaskId]) ? explode(',', $allNodeRelate[$expectTaskId]) : explode(',', $expectTaskId);
    }

    /**
     * 更新奖励状态
     * @param $awardId
     * @param $status
     * @param $reviewerId
     * @param $reason
     * @param string $keyCode
     * @return bool
     * @throws RunTimeException
     */
    public static function updateAward(
        $awardId,
        $status,
        $reviewerId,
        $reason
    ) {
        if (empty($awardId) || empty($reviewerId)) {
            throw new RunTimeException(['invalid_award_id_or_reviewer_id']);
        }
        $erp = new Erp();
        $time = time();
        //期望结果数据 批量处理支持
        $awardIdArr = array_unique(explode(',', $awardId));
        if (count($awardIdArr) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        $needDealAward = [];
        if (!empty($awardIdArr)) {
            foreach ($awardIdArr as $value) {
                $needDealAward[$value] = [
                    'id'          => $value,
                    'award_id'    => $value,
                    'status'      => $status,
                    'reviewer_id' => $reviewerId,
                    'reason'      => $reason,
                    'review_time' => $time
                ];
            }
        }
        //实际发放结果数据 调用微信红包，
        if ($status == ErpUserEventTaskAwardModel::STATUS_GIVE) {
            QueueService::sendRedPack($needDealAward);
        } else {
            $response = $erp->batchUpdateAward($needDealAward);
            if (empty($response) || $response['code'] != 0) {
                $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
                throw new RunTimeException([$errorCode]);
            }
        }
        return true;
    }
}