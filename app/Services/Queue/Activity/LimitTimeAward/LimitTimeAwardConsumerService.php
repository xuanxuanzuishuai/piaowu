<?php

namespace App\Services\Queue\Activity\LimitTimeAward;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\LimitTimeActivity\LimitTimeActivityAwardRuleModel;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\LimitTimeActivity\LimitTimeActivitySharePosterModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityAdminService;
use App\Services\Activity\LimitTimeActivity\TraitService\LimitTimeActivityBaseAbstract;
use App\Services\Activity\Lottery\LotteryServices\LotteryGrantAwardService;
use App\Services\AutoCheckPicture;
use App\Services\CommonServiceForApp;
use App\Services\DictService;
use App\Services\Queue\ErpStudentAccountTopic;
use App\Services\Queue\QueueService;

class LimitTimeAwardConsumerService
{
    private $autoCheckStatusCacheKey            = 'lta_stop_auto_check';
    private $sharePosterCheckLockCacheKeyPrefix = 'lta_auto_check_lock_';

    // 活动推送，第一天推送和最后一天推送
    const IS_FIRST_DAY_PUSH = 'first_day';
    const IS_LAST_DAY_PUSH  = 'last_day';

    /**
     * 分享海报自动审核
     * @param $paramsData
     * @return bool
     * @throws RunTimeException
     */
    public function sharePosterAutoCheck($paramsData): bool
    {
        //检测自动审核功能是否开启
        $redis = RedisDB::getConn();
        $autoCheckStatus = $redis->get($this->autoCheckStatusCacheKey);
        if ($autoCheckStatus === 'no') {
            return false;
        }
        if (empty($paramsData['msg_body']['record_id'])) {
            Util::sendFsWaringText('限时有奖活动，自动审核消费者必填参数海报ID缺失', $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            return false;
        }

        //加锁防止并发操作
        $lockRes = Util::setLock($this->sharePosterCheckLockCacheKeyPrefix . $paramsData['msg_body']['record_id'],
            10, 0);
        if (empty($lockRes)) {
            LimitTimeAwardProducerService::autoCheckProducer($paramsData['msg_body']['record_id'],
                $paramsData['msg_body']['user_id'], 12);
            SimpleLogger::error('limit time award share poster check lock add fail', [$paramsData['msg_body']]);
            return false;
        }

        //获取上传的海报数据
        $sharePosterData = LimitTimeActivitySharePosterModel::getRecord(
            [
                'id'            => $paramsData['msg_body']['record_id'],
                'verify_status' => SharePosterModel::VERIFY_STATUS_WAIT
            ],
            [
                'image_path',
                'activity_id',
                'app_id',
            ]);
        if (empty($sharePosterData)) {
            Util::sendFsWaringText('限时有奖活动，自动审核消费者接收了无效的海报上传记录ID=' . $paramsData['msg_body']['record_id'],
                $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            return false;
        }
        $imagePath = AliOSS::replaceCdnDomainForDss($sharePosterData['image_path']);
        list($status, $errCode) = AutoCheckPicture::checkByOcr($imagePath, $paramsData['msg_body']);
        SimpleLogger::info('limit time award auth check res ', ['status' => $status, 'error_code' => $errCode]);
        //审核参数
        $checkParams = [
            'app_id'      => $sharePosterData['app_id'],
            'activity_id' => $sharePosterData['activity_id'],
            'employee_id' => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'remark'      => ''
        ];
        if ($status > 0) {
            //自动识别通过
            LimitTimeActivityAdminService::approvalPoster([$paramsData['msg_body']['record_id']], $checkParams);
        } elseif (!empty($errCode)) {
            //识别失败
            $reasonArr = AutoCheckPicture::formatAutoCheckErrorCodeMapToSystemErrorCode($errCode);
            $checkParams['reason'] = $reasonArr;
            LimitTimeActivityAdminService::refusedPoster($paramsData['msg_body']['record_id'], $checkParams,
                SharePosterModel::VERIFY_STATUS_WAIT);
        }
        return true;
    }

    /**
     * 发奖
     * @param $paramsData
     * @return bool
     * @throws RunTimeException
     */
    public function sendAward($paramsData): bool
    {
        $logTitle = 'limit time award send award';
        $time = time();
        SimpleLogger::info("$logTitle params:", $paramsData);
        $recordId = $paramsData['msg_body']['record_id'] ?? 0;
        if (empty($recordId)) {
            return false;
        }
        $sharePosterRecordInfo = LimitTimeActivitySharePosterModel::getRecord(['id' => $recordId]);
        SimpleLogger::info("$logTitle share poster record info:", [$sharePosterRecordInfo]);
        if (empty($sharePosterRecordInfo)) {
            return false;
        }
        $studentUUId = $sharePosterRecordInfo['student_uuid'];
        $appId = $sharePosterRecordInfo['app_id'];
        if ($sharePosterRecordInfo['verify_status'] != SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            return false;
        }
        if ($sharePosterRecordInfo['send_award_time'] >= $time) {
            return false;
        }
        if ($sharePosterRecordInfo['send_award_status'] != OperationActivityModel::SEND_AWARD_STATUS_WAITING) {
            return false;
        }
        SimpleLogger::info("$logTitle send award start:", [$sharePosterRecordInfo]);
        LimitTimeActivitySharePosterModel::updateSendAwardStatusIsSuccess($sharePosterRecordInfo['id']);
        if ($sharePosterRecordInfo['award_amount'] <= 0) {
            SimpleLogger::info("$logTitle award is zero:", [$sharePosterRecordInfo]);
            return true;
        }
        switch ($sharePosterRecordInfo['award_type']) {
            case Constants::AWARD_TYPE_TIME:
                $sendData = [
                    'type'                => Constants::AWARD_TYPE_TIME,
                    'common_award_amount' => $sharePosterRecordInfo['award_amount'],
                ];
                break;
            case Constants::AWARD_TYPE_GOLD_LEAF:
                $sendData = [
                    'type'                => Constants::AWARD_TYPE_GOLD_LEAF,
                    'student_uuid'        => $sharePosterRecordInfo['student_uuid'],
                    'common_award_amount' => $sharePosterRecordInfo['award_amount'],
                    'remark'              => '限时活动',
                    'batch_id'            => substr(md5(uniqid()), 0, 6),
                    'operator_type'       => 0,
                    'operator_id'         => EmployeeModel::SYSTEM_EMPLOYEE_ID,
                    'source_type'         => ErpStudentAccountModel::SOURCE_TYPE_LIMIT_TIME_ACTIVITY_AWARD,
                ];
                break;
            case Constants::AWARD_TYPE_MAGIC_STONE:
                $sendData = [
                    'type'                => Constants::AWARD_TYPE_MAGIC_STONE,
                    'student_uuid'        => $sharePosterRecordInfo['student_uuid'],
                    'sub_type'            => Constants::ERP_ACCOUNT_NAME_MAGIC,
                    'source_type'         => ErpStudentAccountTopic::UPLOAD_POSTER_ACTION,
                    'remark'              => '转介绍限时活动',
                    'common_award_amount' => $sharePosterRecordInfo['award_amount'],
                    'batch_id'            => Util::getBatchId(),
                ];
                break;
            default:
                $sendData = [];
                break;
        }
        if (empty($sendData)) {
            SimpleLogger::info("$logTitle send award send data is empty:", []);
            return false;
        }
        $sendData['uuid'] = $sharePosterRecordInfo['student_uuid'];
        $sendRes = LotteryGrantAwardService::sendAward($sendData);
        SimpleLogger::info("$logTitle send award request:", [$sendData, $sendRes]);
        if (!$sendRes) {
            return false;
        }

        $activityInfo = LimitTimeActivityModel::getRecord(['activity_id' => $sharePosterRecordInfo['activity_id']]);
        // 推送到账消息
        $msgId = LimitTimeActivityBaseAbstract::getWxMsgId(
            (int)$sharePosterRecordInfo['app_id'],
            (int)$activityInfo['activity_type'],
            OperationActivityModel::SEND_AWARD_STATUS_GIVE,
            SharePosterModel::VERIFY_STATUS_QUALIFIED
        );
        $studentInfo = LimitTimeActivityBaseAbstract::getAppObj($appId)->getStudentInfoByUUID([$studentUUId])[$studentUUId];
        QueueService::sendUserWxMsg($appId, $studentInfo['id'], $msgId, [
            'replace_params' => [
                'award_amount' => $sharePosterRecordInfo['award_amount'],
                'award_unit'   => LimitTimeActivityBaseAbstract::getAwardUnit($sharePosterRecordInfo['award_type']),
            ],
        ]);
        SimpleLogger::info("$logTitle send award success:", [$sharePosterRecordInfo]);
        return true;
    }

    /**
     * 推送最后一天或者第一天消息
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public function pushActivityMsg($params): bool
    {
        $pushType = $params['msg_body']['push_type'] ?? '';
        $studentInfo = $params['msg_body']['student_info'] ?? [];
        $activityList = $params['msg_body']['activity_list'] ?? [];
        $appId = $params['msg_body']['app_id'] ?? 0;
        // 记录接收日志
        $logTitle = 'limit time award push msg';
        $time = time();
        SimpleLogger::info("$logTitle params:", $params);
        if (empty($pushType) || empty($studentInfo) || empty($activityList) || empty($appId)) {
            return true;
        }
        // 检查学生是否符合活动条件，符合发送消息，不符合不发送消息，记录日志
        $serviceObj = LimitTimeActivityBaseAbstract::getAppObj($appId, [
            'student_info' => [
                'user_id' => $studentInfo['student_id'],
                'uuid'    => $studentInfo['student_uuid'] ?? '',
            ],
            'from_type'    => '',
        ]);
        try {
            $studentAttr = $serviceObj->studentPayStatusCheck();
        } catch (RunTimeException $e) {
            SimpleLogger::info("$logTitle student attr error:", [$e->getMessage(), $e->getData()]);
            return true;
        }
        $inviteNum = $serviceObj->getStudentReferralOrBuyTrailCount();
        foreach ($activityList as $item) {
            // 检查区号
            if (!LimitTimeActivityBaseAbstract::checkStudentCountryCodeRight($studentInfo['country_code'], $item['activity_country_code'])) {
                SimpleLogger::info("$logTitle student country code error:", [$studentInfo, $item]);
                continue;
            }
            $awardRules = LimitTimeActivityAwardRuleModel::getRecords(['activity_id' => $item['activity_id']]);
            $awardType = $awardRules[0]['award_type'] ?? 0;
            if ($item['target_user_type'] == OperationActivityModel::TARGET_USER_PART) {
                // 部分付费有效判断条件
                $targetUser = json_decode($item['target_user'], true);
                if (!empty($targetUser['target_user_first_pay_time_start']) && !empty($targetUser['target_user_first_pay_time_end'])) {
                    if ($studentAttr['first_pay_time'] < $targetUser['target_user_first_pay_time_start'] || $studentAttr['first_pay_time'] > $targetUser['target_user_first_pay_time_end']) {
                        SimpleLogger::info("$logTitle student first pay time error:", [$studentAttr, $targetUser]);
                        continue;
                    }
                }
                if (!empty($targetUser['invitation_num']) && $inviteNum < $targetUser['invitation_num']) {
                    SimpleLogger::info("$logTitle student invitation user num error:", [$inviteNum, $targetUser]);
                    continue;
                }
            }
            // 组装微信消息需要的参数
            $pushTypeDictKey = LimitTimeActivityBaseAbstract::getPushWxMsgAppType($appId);
            $msgId = DictService::getKeyValue($pushTypeDictKey, $pushType);
            $jumpUrl = LimitTimeActivityBaseAbstract::getAppObj($appId)->getActivityDetailHtmlUrl();
            // 发送消息
            QueueService::sendUserWxMsg($appId, $studentInfo['student_id'], $msgId, [
                'replace_params' => [
                    'log_sign'   => $appId . '-' . $studentInfo['student_id'] . '-' . $item['activity_id'],
                    'jump_url'   => LimitTimeActivityBaseAbstract::getMsgJumpUrl($jumpUrl, []),
                    'award_unit' => LimitTimeActivityBaseAbstract::getAwardUnit($awardType, true, SharePosterModel::VERIFY_STATUS_UNQUALIFIED),
                ],
            ]);
        }
        return true;
    }
}