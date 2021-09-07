<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-09-02 10:56:15
 * Time: 7:07 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\RealSharePosterAwardModel;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\QueueService;

class RealSharePosterService
{
    const KEY_POSTER_VERIFY_LOCK = 'REAL_POSTER_VERIFY_LOCK';
    
    /**
     * 审核失败原因解析
     * @param $reason
     * @param $dict
     * @return string
     */
    public static function reasonToStr($reason, $dict = [])
    {
        if (is_string($reason)) {
            $reason = explode(',', $reason);
        }
        if (empty($reason)) {
            return '';
        }
        if (empty($dict)) {
            $dict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
        }
        $str = [];
        foreach ($reason as $item) {
            $str[] = $dict[$item] ?? $item;
        }
        return implode('/', $str);
    }
    
    /**
     * 上传截图列表
     * @param $params
     * @return array
     */
    public static function sharePosterList($params)
    {
        list($posters, $totalCount) = RealSharePosterModel::getPosterList($params);
        if (!empty($posters)) {
            $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
            $reasonDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);
            foreach ($posters as &$poster) {
                $poster['mobile'] = Util::hideUserMobile($poster['mobile']);
                $poster['img_url'] = AliOSS::replaceCdnDomainForDss($poster['img_url']);
                $poster['status_name'] = $statusDict[$poster['poster_status']] ?? $posters['poster_status'];
                $poster['create_time'] = date('Y-m-d H:i', $poster['create_time']);
                $poster['check_time'] = !empty($poster['check_time']) ? date('Y-m-d H:i', $poster['check_time']) : '';
                $poster['reason_str'] = self::reasonToStr(explode(',', $poster['reason']), $reasonDict);
                if ($poster['operator_id'] == EmployeeModel::SYSTEM_EMPLOYEE_ID) {
                    $poster['operator_name'] = EmployeeModel::SYSTEM_EMPLOYEE_NAME;
                }
            }
        }
        return [$posters, $totalCount];
    }

    /**
     * 上传截图审核历史记录
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function sharePosterHistory($params, $page, $count)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        // 获取列表
        $sharePosterData = RealSharePosterModel::getSharePosterHistory($params, $page, $count);
        // 格式化信息
        if (empty($sharePosterData['list'])) {
            return $returnData;
        }
        $activityIds = [];
        $sharePosterIds = [];
        foreach ($sharePosterData['list'] as $_item) {
            $activityIds[] = $_item['activity_id'];
            $sharePosterIds[] = $_item['id'];
        }
        unset($_item);
        // 获取活动名称列表
        $activityList = RealWeekActivityModel::getRecords(['activity_id' => $activityIds]);
        $activityList = array_column($activityList, null, 'activity_id');
        // 获取奖励信息
        $awardList = RealSharePosterAwardModel::getRecords(['share_poster_id' => $sharePosterIds]);
        $awardList = array_column($awardList, null, 'share_poster_id');
        // 组合数据
        foreach ($sharePosterData['list'] as &$_item) {
            $_awardInfo = $awardList[$_item['id']] ?? [];
            $_activityInfo = $activityList[$_item['activity_id']] ?? [];

            $_item['award_type'] = $_awardInfo['award_type'] ?? 0;
            $_item['award_num'] = $_awardInfo['award_num'] ?? 0;
            $_item['activity_start_time'] = '';
            $_item['activity_end_time'] = '';
            $_item['activity_name'] = '';

            if (!empty($_activityInfo)) {
                $_item['activity_start_time'] = date("m月d日", $_activityInfo['start_time']);
                $_item['activity_end_time'] = date("m月d日", $_activityInfo['end_time']);
                $_item['activity_name'] = $_activityInfo['name'];
            }
        }
        unset($_item);

        return $sharePosterData;
    }

    /**
     * 审核通过、发放奖励
     * @param $id
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function approvalPoster($id, $params = [])
    {
        $type = RealSharePosterModel::TYPE_WEEK_UPLOAD;
        $posters = RealSharePosterModel::getPostersByIds($id, $type);
        if (count($posters) != count($id)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        $now = time();
        $updateData = [
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time'   => $now,
            'verify_user'   => $params['employee_id'] ?? 0,
            'remark'        => $params['remark'] ?? '',
            'update_time'   => $now,
        ];
        $redis = RedisDB::getConn();
        $needAwardList = [];
        foreach ($posters as $key => $poster) {
            // 审核数据操作锁，解决并发导致的重复审核和发奖
            $lockKey = self::KEY_POSTER_VERIFY_LOCK . $poster['id'];
            $lock = $redis->set($lockKey, $poster['id'], 'EX', 120, 'NX');
            if (empty($lock)) {
                continue;
            }
            $where = [
                'id' => $poster['id'],
                'verify_status' => $poster['poster_status']
            ];
            $update = RealSharePosterModel::batchUpdateRecord($updateData, $where);
            if (!empty($update)) {
                $needAwardList[] = $poster['id'];
            }
            //真人产品激活
            QueueService::autoActivate(['student_uuid' => $poster['uuid'], 'passed_time' => time(),'app_id' => Constants::REAL_APP_ID]);
        }
        //真人奖励激活
        if (!empty($needAwardList)) {
            QueueService::addRealUserPosterAward($needAwardList);
        }
        return true;
    }
    
    /**
     * 截图审核-发奖-消费者
     * @param $data
     * @return bool
     */
    public static function addUserAward($data)
    {
        if (empty($data)) {
            return false;
        }
        SimpleLogger::info('RealSharePosterService_addUserAward', ['data' => $data]);
        //发放奖励接口
        RealUserAwardMagicStoneService::sendUserMagicStoneAward($data);
        return true;
    }
    
    /**
     * 审核不通过
     * @param $posterId
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function refusedPoster($posterId, $params = [])
    {
        $reason = $params['reason'] ?? '';
        $remark = $params['remark'] ?? '';
        if (empty($reason) && empty($remark)) {
            throw new RunTimeException(['please_select_reason']);
        }
        
        $type = RealSharePosterModel::TYPE_WEEK_UPLOAD;
        $poster = RealSharePosterModel::getPostersByIds([$posterId], $type);
        $poster = $poster[0] ?? [];
        if (empty($poster)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        
        $status = RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED;
        $time   = time();
        $update = RealSharePosterModel::updateRecord($poster['id'], [
            'verify_status' => $status,
            'verify_time'   => $time,
            'verify_user'   => $params['employee_id'],
            'verify_reason' => implode(',', $reason),
            'update_time'   => $time,
            'remark'        => $remark,
        ]);
        // 审核不通过, 发送模版消息
        if ($update > 0) {
            //$vars = [
            //    'activity_name' => $poster['activity_name'],
            //    'status' => $status
            //];
            //MessageService::sendRealPosterVerifyMessage($poster['open_id'], $vars)
            QueueService::realSendPosterAwardMessage(["share_poster_id" => $posterId]);
        }
        
        return $update > 0;
    }

    /**
     * 真人 - 截图审核详情
     * @param $id
     * @param array $userInfo
     * @return array|mixed
     * @throws RunTimeException
     */
    public static function realSharePosterDetail($id, $userInfo = [])
    {
        $returnData = [];
        if (empty($id)) {
            return $returnData;
        }
        $sharePosterInfo = RealSharePosterModel::getRecord(['id' => $id]);
        if (empty($sharePosterInfo)) {
            return $returnData;
        }
        $sharePosterInfo['can_upload'] = Constants::STATUS_TRUE;
        // TODO qingfeng.lian 利鹏提供方法  /real_student_wx/activity/can_participate_week
        // 是否可重新上传
        $activities = [];
        $allIds = array_column($activities, 'activity_id');
        if (!in_array($sharePosterInfo['activity_id'], $allIds)) {
            $sharePosterInfo['can_upload'] = Constants::STATUS_FALSE;
        }
        if ($sharePosterInfo['verify_status'] == RealSharePosterModel::VERIFY_STATUS_QUALIFIED) {
            $sharePosterInfo['can_upload'] = Constants::STATUS_FALSE;
        }

        $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
        $reasonDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_REASON);

        $returnData['can_upload'] = Constants::STATUS_TRUE;
        $returnData['verify_status'] = $sharePosterInfo['verify_status'];
        $returnData['status_name'] = $statusDict[$sharePosterInfo['verify_status']] ?? $sharePosterInfo['verify_status'];
        $returnData['image_url'] = AliOSS::replaceCdnDomainForDss($sharePosterInfo['image_path']);
        $returnData['award_amount'] = 0;
        $returnData['award_type'] = 0;
        $returnData['reason_str'] = '';


        // 根据状态判断展示逻辑
        switch ($sharePosterInfo['verify_status']) {
            case RealSharePosterModel::VERIFY_STATUS_WAIT: // 审核中
                break;
            case RealSharePosterModel::VERIFY_STATUS_QUALIFIED: // 审核通过
                // 获取奖励
                $awardInfo = RealSharePosterAwardModel::getRecord(['share_poster_id' => $id]);
                $returnData['award_amount'] = $awardInfo['award_num'] ?? 0;
                $returnData['award_type'] = $awardInfo['award_type'] ?? 0;
                break;
            case RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED: // 未通过
                $returnData['reason_str'] = self::reasonToStr(explode(',', $sharePosterInfo['reason']), $reasonDict);
                break;
            default:
                return $returnData;
        }

        return $returnData;
    }
}
