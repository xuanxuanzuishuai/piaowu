<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 6:51 PM
 */

namespace App\Services;


use App\Libs\PandaCRM;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealLandingPromotionRecordModel;

class RealLandingPromotionService
{
    /**
     * 推广落地页领课数据记录
     * @param $studentUuid
     * @param $landingType
     * @param $channelId
     * @param bool $isSendIntentActiveMessage
     * @return array
     */
    public static function takeLessonRecord($studentUuid, $landingType, $channelId, $isSendIntentActiveMessage = false)
    {
        $result = [
            'is_new' => true,
            'take_res' => false,
            'is_first_take' => true,//是否首次领取:true是 false不是
        ];
        if (empty($studentUuid)) {
            return $result;
        }
        //查询账号是否存在
        $studentInfo = ErpStudentModel::getStudentInfoByUuid($studentUuid);
        if (empty($studentInfo)) {
            return $result;
        } else {
            $result['is_new'] = false;
        }
        if (empty($channelId)) {
            $channelId = $studentInfo['channel_id'];
        }
        //检测是否已经参与
        $joinRecord = RealLandingPromotionRecordModel::getRecord(['uuid' => $studentInfo['uuid'], 'type' => $landingType,], ['id']);
        if (!empty($joinRecord)) {
            $result['take_res'] = true;
            $result['is_first_take'] = false;
            return $result;
        }
        $insertData = [
            'mobile' => $studentInfo['mobile'],
            'uuid' => $studentInfo['uuid'],
            'student_id' => $studentInfo['id'],
            'type' => $landingType,
            'create_time' => time(),
        ];
        $takeRes = RealLandingPromotionRecordModel::insertRecord($insertData);
        if (!empty($takeRes)) {
            $result['take_res'] = true;
        }
        if ($isSendIntentActiveMessage) {
            self::sendIntentActiveMessage($studentInfo['uuid'], $landingType, $channelId);
        }
        return $result;
    }

    /**
     * @param $studentUuid
     * @param $landingType
     * @param $channelId
     * @return array|mixed
     */
    private static function sendIntentActiveMessage($studentUuid, $landingType, $channelId)
    {
        return (new PandaCRM())->mainIntentActive(
            [
                'uuid' => $studentUuid,
                'active_type' => RealLandingPromotionRecordModel::LANDING_TYPE_MAP_LOGIN_TYPE[$landingType],
                'channel_id' => $channelId
            ]);

    }
}