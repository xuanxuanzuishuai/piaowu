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
use App\Models\RealSharePosterModel;
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
            'verify_reason' => implode(',', $params['reason']),
            'update_time'   => $time,
            'remark'        => $params['remark'] ?? '',
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
