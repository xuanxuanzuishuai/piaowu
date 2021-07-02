<?php
namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\WeChatAwardCashDealModel;
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
     * @param $parentBillId
     * @return bool
     */
    public static function dssShouldCompleteEventTask($student, $package, $parentBillId)
    {
        // 真人业务或者非智能商城包不发奖
        if ($package['app_id'] != DssPackageExtModel::APP_AI || $package['sale_shop'] != DssErpPackageV1Model::SALE_SHOP_AI_PLAY) {
            return false;
        }
        //绑定关系处理逻辑
        $inviteRes = StudentInviteService::studentInviteRecord($student['id'], $package['package_type'], $package['app_id'],'', [], $parentBillId);
        if (empty($inviteRes)) {
            return false;
        }

        // 不发奖励 - 推荐人状态为非"付费正式课"不发奖励
        $referralInfo = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $student['id']]);
        if (empty($referralInfo)) {
            SimpleLogger::info("RefereeAwardService::dssShouldCompleteEventTask", ['err' => 'no_fond_referee', 'student' => $student, 'package' => $package]);
            return false;
        }
        //$referralUser = DssStudentModel::getRecord(['id' => $referralInfo['referee_id']]);
        //if ($referralUser['has_review_course'] != DssStudentModel::REVIEW_COURSE_1980) {
        //    SimpleLogger::info("RefereeAwardService::dssShouldCompleteEventTask", ['err' => 'no_REVIEW_COURSE_1980', 'student' => $student, 'package' => $package]);
        //    return false;
        //}

        // 升级
        if ($package['package_type'] > $student['has_review_course']) {
            return true;
        } else {
            // 年包 && 首购智能陪练正式课
            if ($package['package_type'] == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
                $res = DssGiftCodeModel::hadPurchasePackageByType($student['id'], DssPackageExtModel::PACKAGE_TYPE_NORMAL, false);
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
        list($page, $count) = Util::formatPageCount($params);

        if (!empty($params['event_task_id'])) {
            $params['event_task_id'] = self::expectTaskRelateRealTask($params['event_task_id']);
        }
        list($records, $total) = ErpUserEventTaskAwardModel::getAward($page, $count, $params);
        return [self::formatAward($records), $total];
    }

    /**
     * 获取操作人名字
     * @param $ids
     * @return array
     */
    public static function getReviewerNames($ids)
    {
        return EmployeeModel::getRecords(['id' => $ids], ['id', 'name']);
    }

    /**
     * 格式化红包数据
     * @param $records
     * @return mixed
     */
    public static function formatAward($records)
    {
        // 公众号关注状态：
        $wechatSubscribeInfo = [];
        $uuidArr = array_column($records, 'student_uuid');
        if (!empty($uuidArr)) {
            $wechatSubscribeInfo = array_column(DssWechatOpenIdListModel::getUuidOpenIdInfo($uuidArr), null, 'uuid');
        }

        foreach ($records as &$award) {
            $subscribeStatus = $wechatSubscribeInfo[$award['student_uuid']]['subscribe_status'];
            $bindStatus = $wechatSubscribeInfo[$award['student_uuid']]['bind_status'];

            $award['subscribe_status']    = $subscribeStatus;
            $award['subscribe_status_zh'] = $subscribeStatus == DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注';
            $award['bind_status']         = $bindStatus;
            $award['bind_status_zh']      = $bindStatus == DssUserWeiXinModel::STATUS_NORMAL ? '已绑定' : '未绑定';
            $award['award_status_zh']     = ErpUserEventTaskAwardModel::STATUS_DICT[$award['award_status']] ?? '';
            $award['fail_reason_zh']      = $award['award_status'] == ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL ? WeChatAwardCashDealModel::getWeChatErrorMsg($award['result_code']) : '';
            $award['award_amount']        = ($award['award_amount'] / 100);
        }
        return $records;
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

    /**
     * 时长奖励发放
     * @param $awardId
     * @return bool
     */
    public static function sendDuration($awardId)
    {
        if (empty($awardId)) {
            SimpleLogger::error("EMPTY AWARD ID", [$awardId]);
            return false;
        }
        $sendStatus = ErpUserEventTaskAwardModel::STATUS_GIVE;
        $reviewerId = EmployeeModel::SYSTEM_EMPLOYEE_ID;
        try {
            $awardDetailInfo = ErpUserEventTaskAwardModel::awardRelateEvent($awardId);
            if ($awardDetailInfo['award_type'] != ErpUserEventTaskAwardModel::AWARD_TYPE_DURATION) {
                return false;
            }
            if (!CashGrantService::awardAndRefundVerify($awardDetailInfo)) {
                $sendStatus = ErpUserEventTaskAwardModel::STATUS_DISABLED;
                SimpleLogger::error('REFUND VERIFY FAIL', [$awardDetailInfo]);
            }
            (new Erp())->updateAward($awardId, $sendStatus, $reviewerId);
            if ($sendStatus == ErpUserEventTaskAwardModel::STATUS_GIVE) {
                PushMessageService::sendAwardRelateMessage($awardDetailInfo);
            }
        } catch (RunTimeException $e) {
            SimpleLogger::error('ERP UPDATE AWARD ERROR', [$e->getMessage()]);
            return false;
        }
        return true;
    }
}