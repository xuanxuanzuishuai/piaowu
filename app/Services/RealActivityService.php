<?php

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RealDictConstants;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RealMonthActivityModel;
use App\Models\RealSharePosterDesignateUuidModel;
use App\Models\RealSharePosterModel;
use App\Models\RealSharePosterTaskListModel;
use App\Models\RealWeekActivityModel;
use App\Models\TemplatePosterModel;
use App\Services\Queue\QueueService;
use App\Services\StudentServices\ErpStudentService;
use Medoo\Medoo;

class RealActivityService
{
    use TraitRealXyzop1321Service;
    /**
     * 获取周周领奖活动
     * @param int $studentId 学生ID
     * @param string $fromType 来源类型:微信 app
     * @return array
     * @throws RunTimeException
     */
    public static function weekActivityData(int $studentId, string $fromType): array
    {
        $data = [
            'list' => [],
            'activity' => [],
            'student_info' => [],
            'channel_list' => [],
            'is_have_activity' => true,//当前时间是否存在生效的活动
            'no_re_activity_reason' => 1,//补卡活动的状态
        ];
        //获取学生信息
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        if (empty($studentDetail)) {
            throw new RunTimeException(['student_not_exist']);
        }
        // 获取学生付费状态
        $studentPayStatus = ErpUserService::getStudentStatus($studentId);
        $data['student_info'] = [
            'uuid' => $studentDetail['uuid'],
            'nickname' => !empty($studentDetail['name']) ? $studentDetail['name'] : ErpUserService::getStudentDefaultName($studentDetail['mobile']),
            'thumb' => ErpUserService::getStudentThumbUrl([$studentDetail['thumb']])[0],
            'real_person_paid' => 0,
            'can_upload' => true,
            'pay_status' => $studentPayStatus['pay_status'] ?? '',
            'pay_status_zh' => $studentPayStatus['status_zh'] ?? '',
        ];
        $data['no_re_activity_reason'] = self::getReCardActivityList($studentDetail)['no_re_activity_reason'];
        // 获取活动详情
        $activityList = RealWeekActivityService::getStudentCanPartakeWeekActivityList($studentDetail);
        $canPartakeActivityId = $activityList[0]['activity_id'] ?? 0;
        if (!empty($canPartakeActivityId)) {
            // 获取活动任务列表
            $activityTaskList = RealSharePosterTaskListModel::getRecords(['activity_id' => $canPartakeActivityId, 'ORDER' => ['task_num' => 'ASC']]);
        }
        if (empty($activityTaskList)) {
            $data['is_have_activity'] = false;
            $data['student_info']['can_upload'] = false;
            return $data;
        }
        // 查看学生可参与的活动中已经审核通过的分享任务
        $haveQualifiedActivityIds = RealSharePosterModel::getRecords([
            'student_id'    => $studentId,
            'activity_id'   => $canPartakeActivityId,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
        ], 'task_num');
        // 查看学生相对可参与活动的状态 - 计算差集
        $diffActivityTaskNum = array_diff(array_column($activityTaskList, 'task_num'), $haveQualifiedActivityIds);
        if (empty($diffActivityTaskNum)) {
            $data['student_info']['can_upload'] = false;
        }
        // 获取活动海报
        list($data['list'], $data['activity']) = self::getWeekActivityPosterList($activityList[0], $fromType, $studentDetail);
        //渠道获取
        list($data['channel_list']['first']) = DictConstants::getValues(DictConstants::ACTIVITY_CONFIG, ['channel_week_' . $fromType]);
        return $data;
    }

    /**
     * 获取可以补卡的活动列表:以结束并结束时间距离现在5天内
     * @param $studentInfo
     * @return array 1当前没有具备补卡资格的活动 2具备补卡资格的活动分享任务都已通过 3存在有效的补卡活动
     */
    public static function getReCardActivityList($studentInfo)
    {
        $reCardActivity = [
            'no_re_activity_reason' => 1,
            'list' => [],
        ];
        $activityList = RealWeekActivityService::getStudentCanPartakeWeekActivityList($studentInfo, 2);
        if (empty($activityList)) {
            return $reCardActivity;
        }
        $activityIds = array_column($activityList, 'activity_id');
        // 获取活动任务列表
        $activityTaskList = RealSharePosterTaskListModel::getActivityTaskList($activityIds);
        if (empty($activityTaskList)) {
            return $reCardActivity;
        }
        $activityTaskList = array_column($activityTaskList, null, 'activity_task');
        // 查看学生可参与的活动中已经审核通过的分享任务
        $haveQualifiedActivityIds = RealSharePosterModel::getRecords([
            'student_id' => $studentInfo['student_id'],
            'activity_id' => $activityIds,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
            'task_num' => array_unique(array_column($activityTaskList, 'task_num')),
        ], ["activity_task" => Medoo::raw('concat_ws(:separator,activity_id,task_num)', [":separator" => '-'])]);
        // 差集
        $diffActivityTaskNum = array_diff(array_column($activityTaskList, 'activity_task'), array_column($haveQualifiedActivityIds, 'activity_task'));
        if (empty($diffActivityTaskNum)) {
            $reCardActivity['no_re_activity_reason'] = 2;
        } else {
            // 交集
            $canPartakeActivity = array_intersect_key($activityTaskList, array_flip($diffActivityTaskNum));
            array_multisort(array_column($canPartakeActivity, 'start_time'), SORT_DESC, array_column($canPartakeActivity, 'task_num'), SORT_ASC, $canPartakeActivity);
            $reCardActivity['no_re_activity_reason'] = 3;
            $reCardActivity['list'] = $canPartakeActivity;
        }

        return $reCardActivity;
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
            'can_upload' => false,

        ];
        //获取学生信息
        $studentDetail = ErpStudentModel::getStudentInfoById($studentId);
        if (empty($studentDetail)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $studentIdAttribute = ErpStudentService::getStudentCourseData($studentDetail['uuid']);
        if (!empty($studentIdAttribute['course_count_num'])){
            // 有剩余课程才参加月月有奖
            $data['can_upload'] = true;
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
     * 获取用户身份命中周周领奖活动
     * @param $studentData
     * @return array
     */
    public static function getCanPartakeWeekActivity($studentData): array
    {
        // 获取用户信息
        $studentInfo = ErpStudentModel::getRecord(['id' => $studentData['id']]);
        if (empty($studentInfo)) {
            return [];
        }
        $time = time();
        // 获取当前时间有效的活动
        $nowAffectActivityData = RealWeekActivityService::getStudentCanPartakeWeekActivityList([
            'student_id' => $studentInfo['id'],
            'uuid' => $studentInfo['uuid'],
        ]);
        //有效活动的开始时间是否在24小时内：true是 false不是
        $activityTimeStatusIn24 = true;
        $currentActivityCanPartakeActivity = [];
        $currentActivity = array_shift($nowAffectActivityData);
        if (!empty($currentActivity) && (($time - $currentActivity['start_time']) > (Util::TIMESTAMP_1H * 24))) {
            $activityTimeStatusIn24 = false;
        }
        // 获取活动任务列表
        $activityTaskList = RealSharePosterTaskListModel::getActivityTaskList($currentActivity['activity_id']);
        if (!empty($activityTaskList)) {
            $activityTaskList = array_column($activityTaskList, null, 'activity_task');
            // 查看学生可参与的活动中已经审核通过的分享任务
            $haveQualifiedActivityIds = RealSharePosterModel::getRecords([
                'student_id' => $studentData['id'],
                'activity_id' => $currentActivity['activity_id'],
                'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
                'task_num' => array_unique(array_column($activityTaskList, 'task_num')),
            ], ["activity_task" => Medoo::raw('concat_ws(:separator,activity_id,task_num)', [":separator" => '-'])]);
            // 差集
            $diffActivityTaskNum = array_diff(array_column($activityTaskList, 'activity_task'), array_column($haveQualifiedActivityIds, 'activity_task'));
            if (!empty($diffActivityTaskNum)) {
                // 交集
                $currentActivityCanPartakeActivity = array_intersect_key($activityTaskList, array_flip($diffActivityTaskNum));
            }
        }
        //获取可以补卡的活动
        $reCardActivityList = self::getReCardActivityList(['student_id' => $studentInfo['id'], 'uuid' => $studentInfo['uuid'],])['list'];
        if ($activityTimeStatusIn24) {
            //当前活动开始24小时内
            $totalActivityList = array_merge($reCardActivityList, $currentActivityCanPartakeActivity);
        } else {
            //当前活动开始24小时后
            $totalActivityList = array_merge($currentActivityCanPartakeActivity, $reCardActivityList);
        }
        if (empty($totalActivityList)) {
            return [];
        }
        // 格式化数据
        $result = array_map(function ($av) {
            return [
                'activity_id' => $av['activity_id'],
                'task_num' => $av['task_num'],
                'name' => RealWeekActivityService::formatWeekActivityTaskName($av),
            ];
        }, $totalActivityList);
        return array_values($result);
    }

    /**
     * 周周有奖活动海报截图上传
     * @param $studentData
     * @param $activityId
     * @param $imagePath
     * @param $taskNum
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function weekActivityPosterScreenShotUpload($studentData, $activityId, $imagePath, $taskNum)
    {
        /** 新奖励规则 */
        $time = time();
        //审核通过不允许上传截图
        $uploadRecord = RealSharePosterModel::getRecord([
            'student_id' => $studentData['id'],
            'activity_id' => $activityId,
            'task_num' => $taskNum,
            'ORDER' => ['id' => 'DESC']
        ], ['verify_status', 'id']);
        if (!empty($uploadRecord) && ($uploadRecord['verify_status'] == RealSharePosterModel::VERIFY_STATUS_QUALIFIED)) {
            throw new RunTimeException(['wait_for_next_event']);
        }
        //重新上传不校验资格，否则需要校验活动以及身份数据
        if (empty($uploadRecord)) {
            $studentInfo = ErpStudentModel::getRecord(['id' => $studentData['id']]);
            //资格检测 - 获取用户身份属性
            $studentIdAttribute = ErpStudentService::getStudentCourseData($studentInfo['uuid']);
            // 检查一下用户是否是有效用户，不是有效用户不可能有可参与的活动
            if (empty($studentIdAttribute['is_valid_pay'])) {
                // 检查用户是不是活动指定的uuid
                $designateUuid = RealSharePosterDesignateUuidModel::getRecord(['activity_id' => $activityId, 'uuid' => $studentInfo['uuid'] ?? '']);
                if (empty($designateUuid)) {
                    throw new RunTimeException(['student_status_disable']);
                }
            }
            // 检查周周领奖活动是否可以上传 - 未结束 或 已结束但没超过5天
            $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
            if (!RealSharePosterService::checkWeekActivityAllowUpload($activityInfo, $time)) {
                throw new RunTimeException(['wait_for_next_event']);
            }
            // 校验用户是否能参与活动
            OperationActivityModel::checkWeekActivityCountryCode($studentInfo, $activityInfo, Constants::REAL_APP_ID);
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
            'task_num' => $taskNum,
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
        QueueService::checkPoster(['id' => $res, 'app_id' => Constants::REAL_APP_ID, 'activity_type'=>AutoCheckPicture::SHARE_POSTER_TYPE_REAL_WEEK]);
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
        $tabData = [
            'month_tab' =>[
                'title' => '月月有奖',
                'aw_type' => 'month'
            ],
            'week_tab' => [
                'title' => '限时领奖',
                'aw_type' => 'week'
            ]
        ];
        $weekTab = self::xyzopWeekActivityTabShowList($studentData);
        if (!empty($weekTab)) {
            $tabData['week_tab'] = $weekTab;
        }
        return $tabData;
    }

    /**
     * 获取活动海报列表
     * @param $activityData
     * @param $fromType
     * @param $studentDetail
     * @return array
     * @throws RunTimeException
     */
    private static function getWeekActivityPosterList($activityData, $fromType, $studentDetail): array
    {
        if (empty($activityData) || empty($activityData['activity_id'])) {
            return [];
        }
        //海报定位参数配置
        $posterConfig = PosterService::getPosterConfig();
        //查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityData);
        if (empty($posterList)) {
            return [];
        }
        //获取渠道ID配置
        $channel = PosterTemplateService::getChannel(OperationActivityModel::TYPE_WEEK_ACTIVITY, $fromType);
        $typeColumn = [];
        $activityPosterIdColumn = [];
        foreach ($posterList as $item) {
            $typeColumn[] = $item['type'];
            $activityPosterIdColumn[] = $item['activity_poster_id'];
        }
        unset($item);
        //海报排序处理
        if (isset($activityData['poster_order']) && ($activityData['poster_order'] == TemplatePosterModel::POSTER_ORDER)) {
            array_multisort($typeColumn, SORT_DESC, $activityPosterIdColumn, SORT_ASC, $posterList);
        }
        // 组合生成海报数据
        $userQrArr= [];
		$checkActiveId = (int)PosterService::getCheckActivityId(Constants::REAL_APP_ID, $studentDetail['id']);
        foreach ($posterList as $item) {
            $_tmp['user_status'] = $studentDetail['status'];
            $_tmp['activity_id'] = $activityData['activity_id'];
            $_tmp['check_active_id'] = $checkActiveId;
            $_tmp['poster_id'] = $item['poster_id'];
            $_tmp['user_id'] = $studentDetail['id'];
            $_tmp['user_type'] = DssUserQrTicketModel::STUDENT_TYPE;
            $_tmp['channel_id'] = $channel;
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $_tmp['date'] = date('Y-m-d', time());
            $userQrArr[] = $_tmp;
        }
        // 获取小程序码
        $userQrArr = MiniAppQrService::batchCreateUserMiniAppQr(Constants::REAL_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $userQrArr, true);
        unset($item);

        // 获取AB测海报，和对照组海报id
        list($abPosterIsNormal, $abTestPosterInfo) = RealWeekActivityService::getStudentTestAbPoster($studentDetail['id'], $activityData['activity_id'], [
            'channel_id'      => $channel,
            'user_type'       => DssUserQrTicketModel::STUDENT_TYPE,
            'landing_type'    => DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
            'user_status'     => $studentDetail['status'],
            'is_create_qr_id' => true,
        ]);
        // 获取海报， 标准海报：后台生成带有二维码的海报地址， 个性海报：后台只打防伪码
        $firstStandardPoster = true;
        foreach ($posterList as $key => &$item) {
            // 如果是对照组标准海报，不用重新生成海报二维码
            // 开启了ab测，测试海报不正常删除， 正常替换
            if ($activityData['has_ab_test'] == OperationActivityModel::HAS_AB_TEST_YES && $item['type'] == TemplatePosterModel::STANDARD_POSTER && $firstStandardPoster) {
                if (!$abPosterIsNormal) {
                    unset($posterList[$key]);
                } else {
                    $item = $abTestPosterInfo;
                }
                $firstStandardPoster = false;
                continue;
            }
            $extParams = [
                'user_status' => $studentDetail['status'],
                'activity_id' => $activityData['activity_id'],
                'poster_id' => $item['poster_id'],
            ];
            $item = PosterTemplateService::formatPosterInfo($item);
            //个性化海报只需获取二维码，不用合成海报
            if ($item['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $item['qr_code_url'] = $userQrArr[$key]['format_qr_path'];
                $word = [
                    'qr_id' => $userQrArr[$key]['qr_id'],
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
                $userQrArr[$key] ?? []
            );
            $item['poster_url'] = $poster['poster_save_full_path'];
        }
        unset($key, $item);
        // 获取活动规则
        $activityData['award_rule'] = ActivityExtModel::getActivityExt($activityData['activity_id'])['award_rule'] ?? '';
        return [array_values($posterList), ActivityService::formatData($activityData)];
    }
}
