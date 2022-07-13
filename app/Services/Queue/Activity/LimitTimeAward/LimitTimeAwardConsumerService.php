<?php

namespace App\Services\Queue\Activity\LimitTimeAward;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\LimitTimeActivity\LimitTimeActivitySharePosterModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityAdminService;
use App\Services\Activity\Lottery\LotteryServices\LotteryGrantAwardService;
use App\Services\AutoCheckPicture;

class LimitTimeAwardConsumerService
{
    private $autoCheckStatusCacheKey = 'lta_stop_auto_check';
    private $sharePosterCheckLockCacheKeyPrefix = 'lta_auto_check_lock';

    /**
     * 分享海报自动审核
     * @param $paramsData
     * @return bool
     * @throws RunTimeException
     */
    public function sharePosterAutoCheck($paramsData): bool
    {
        //检测自动审核功能是否开启
        $redis           = RedisDB::getConn();
        $autoCheckStatus = $redis->get($this->autoCheckStatusCacheKey);
        if ($autoCheckStatus === 'no') {
            return false;
        }
        //加锁防止并发操作
        $lockRes = Util::setLock($this->sharePosterCheckLockCacheKeyPrefix . $paramsData['msg_body']['share_poster_id'],
            10, 0);
        if (empty($lockRes)) {
            LimitTimeAwardProducerService::autoCheckProducer($paramsData['msg_body']['share_poster_id'],
                $paramsData['msg_body']['user_id'], 12);
            SimpleLogger::error('limit time award share poster more times check', [$paramsData['msg_body']]);
            return false;
        }
        if (empty($paramsData['msg_body']['share_poster_id'])) {
            Util::sendFsWaringText('限时有奖活动，自动审核消费者必填参数海报ID缺失', $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            return false;
        }
        //获取上传的海报数据
        $sharePosterData = LimitTimeActivitySharePosterModel::getRecord(
            [
                'id'            => $paramsData['msg_body']['share_poster_id'],
                'verify_status' => SharePosterModel::VERIFY_STATUS_WAIT
            ],
            [
                'image_path',
                'activity_id',
                'app_id',
            ]);
        if (empty($sharePosterData)) {
            Util::sendFsWaringText('限时有奖活动，自动审核消费者接收了无效的海报上传记录ID', $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            return false;
        }
        $imagePath = AliOSS::replaceCdnDomainForDss($sharePosterData['image_path']);
        list($status, $errCode) = AutoCheckPicture::checkByOcr($imagePath, $paramsData['msg_body']);
        if ($status > 0) {
            //自动识别通过
            LimitTimeActivityAdminService::approvalPoster(
                [$paramsData['msg_body']['share_poster_id']],
                [
                    'app_id'      => $sharePosterData['app_id'],
                    'activity_id' => $sharePosterData['activity_id'],
                    'employee_id' => EmployeeModel::SYSTEM_EMPLOYEE_ID,
                    'remark'      => ''
                ]);
        } elseif (!empty($errCode)) {
            //识别失败
            $reasonArr = AutoCheckPicture::formatAutoCheckErrorCodeMapToSystemErrorCode($errCode);
            LimitTimeActivityAdminService::refusedPoster(
                $paramsData['msg_body']['share_poster_id'],
                [
                    'app_id'      => $sharePosterData['app_id'],
                    'activity_id' => $sharePosterData['activity_id'],
                    'employee_id' => EmployeeModel::SYSTEM_EMPLOYEE_ID,
                    'reason'      => $reasonArr,
                    'remark'      => ''
                ],
                SharePosterModel::VERIFY_STATUS_WAIT
            );
        }
        return true;
    }

    /**
     * 发奖
     * @param $paramsData
     * @return bool
     */
    public function sendAward($paramsData): bool
    {
        $logTitle = 'limit time award send award';
        $time     = time();
        SimpleLogger::info("$logTitle params:", $paramsData);
        $recordId = $paramsData['record_id'] ?? 0;
        if (empty($recordId)) {
            return false;
        }
        $sharePosterRecordInfo = LimitTimeActivitySharePosterModel::getRecord(['id' => $recordId]);
        SimpleLogger::info("$logTitle share poster record info:", [$sharePosterRecordInfo]);
        if (empty($sharePosterRecordInfo)) {
            return false;
        }
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
        switch ($sharePosterRecordInfo['award_type']) {
            case Constants::AWARD_TYPE_TIME:
                $sendData = [
                    'common_award_amount' => $sharePosterRecordInfo['award_amount'],
                ];
                break;
            case Constants::AWARD_TYPE_GOLD_LEAF:
                $sendData = [
                    'student_uuid'  => $sharePosterRecordInfo['student_uuid'],
                    'num'           => $sharePosterRecordInfo['award_amount'],
                    'remark'        => '限时活动',
                    'batch_id'      => substr(md5(uniqid()), 0, 6),
                    'operator_type' => 0,
                    'operator_id'   => EmployeeModel::SYSTEM_EMPLOYEE_ID,
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
        $sendRes = LotteryGrantAwardService::sendAward($sendData);
        SimpleLogger::info("$logTitle send award request:", [$sendData, $sendRes]);
        if (!$sendRes) {
            return false;
        }
        LimitTimeActivitySharePosterModel::updateSendAwardStatusIsSuccess($sharePosterRecordInfo['id']);
        SimpleLogger::info("$logTitle send award success:", [$sharePosterRecordInfo]);
        return true;
    }


}