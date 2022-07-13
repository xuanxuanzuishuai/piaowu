<?php

namespace App\Services\Queue\Activity\LimitTimeAward;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\LimitTimeActivity\LimitTimeActivitySharePosterModel;
use App\Models\SharePosterModel;
use App\Services\AutoCheckPicture;

class LimitTimeAwardConsumerService
{
    private $autoCheckStatusCacheKey = 'lta_stop_auto_check';
    private $sharePosterCheckLockCacheKeyPrefix = 'lta_auto_check_lock';

    /**
     * 分享海报自动审核
     * @param $paramsData
     * @return bool
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
            ]);
        if (empty($sharePosterData)) {
            Util::sendFsWaringText('限时有奖活动，自动审核消费者接收了无效的海报上传记录ID', $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            return false;
        }
        $imagePath = AliOSS::replaceCdnDomainForDss($sharePosterData['image_path']);
        list($status, $errCode) = AutoCheckPicture::checkByOcr($imagePath, $paramsData['msg_body']);
        if ($status > 0) {
            //自动识别通过
            //todo
        } elseif (!empty($errCode)) {
            $reasonArr = AutoCheckPicture::formatAutoCheckErrorCodeMapToSystemErrorCode($errCode);
            //todo
        }
        return true;
    }
}