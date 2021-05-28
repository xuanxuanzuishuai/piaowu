<?php
/**
 * 端午节活动
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\PosterModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;

class ActivityDuanWuService
{
    
    public static $cacheKeyEarliestTime = 'OP_STUDENT_EARLIEST_PLAY_TIME_FOR_DUANWU';
    public static $cacheKeyRefereeRank = 'OP_REFEREE_RANK_FOR_DUANWU';
    public static $cacheKeyRankCnt = 'OP_REFEREE_RANK_CNT_FOR_DUANWU';
    
    /**
     * @param $params
     * @return array
     * 活动详情
     */
    public static function activityInfo($params)
    {
        $uid = $params['user_info']['user_id'];
        //$uid = 92;   //TODO 上线时注释
        
        $redis = RedisDB::getConn();
        $userRank = $redis->hget(self::$cacheKeyRefereeRank, $uid);
        
        if (empty($userRank)) {   //用户没有有效邀请用户,没有排名
            $rankUserCnt = $redis->hlen(self::$cacheKeyRankCnt);   //有排名人数
            $rankTips = "未上榜";
            switch (true) {
                case $rankUserCnt < 10:
                    $encourageTips = "加油，再邀请1人购买体验卡并练琴，即可获得200元京东卡奖励";
                    break;
                case $rankUserCnt >= 10 && $rankUserCnt < 90:
                    $encourageTips = "加油，再邀请1人购买体验卡并练琴，即可获得100元京东卡奖励";
                    break;
                case $rankUserCnt >= 90 && $rankUserCnt < 200:
                    $encourageTips = "加油，再邀请1人购买体验卡并练琴，即可获得50元京东卡奖励";
                    break;
                case $rankUserCnt >= 200:
                    $rankCnt = $redis->hget(self::$cacheKeyRankCnt, 200);
                    $diff = $rankCnt + 1;
                    $encourageTips = "加油，再邀请{$diff}人购买体验卡并练琴，即可获得50元京东卡奖励";
                    break;
                default:
                    $encourageTips = "加油，邀请人购买体验卡并练琴，即可获得京东卡奖励";
                    break;
            }
        } else {   //有有效邀请用户
            $rankInfo = json_decode($userRank, true);
            $rank = $rankInfo['rank'];
            $refereeCnt = $rankInfo['referee_cnt'];
            switch (true) {
                case $rank <= 10:
                    $rankTips = "第{$rank}名";
                    $encourageTips = "加油，继续邀请好友购买体验卡并练琴~不要被后面超过哦";
                    break;
                case $rank > 10 && $rank <= 90:
                    $rankTips = "第{$rank}名";
                    $rankCnt = $redis->hget(self::$cacheKeyRankCnt, 10);
                    $diff = $rankCnt - $refereeCnt;
                    $diff < 1 && $diff = 1;
                    $encourageTips = "加油，再邀请{$diff}人购买体验卡并练琴，即可获得200元京东卡奖励";
                    break;
                case $rank > 90 && $rank <= 200:
                    $rankTips = "第{$rank}名";
                    $rankCnt = $redis->hget(self::$cacheKeyRankCnt, 90);
                    $diff = $rankCnt - $refereeCnt;
                    $diff < 1 && $diff = 1;
                    $encourageTips = "加油，再邀请{$diff}人购买体验卡并练琴，即可获得100元京东卡奖励";
                    break;
                case $rank > 200 && $rank <= 300:
                    $rankTips = "第{$rank}名";
                    $rankCnt = $redis->hget(self::$cacheKeyRankCnt, 200);
                    $diff = $rankCnt - $refereeCnt;
                    $diff < 1 && $diff = 1;
                    $encourageTips = "加油，再邀请{$diff}人购买体验卡并练琴，即可获得50元京东卡奖励";
                    break;
                case $rank > 300:
                    $rankTips = "第300+";
                    $rankCnt = $redis->hget(self::$cacheKeyRankCnt, 200);
                    $diff = $rankCnt - $refereeCnt;
                    $diff < 1 && $diff = 1;
                    $encourageTips = "加油，再邀请{$diff}人购买体验卡并练琴，即可获得50元京东卡奖励";
                    break;
                default:
                    $rankTips = "第300+";
                    $encourageTips = "加油，邀请人购买体验卡并练琴，即可获得京东卡奖励";
                    break;
            }
        }
        
        $activityConfig = DictConstants::getSet(DictConstants::ACTIVITY_DUANWU_CONFIG);
        
        $activityId = $activityConfig['activity_id'] ?? 0;
        $userStatusInfo = StudentService::dssStudentStatusCheck($uid);   // 获取用户当前状态
        $userStatus = $userStatusInfo['student_status'];
        
        $inviteUrlApp = $activityConfig['app_url'] ?? '';
        if (!$inviteUrlApp) {
            $path = $activityConfig['app_poster_path'] ?? '';
            $config = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
            $channelId = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'BUY_TRAIL_REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT');
            // 埋点 - 活动id,海报id,用户身份
            $extParams = [
                'a' => $activityId,
                'p' => PosterModel::getIdByPath($path, ['name' => '端午节活动']),
                'user_current_status' => $userStatus,
            ];
            //$uid = 12;   //TODO 上线时注释
            //$channelId = 1;   //TODO 上线时注释
            $posterImgFile = PosterService::generateQRPosterAliOss(
                $path,
                $config,
                $uid,
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                $channelId,
                $extParams
            );
            $inviteUrlApp = $posterImgFile['poster_save_full_path'] ?? '';
        }
        
        $result = [
            'activity_id' => $activityId,
            'user_status' => $userStatus,
            'rank_tips' => $rankTips,
            'encourage_tips' => $encourageTips,
            'invite_url_wx' => $activityConfig['wx_url'],
            'invite_url_app' => $inviteUrlApp,
        ];
        
        return $result;
    }
    
    /**
     * @param $params
     * @return array
     * 推荐列表
     */
    public static function refereeList($params, $page, $count)
    {
        $returnList = [
            'invite_total_num' => 0,
            'invite_student_list' => [],
        ];
        
        $uid = $params['user_info']['user_id'];
        //$uid = 92;   //TODO 上线时注释
        
        $where = ['referee_id' => $uid];
        $startTime = strtotime('2021-06-10');
        $endTime = strtotime('2021-06-20');
        //$startTime = 1602717330;   //TODO 上线时注释
        //$endTime = 1602737330;   //TODO 上线时注释
        $where['create_time[>=]'] = $startTime;
        $where['create_time[<=]'] = $endTime;
        $returnList['invite_total_num'] = StudentReferralStudentStatisticsModel::getCount($where);
        if ($returnList['invite_total_num'] <= 0) {
            return $returnList;
        }
        
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $where['ORDER'] = ['id' => 'DESC'];
        // 获取邀请学生id列表
        $list = StudentReferralStudentStatisticsModel::getRecords($where);
        // 如果数据为空直接返回
        if (empty($list)) {
            return $returnList;
        }
        $inviteStudentId = array_column($list, 'student_id');
        // 获取所有学生信息
        $inviteStudentList = DssStudentModel::getRecords(['id' => $inviteStudentId], ['id', 'name', 'mobile', 'thumb']);
        $inviteStudentArr = [];
        if (is_array($inviteStudentList)) {
            foreach ($inviteStudentList as $_item) {
                $inviteStudentArr[$_item['id']] = $_item;
            }
        }
        // 获取学生节点名称
        $stageNameList = DictConstants::getSet(DictConstants::AGENT_USER_STAGE);
        // 获取学生节点
        $studentStageList = StudentReferralStudentDetailModel::getRecords(['student_id' => $inviteStudentId]);
        $studentStageArr = [];
        
        $stageBase = [
            ['stage_name' => $stageNameList[0] ?? '', 'create_time' => '', 'unix_create_time' => '', 'finish' => '0',],
            ['stage_name' => $stageNameList[1] ?? '', 'create_time' => '', 'unix_create_time' => '', 'finish' => '0',],
            ['stage_name' => '练琴', 'create_time' => '', 'unix_create_time' => '', 'finish' => '0',],
            ['stage_name' => $stageNameList[2] ?? '', 'create_time' => '', 'unix_create_time' => '', 'finish' => '0',],
        ];
        foreach ($inviteStudentId as $sid) {
            $studentStageArr[$sid] = $stageBase;
        }
        
        foreach ($studentStageList as $item) {
            $realStage = $item['stage']==2 ? 3 : $item['stage'];
            $studentStageArr[$item['student_id']][$realStage] = [
                'stage_name' => $stageNameList[$item['stage']] ?? '',
                'create_time' => date("Y-m-d", $item['create_time']),
                'unix_create_time' => $item['create_time'],
                'finish' => '1',
            ];
        }
        
        $redis = RedisDB::getConn();
        foreach ($studentStageArr as $sid => $studentStageEach) {
            $earliestTime = $redis->hget(self::$cacheKeyEarliestTime, $sid);
            if ($earliestTime) {
                $studentStageArr[$sid][2] = [
                    'stage_name' => '练琴',
                    'create_time' => date("Y-m-d", $earliestTime),
                    'unix_create_time' => $earliestTime,
                    'finish' => '1',
                ];
            }
        }
        
        foreach ($list as $_invite) {
            $s_info = $inviteStudentArr[$_invite['student_id']] ?? [];
            // 如果购买体验课的时间比购买年卡的时间大，不显示体验卡节点
            $stage = $studentStageArr[$_invite['student_id']] ?? $stageBase;
            //if (isset($stage[3]) && isset($stage[1]) && $stage[3]['unix_create_time'] < $stage[1]['unix_create_time']) {
            //    unset($stage[1]);
            //}
            // 更改绑定关系建立的时间
            if ($stage[0]['finish']) {
                $stage[0]['create_time'] = date("Y-m-d", $_invite['create_time']);
                $stage[0]['unix_create_time'] =$_invite['create_time'];
            }
            $returnList['invite_student_list'][] = [
                'mobile' => isset($s_info['mobile']) ? Util::hideUserMobile($s_info['mobile']) : '',
                'name' => isset($s_info['name']) ? $s_info['name'] : '',
                'thumb' => isset($s_info['thumb']) ? AliOSS::signUrls($s_info['thumb']) : '',
                'stage' => $stage,
            ];
        }
        
        return $returnList;
    }
}
