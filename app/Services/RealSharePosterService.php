<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-09-02 10:56:15
 * Time: 7:07 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Libs\Operation;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\RealSharePosterAwardModel;
use App\Models\RealSharePosterModel;
use App\Libs\Erp;
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
     * 审核通过、发放奖励
     * @param $id
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function approval($id, $employeeId)
    {
        $type = RealSharePosterModel::TYPE_WEEK_UPLOAD;
        $posters = RealSharePosterModel::getPostersByIds($id, $type);
        if (count($posters) != count($id)) {
            throw new RunTimeException(['get_share_poster_error']);
        }
        
        $taskConfig = DictConstants::getSet(DictConstants::NORMAL_UPLOAD_POSTER_TASK);
        
        $now = time();
        $updateData = [
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            'verify_time'   => $now,
            'verify_user'   => $employeeId,
            'remark'        => '',
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
            
            //计算当前真正应该获得的奖励
            $where = [
                'id[!]'         => $poster['id'],
                'student_id'    => $poster['student_id'],
                'type'          => RealSharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            ];
            $count = RealSharePosterModel::getCount($where);
            $taskId = $taskConfig[$count] ?? $taskConfig['-1'];
            if (!empty($update)) {
                $needAwardList[] = [
                    'id' => $poster['id'],
                    'uuid' => $poster['uuid'],
                    'task_id' => $taskId,
                    'activity_id' => $poster['activity_id'],
                ];
            }
            //真人产品激活
            QueueService::autoActivate(['student_uuid' => $poster['uuid'], 'passed_time' => time(),'app_id' => Constants::REAL_APP_ID]);
        }
        //TODO 真人奖励激活
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
        //TODO 等待庆丰的发放奖励接口
        //foreach ($data as $poster) {
        //    $status = $poster['status'] ?? ErpReferralService::EVENT_TASK_STATUS_COMPLETE;
        //    $res = (new Erp())->addEventTaskAward($poster['uuid'], $poster['task_id'], $status);
        //    if (empty($res['data'])) {
        //        SimpleLogger::error('ERP_CREATE_USER_EVENT_TASK_AWARD_FAIL', [$poster]);
        //    }
        //    $awardIds  = $res['data']['user_award_ids'] ?? [];
        //    $pointsIds = $res['data']['points_award_ids'] ?? [];
        //    $awardId   = implode(',', $awardIds);
        //    $pointsId  = implode(',', $pointsIds);
        //    SharePosterModel::updateRecord($poster['id'], ['award_id' => $awardId, 'points_award_id'=> $pointsId]);
        //    QueueService::sharePosterAwardMessage(['points_award_ids' => $pointsIds, 'activity_id' => $poster['activity_id'] ?? 0]);
        //}
        return true;
    }
    
    /**
     * 审核不通过
     * @param $posterId
     * @param $employeeId
     * @param $reason
     * @param $remark
     * @return int|null
     * @throws RunTimeException
     */
    public static function refused($posterId, $employeeId, $reason, $remark)
    {
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
            'verify_user'   => $employeeId,
            'verify_reason' => implode(',', $reason),
            'update_time'   => $time,
            'remark'        => $remark ?? '',
        ]);
        // 审核不通过, 发送模版消息
        if ($update > 0) {
            $vars = [
                'activity_name' => $poster['activity_name'],
                'status' => $status
            ];
            MessageService::sendRealPosterVerifyMessage($poster['open_id'], $vars);
        }
        
        return $update > 0;
    }
}
