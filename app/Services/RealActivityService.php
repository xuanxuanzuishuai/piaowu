<?php

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RealDictConstants;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentAccountDetail;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealMonthActivityModel;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Models\TemplatePosterModel;
use App\Services\Queue\QueueService;

class RealActivityService
{
    use TraitRealXyzop1321Service;
    /**
     * 获取周周领奖活动
     * @param int $studentId 学生ID
     * @param int $fromType 来源类型:微信 app
     * @return array
     * @throws RunTimeException
     */
    public static function weekActivityData($studentId, $fromType)
    {
        $data = [
            'list' => [],
            'activity' => [],
            'student_info' => [],
            'channel_list' => [],
            'can_upload' => false
        ];
        //获取学生信息
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        //检测是否可以看到周周领奖tab
        $showTabList = self::monthAndWeekActivityTabShowList($studentDetail);
        if (empty($showTabList['week_tab'])) {
            return $data;
        }
        if (empty($studentDetail)) {
            throw new RunTimeException(['student_not_exist']);
        }
        //资格检测
        $checkRes = ErpUserService::getStudentStatus($studentId);
        $data['student_info'] = [
            'uuid' => $studentDetail['uuid'],
            'pay_status' => $checkRes['pay_status'],
            'nickname' => !empty($studentDetail['name']) ? $studentDetail['name'] : ErpUserService::getStudentDefaultName($studentDetail['mobile']),
            'pay_status_zh' => $checkRes['status_zh'],
            'thumb' => ErpUserService::getStudentThumbUrl([$studentDetail['thumb']])[0],
        ];
        if ($checkRes['pay_status'] != ErpStudentAppModel::STATUS_PAID) {
            return $data;
        }
        //检测当前学生是否可以看到上传截图按钮
        $data['can_upload'] = empty(self::getCanParticipateWeekActivityIds($studentDetail, 2)) ? false : true;
        list($data['list'], $data['activity']) = self::initWeekOrMonthActivityData(OperationActivityModel::TYPE_WEEK_ACTIVITY, $fromType, $studentDetail);
        //渠道获取
        list($data['channel_list']['first']) = DictConstants::getValues(DictConstants::ACTIVITY_CONFIG, ['channel_week_' . $fromType]);
        return $data;
    }


    /**
     * 获取月月领奖活动
     * @param int $studentId 学生ID
     * @param int $fromType 来源类型:微信 app
     * @return array
     * @throws RunTimeException
     */
    public static function monthActivityData($studentId, $fromType)
    {
        $data = [
            'list' => [],
            'activity' => [],
            'student_info' => [],
            'channel_list' => [],
        ];
        //获取学生信息
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        if (empty($studentDetail)) {
            throw new RunTimeException(['student_not_exist']);
        }
        //状态获取
        $checkRes = ErpUserService::getStudentStatus($studentId);
        $data['student_info'] = [
            'uuid' => $studentDetail['uuid'],
            'nickname' => !empty($studentDetail['name']) ? $studentDetail['name'] : ErpUserService::getStudentDefaultName($studentDetail['mobile']),
            'pay_status' => $checkRes['pay_status'],
            'pay_status_zh' => $checkRes['status_zh'],
            'thumb' => ErpUserService::getStudentThumbUrl([$studentDetail['thumb']])[0],
        ];
        list($data['list'], $data['activity']) = self::initWeekOrMonthActivityData(OperationActivityModel::TYPE_MONTH_ACTIVITY, $fromType, $studentDetail);
        //渠道获取
        list($data['channel_list']['first'], $data['channel_list']['second']) = DictConstants::getValues(DictConstants::ACTIVITY_CONFIG, ['channel_month_' . $fromType, 'channel_month_' . $fromType . '_second']);
        return $data;
    }


    /**
     * 初始化周周月月活动的基础数据
     * @param $activityType
     * @param $fromType
     * @param $studentDetail
     * @return array
     * @throws RunTimeException
     */
    private static function initWeekOrMonthActivityData($activityType, $fromType, $studentDetail)
    {
        //查询当前有效的活动
        $activityData = [];
        $time = time();
        if ($activityType == OperationActivityModel::TYPE_WEEK_ACTIVITY) {
            // XYZOP-1321 10.26-11.30首次付费时的付费用户获取指定活动
            if (self::xyzopCheckCondition($studentDetail)) {
                $activityData = self::xyzopGetWeekActivityList($studentDetail)['list'] ?? [];
            } else {
                $activityData = RealWeekActivityModel::getStudentCanSignWeekActivity(1, $time);
            }
        } elseif ($activityType == OperationActivityModel::TYPE_MONTH_ACTIVITY) {
            $activityData = RealMonthActivityModel::getStudentCanSignMonthActivity(1);
        }
        if (empty($activityData)) {
            return [[], []];
        } else {
            $activityData = $activityData[0];
        }
        //海报定位参数配置
        $posterConfig = PosterService::getPosterConfig();
        //查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityData);
        if (empty($posterList)) {
            return [[], []];
        }
        $typeColumn = array_column($posterList, 'type');
        $activityPosterIdColumn = array_column($posterList, 'activity_poster_id');
        //海报排序处理
        if (isset($activityData['poster_order']) && ($activityData['poster_order'] == TemplatePosterModel::POSTER_ORDER)) {
            array_multisort($typeColumn, SORT_DESC, $activityPosterIdColumn, SORT_ASC, $posterList);
        }
        //获取渠道ID配置
        $channel = PosterTemplateService::getChannel($activityType, $fromType);
        $extParams = [
            'user_status' => $studentDetail['status'],
            'activity_id' => $activityData['activity_id'],
        ];
        //获取小程序二维码
        $userQrParams = [];
        foreach ($posterList as &$item) {
            $_tmp = $extParams;
            $_tmp['poster_id'] = $item['poster_id'];
            $_tmp['user_id'] = $studentDetail['id'];
            $_tmp['user_type'] = DssUserQrTicketModel::STUDENT_TYPE;
            $_tmp['channel_id'] = $channel;
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $_tmp['date'] = date('Y-m-d',time());
            $_tmp['qr_sign'] = QrInfoService::createQrSign($_tmp, Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE);
            $userQrParams[] = $_tmp;
            $item['qr_sign'] = $_tmp['qr_sign'];
        }
        unset($item);
        $userQrArr = MiniAppQrService::getUserMiniAppQrList(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $userQrParams);
        //处理数据
        foreach ($posterList as &$item) {
            $extParams['poster_id'] = $item['poster_id'];
            $item = PosterTemplateService::formatPosterInfo($item);
            //个性化海报只需获取二维码，不用合成海报
            if ($item['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $item['qr_code_url'] = AliOSS::replaceCdnDomainForDss($userQrArr[$item['qr_sign']]['qr_path']);
                $word = [
                    'qr_id' => $userQrArr[$item['qr_sign']]['qr_id'],
                    'date'  => date('m.d', time()),
                ];
                $item['poster_url'] = PosterService::addAliOssWordWaterMark($item['poster_path'], $word, $posterConfig);
                continue;
            }
            // 海报图：
            $poster = PosterService::generateQRPoster(
                $item['poster_path'],
                $posterConfig,
                $studentDetail['id'],
                DssUserQrTicketModel::STUDENT_TYPE,
                $channel,
                $extParams,
                $userQrArr[$item['qr_sign']] ?? []
            );
            $item['poster_url'] = $poster['poster_save_full_path'];
        }
        $activityData['award_rule'] = ActivityExtModel::getActivityExt($activityData['activity_id'])['award_rule'];
        return [$posterList, ActivityService::formatData($activityData)];
    }

    /**
     * 获取可参与周周领奖活动列表：最多两期活动
     * @param $studentData
     * @param $limitActivity
     * @return array
     */
    public static function getCanParticipateWeekActivityIds($studentData, $limitActivity)
    {
        //已开始的活动，按照开始时间倒叙排序，第一活动如果处在有效期内则返回最近的两个活动，否则返回第一个活动
        $time = time();
        // xyzop-1321需求，获取可上传的活动列表
        if (self::xyzopCheckCondition($studentData)) {
            $activityData = self::xyzopGetWeekActivityList($studentData)['list'] ?? [];
        } else {
            $activityData = RealWeekActivityModel::getStudentCanSignWeekActivity($limitActivity, 0);
        }
        if (empty($activityData)) {
            return [];
        }
        $canJoinActivityIds = [];
        //学生第一次付费时间必须的活动结束时间之前
        foreach ($activityData as $val) {
            if ($val['end_time'] >= $studentData['first_pay_time']) {
                $canJoinActivityIds[] = $val['activity_id'];
            }
            if ($val['end_time'] < $time) {
                break;
            }
        }
        if (empty($canJoinActivityIds)) {
            return [];
        }
        //查看学生相对可参与活动的状态
        $haveQualifiedActivityIds = RealSharePosterModel::getRecords([
            'student_id' => $studentData['id'],
            'activity_id' => $canJoinActivityIds,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
        ], 'activity_id');
        //计算差集
        $diffActivityIds = array_diff($canJoinActivityIds, $haveQualifiedActivityIds);
        if (empty($diffActivityIds)) {
            return [];
        }
        $result = [];
        //活动排序
        if ((count($diffActivityIds) == 2) && (($time - $activityData[0]['start_time']) <= Util::TIMESTAMP_12H)) {
            foreach (array_reverse($activityData) as $al) {
                $result[] = [
                    'activity_id' => $al['activity_id'],
                    'name' => $al['name'],
                ];
            }
        } else {
            $activityData = array_column($activityData, null, 'activity_id');
            foreach ($diffActivityIds as $aid) {
                $result[] = [
                    'activity_id' => $aid,
                    'name' => $activityData[$aid]['name'],
                ];
            }
        }
        return $result;
    }

    /**
     * 周周有奖活动海报截图上传
     * @param $studentData
     * @param $activityId
     * @param $imagePath
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function weekActivityPosterScreenShotUpload($studentData, $activityId, $imagePath)
    {
        $time = time();
        //资格检测
        $checkRes = ErpUserService::getStudentStatus($studentData['id']);
        if ($checkRes['pay_status'] != ErpStudentAppModel::STATUS_PAID) {
            throw new RunTimeException(['student_status_disable']);
        }
        //活动检测:获取最新两个最新可参与的周周领奖活动
        $canParticipateWeekActivityIds = self::getCanParticipateWeekActivityIds($studentData, 2);
        if (!in_array($activityId, array_column($canParticipateWeekActivityIds, 'activity_id'))) {
            throw new RunTimeException(['wait_for_next_event']);
        }
        //审核通过不允许上传截图
        $uploadRecord = RealSharePosterModel::getRecord([
            'student_id' => $studentData['id'],
            'activity_id' => $activityId,
            'ORDER' => ['id' => 'DESC']
        ], ['verify_status', 'id']);
        if (!empty($uploadRecord) && ($uploadRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_QUALIFIED)) {
            throw new RunTimeException(['wait_for_next_event']);
        }
        $data = [
            'student_id' => $studentData['id'],
            'type' => RealSharePosterModel::TYPE_WEEK_UPLOAD,
            'activity_id' => $activityId,
            'image_path' => $imagePath,
            'verify_reason' => '',
            'unique_code' => '',
            'create_time' => $time,
            'update_time' => $time,
        ];
        if (empty($uploadRecord) || $uploadRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
            $res = RealSharePosterModel::insertRecord($data);
        } else {
            unset($data['create_time']);
            $count = RealSharePosterModel::updateRecord($uploadRecord['id'], $data);
            if (empty($count)) {
                throw new RunTimeException(['update_fail']);
            }
            $res = $uploadRecord['id'];
        }
        if (empty($res)) {
            throw new RunTimeException(['share_poster_add_fail']);
        }
        //系统自动审核
        QueueService::checkPoster(['id' => $res, 'app_id' => Constants::REAL_APP_ID]);
        return $res;
    }

    /**
     * 真人 - 跑马灯数据-获取用户金叶子相关信息
     * @param $topNum
     * @return array
     */
    public static function realUserRewardTopList($topNum = 20)
    {
        // 获取预设的手机号 (智能的账号) 和 其他配置
        list($mobileStr, $magicStone) = RealDictConstants::get(RealDictConstants::REAL_TWO_SHARE_POSTER_TOP_CONFIG, [ 'mobile_invitee_num', 'magic_stone']);
        $mobileInviteeNumArr = json_decode($mobileStr, true);
        $mobileList = [];
        foreach ($mobileInviteeNumArr as $mk=>$mv){
            $mobileList[] = (string)$mk;
        }
        $userList = ErpStudentModel::getRecords(['mobile' => $mobileList], ['id', 'name', 'mobile', 'thumb']);
        $userList = array_column($userList, null, 'mobile');
        $accountDetail = [];
        if (empty($mobileInviteeNumArr) || !is_array($mobileInviteeNumArr)) {
            return $accountDetail;
        }
        //设置头像数据和默认头像数据
        $thumbData = array_column($userList, 'thumb', 'id');
        $thumbData[0] = '';
        $thumbList = ErpUserService::getStudentThumbUrl($thumbData);
        foreach ($mobileInviteeNumArr as $_mobile => $_invitee) {
            $_userInfo = $userList[$_mobile] ?? ['thumb' => '', 'name' => '', 'id' => 0, 'mobile' => ''];
            $accountDetail[] = [
                'student_id' => $_userInfo['id'] ?? 0,
                'invite_num' => $_invitee,
                'magic_stone_num' => ceil($_invitee * $magicStone),
                'avatar' => $thumbList[$_userInfo['id']],
                'name' => $_userInfo['name'] ?: ErpUserService::getStudentDefaultName($_mobile),
            ];
        }

        // 排序 - 邀请人数
        array_multisort(array_column($accountDetail, 'invite_num'), SORT_DESC, $accountDetail);
        // 排序 - 魔法石总数量 - 魔法石是通过邀请人数计算得出，所以不需要在安装魔法石总数量排序
        // array_multisort(array_column($accountDetail, 'magic_stone_num'), SORT_DESC, $accountDetail);


        /** 真实数据查询 - 暂时注释 */
        // // 从redis缓存读取
        // $redis = RedisDB::getConn();
        // $cacheKey = Constants::REAL_APP_ID .'_user_reward_details';
        // $value = $redis->get($cacheKey);
        // if (!empty($value)) {
        //     return json_decode($value, true);
        // }
        // // 获取邀请年卡前20名用户已到账魔法石数量， 倒序
        // $referralTopList = ErpReferralUserRefereeModel::getReferralBySort($topNum);
        // if (empty($referralTopList)) {
        //     return [];
        // }
        // // 获取推荐人信息
        // $referral = [];
        // $referralIds = [];
        // foreach ($referralTopList as $item) {
        //     $referral[$item['referee_id']] = $item;
        //     $referralIds[] = $item['referee_id'];
        // }
        // unset($item, $referralTopList);
        // $referralUserList = ErpStudentModel::getRecords(['id' => $referralIds], ['id', 'name', 'mobile', 'thumb','uuid']);
        // if (empty($referralUserList)) {
        //     return [];
        // }
        // $referralUserList = array_column($referralUserList, null, 'id');
        // // 获取用户入账总数
        // $accountDetail = ErpStudentAccountDetail::getUserRewardTotal($referralIds, ErpStudentAccountDetail::SUB_TYPE_MAGIC_STONE);
        // if (empty($accountDetail)) {
        //     return [];
        // }
        // // 根据入账总数排序
        // array_multisort(array_column($accountDetail, 'total'), SORT_DESC, $accountDetail);
        //
        // // 组装数据
        // foreach ($accountDetail as &$val) {
        //     $_thumb = $referralUserList[$val['referee_id']]['thumb'] ?? '';
        //     $_name = $referralUserList[$val['referee_id']]['name'] ?? '';
        //     // 邀请人数
        //     $val['invite_num']    = $referral[$val['referee_id']]['num'] ?? 0;
        //     // 魔法石积分 - 单位万
        //     $val['magic_stone_num'] = ceil($val['total'] / (ErpPackageV1Model::DEFAULT_SCALE_NUM * SourceMaterialService::WAN_UNIT));
        //     // 昵称
        //     $val['name']          = $_name ?: ErpUserService::getStudentThumbUrl($_thumb);
        //     // 头像
        //     $val['avatar']        = ErpUserService::getStudentDefaultName($_thumb);
        // }
        // unset($val);
        //
        // $redis->setex($cacheKey, Util::TIMESTAMP_12H, json_encode($accountDetail));
        return $accountDetail;
    }

    /**
     * 周周/月月领奖tab展示列表
     * @param $studentData
     * @return array
     */
    public static function monthAndWeekActivityTabShowList($studentData)
    {
        $tabData['month_tab'] = [
            'title' => '月月有奖',
            'aw_type' => 'month'
        ];
        $splitTime = DictConstants::get(DictConstants::ACTIVITY_CONFIG, 'real_week_tab_first_pay_split_time');
        $studentId = $studentData['user_id'] ?? 0;
        if (UserService::checkRealStudentIdentityIsNormal($studentId, 0, intval($splitTime))) {
            $tabData['week_tab'] = [
                'title' => '周周领奖',
                'aw_type' => 'week'
            ];
        }
        $weekTab = self::xyzopWeekActivityTabShowList($studentData);
        if (!empty($weekTab)) {
            $tabData['week_tab'] = $weekTab;
        }
        return $tabData;
    }
}
