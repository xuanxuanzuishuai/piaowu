<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Libs\Util;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\PosterModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\WeChatAwardCashDealModel;

class ReferralService
{
    const EXPECT_REGISTER          = 1; //注册
    const EXPECT_TRAIL_PAY         = 2; //付费体验卡
    const EXPECT_YEAR_PAY          = 3; //付费年卡
    const EXPECT_FIRST_NORMAL      = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    /**
     * 推荐学员列表
     * @param $params
     * @return array
     */
    public static function getReferralList($params)
    {
        $listData = [
            'total_count' => 0,
            'list' => [],
        ];
        $whereData = self::formatReferralListWhere($params);
        if (empty($whereData)) {
            return $listData;
        }
        //查询数据
        $count = StudentReferralStudentStatisticsModel::getCount($whereData);
        if (empty($count)) {
            return $listData;
        }
        $listData['total_count'] = $count;
        $whereData['ORDER'] = ["id" => "DESC"];
        $whereData['LIMIT'] = [($params['page'] - 1) * $params['count'], $params['count']];
        $listData['list'] = StudentReferralStudentStatisticsModel::getRecords(
            $whereData,
            [
                'id[Int]',
                'student_id[Int]',
                'last_stage[Int]',
                'referee_id[Int]',
                'referee_employee_id[Int]',
                'activity_id[Int]',
                'create_time'
            ]);
        return self::formatStudentInvite($listData);
    }

    /**
     * 格式化推荐学员列表数据
     * @param $listData
     * @return mixed
     */
    public static function formatStudentInvite($listData)
    {
        $studentIds = array_column($listData['list'], 'student_id');
        $refereeStudentIds = array_column($listData['list'], 'referee_id');
        //批量获取学生信息
        $studentDetail = array_column(DssStudentModel::getRecords(
            [
                'id' => array_unique(array_merge($studentIds, $refereeStudentIds))
            ],
            ['id', 'name', 'uuid', 'mobile', 'create_time', 'channel_id[Int]']), null, 'id');
        //进度
        $dictData = DictConstants::getSet(DictConstants::AGENT_USER_STAGE);
        //批量获取渠道信息
        $channelData = array_column(DssChannelModel::getRecords(['id' => array_unique(array_column($studentDetail, 'channel_id'))], ['name', 'id']), null, 'id');
        //批量获取员工信息
        $employeeData = array_column(DssEmployeeModel::getRecords(['id' => array_unique(array_column($listData['list'], 'referee_employee_id'))], ['name', 'id']), null, 'id');
        //批量获取活动信息
        $activityData = array_column(OperationActivityModel::getRecords(['id' => array_unique(array_column($listData['list'], 'activity_id'))], ['name', 'id']), null, 'id');
        foreach ($listData['list'] as $lk => &$lv) {
            $lv['student_name'] = $studentDetail[$lv['student_id']]['name'];
            $lv['student_uuid'] = $studentDetail[$lv['student_id']]['uuid'];
            $lv['student_mobile_hidden'] = $studentDetail[$lv['student_id']]['mobile'];
            $lv['register_time'] = $studentDetail[$lv['student_id']]['create_time'];
            $lv['channel_name'] = $channelData[$studentDetail[$lv['student_id']]['channel_id']]['name'];
            $lv['last_stage_show'] = $dictData[$lv['last_stage']];

            $lv['referrer_mobile_hidden'] = $studentDetail[$lv['referee_id']]['mobile'];
            $lv['referrer_uuid'] = $studentDetail[$lv['referee_id']]['uuid'];
            $lv['referrer_name'] = $studentDetail[$lv['referee_id']]['name'];

            $lv['employee_name'] = !empty($lv['referee_employee_id']) ? $employeeData[$lv['referee_employee_id']]['name'] : '';
            $lv['activity_name'] = !empty($lv['activity_id']) ? $activityData[$lv['activity_id']]['name'] : '';
        }
        return $listData;
    }


    /**
     * 格式化推荐学员搜索条件
     * @param $params
     * @return array
     */
    private static function formatReferralListWhere($params)
    {
        //推荐时间
        $statisticsWhere['id[>]'] = 0;
        if (!empty($params['referral_s_create_time'])) {
            $statisticsWhere['create_time[>=]'] = $params['referral_s_create_time'];
        }
        if (!empty($params['referral_e_create_time'])) {
            $statisticsWhere['create_time[<=]'] = $params['referral_e_create_time'];
        }
        //当前进度
        if (is_numeric($params['last_stage']) && isset($params['last_stage'])) {
            $statisticsWhere['last_stage'] = $params['last_stage'];
        }

        //员工
        if (!empty($params['employee_name'])) {
            $employeeData = DssEmployeeModel::getRecords(['name[~]' => $params['employee_name']], ['id[Int]']);
            if (empty($employeeData)) {
                return [];
            }
            $statisticsWhere['referee_employee_id'] = array_column($employeeData, 'id');
        }
        //活动
        if (!empty($params['activity'])) {
            $activityData = OperationActivityModel::getRecords(['name[~]' => $params['activity']], ['id[Int]']);
            if (empty($activityData)) {
                return [];
            }
            $statisticsWhere['activity_id'] = array_column($activityData, 'id');
        }
        //推荐人手机号
        $referralStudentIds = [];
        if (!empty($params['referral_mobile'])) {
            $referralMobileData = DssStudentModel::getRecord(['mobile[~]' => $params['referral_mobile']], ['id[Int]']);
            if (empty($referralMobileData)) {
                return [];
            }
            $referralStudentIds = array_column($referralMobileData, 'id');
        }
        //推荐人uuid
        if (!empty($params['referral_uuid'])) {
            $referralUuidData = DssStudentModel::getRecord(['uuid' => $params['referral_uuid']], ['id[Int]']);
            if (empty($referralUuidData)) {
                return [];
            }
            $referralStudentIds = array_merge($referralStudentIds, array_column($referralUuidData, 'id'));
        }
        if (!empty($referralStudentIds)) {
            $statisticsWhere['referee_id'] = $referralStudentIds;
        }

        //学员手机号
        $studentIds = [];
        if (!empty($params['mobile'])) {
            $mobileData = DssStudentModel::getRecord(['mobile[~]' => $params['mobile']], ['id[Int]']);
            if (empty($mobileData)) {
                return [];
            }
            $studentIds = array_column($mobileData, 'id');
        }
        //学员uuid
        if (!empty($params['uuid'])) {
            $uuidData = DssStudentModel::getRecord(['uuid' => $params['uuid']], ['id[Int]']);
            if (empty($uuidData)) {
                return [];
            }
            $studentIds = array_merge($studentIds, array_column($uuidData, 'id'));
        }
        //学员注册渠道
        if (!empty($params['channel_id'])) {
            $channelData = DssStudentModel::getRecord(['channel_id' => $params['channel_id']], ['id[Int]']);
            if (empty($channelData)) {
                return [];
            }
            $studentIds = array_merge($studentIds, array_column($channelData, 'id'));
        }

        //学员注册时间
        $registerStudentIds = [];
        if (!empty($params['register_s_create_time'])) {
            $registerStartData = StudentReferralStudentDetailModel::getRecords(['create_time[>=]' => $params['register_s_create_time'], 'stage' => StudentReferralStudentStatisticsModel::STAGE_REGISTER], ['student_id[Int]']);
            if (empty($registerStartData)) {
                return [];
            }
            $registerStudentIds = array_merge($registerStudentIds, array_column($registerStartData, 'student_id'));
        }
        if (!empty($params['register_e_create_time'])) {
            $registerEndData = StudentReferralStudentDetailModel::getRecords(['create_time[<=]' => $params['register_e_create_time'], 'stage' => StudentReferralStudentStatisticsModel::STAGE_REGISTER], ['student_id[Int]']);
            if (empty($registerEndData)) {
                return [];
            }
            $registerStudentIds = array_merge($registerStudentIds, array_column($registerEndData, 'student_id'));
        }
        if (!empty($studentIds) || !empty($registerStudentIds)) {
            $statisticsWhere['student_id'] = array_unique(array_merge($studentIds, $registerStudentIds));
        }
        return $statisticsWhere;
    }

    /**
     * 根据用户完成的任务得到进度
     * @param $taskId
     * @return string
     */
    public static function getStageByMaxTaskIds($taskId, $taskNodeDict, $nodeNameDict)
    {
        if (empty($taskId)) {
            return '';
        }
        // 得到最大任务id
        // 根据任务得到节点
        // 返回节点名称
        $node = $taskNodeDict[$taskId] ?? 0;
        return $nodeNameDict[$node] ?? '';
    }

    /**
     * 获取转介绍节点名称字典
     * node_id => name
     * @return array
     */
    public static function getReferralNodeNameDict()
    {
        return DictConstants::getSet(DictConstants::REFEREE_CASH_NODE);
    }

    /**
     * 获取转介绍任务节点字典
     * task_id => node_id
     * @return array
     */
    public static function getReferralTaskNodeDict()
    {
        $list = [self::EXPECT_REGISTER, self::EXPECT_TRAIL_PAY, self::EXPECT_YEAR_PAY];
        $taskNodeDict = [];
        foreach ($list as $code) {
            $l = explode(',', DictConstants::get(DictConstants::NODE_RELATE_TASK, $code));
            foreach ($l as $taskId) {
                $taskNodeDict[$taskId] = $code;
            }
        }
        return $taskNodeDict;
    }

    /**
     * 某个学生用户的学生推荐人信息
     * @param $appId
     * @param $studentId
     * @return mixed
     */
    public static function getReferralInfo($appId, $studentId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return StudentReferralStudentStatisticsModel::getRecord(
                ['student_id' => $studentId]
            );
        }
        return NULL;
    }

    /**
     * @param $appId
     * @param $studentId
     * @return mixed
     * 获取当前这个学生推荐的所有学生
     */
    public static function getRefereeAllUser($appId, $studentId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return StudentReferralStudentStatisticsModel::getRecords(
                ['referee_id' => $studentId]
            );
        }
        return NULL;
    }

    /**
     * 获取推送消息文案和图片
     * @param $day
     * @param $studentInfo
     * @return array
     */
    public static function getCheckinSendData($day, $studentInfo)
    {
        $configData = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'day_'.$day);
        if (empty($configData)) {
            SimpleLogger::error('EMPTY CONFIG', [$day]);
            return [];
        }
        $pageUrl = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'page_url');
        $configData = json_decode($configData, true);
        $content1 = Util::textDecode($configData['content1'] ?? '');
        $content1 = str_replace('{page_url}', $pageUrl, $content1);
        $content2 = Util::textDecode($configData['content2'] ?? '');
        $posterData = self::getPosterByDay($day);
        $posterImage = self::genCheckinPoster($posterData, $day, $studentInfo);
        return [$content1, $content2, $posterImage];
    }

    /**
     * 根据第几天节点获取海报
     * @param $day
     * @return array|mixed
     */
    public static function getPosterByDay($day)
    {
        $configData = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'day_poster_config');
        $configData = json_decode($configData, true);
        $id = $configData[$day] ?? 0;
        if (empty($id)) {
            return [];
        }
        return PosterModel::getRecord(['id' => $id]);
    }

    /**
     * 生成学生打卡海报
     * @param $posterInfo
     * @param $day
     * @param $studentInfo
     * @return array
     */
    public static function genCheckinPoster($posterInfo, $day, $studentInfo)
    {
        if (empty($posterInfo)) {
            return [];
        }
        if (empty($day)) {
            return ['poster_save_full_path' => AliOSS::signUrls($posterInfo['path']), 'unique' => md5($studentInfo['id'] . $day . $posterInfo['path']) . ".jpg"];
        }
        $headImageUrl = $studentInfo['wechat']['headimgurl'];
        $name = self::getPosterName($studentInfo['wechat']['nickname']);
        $studentInfo['duration_sum'] = Util::formatDuration($studentInfo['duration_sum']);
        $percent = SharePosterModel::getUserCheckInPercent($studentInfo['collection_id'], $studentInfo['id'], $day, $studentInfo['duration_sum']);
        $posterConfig = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'poster_config');
        if (!empty($posterConfig)) {
            $posterConfig = json_decode($posterConfig, true);
        }

        $fileName = md5($headImageUrl);
        $thumb = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_REFERRAL . '/'. $fileName. '.jpg';
        if (!AliOSS::doesObjectExist($thumb)) {
            $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/" . $fileName . ".jpg";
            chmod($tmpFileFullPath, 0755);
            file_put_contents($tmpFileFullPath, file_get_contents($headImageUrl));
            if (file_exists($tmpFileFullPath)) {
                AliOSS::uploadFile($thumb, $tmpFileFullPath);
            }
            unlink($tmpFileFullPath);
        }
        $userQrPath  = DssUserQrTicketModel::getUserQrURL(
            $studentInfo['id'],
            DssUserQrTicketModel::STUDENT_TYPE,
            self::getChannelByDay($day),
            DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
            ['p' => $posterInfo['id']]
        );
        $waterImgEncode = str_replace(["+", "/"], ["-", "_"], base64_encode($thumb."?x-oss-process=image/resize,w_90,h_90/circle,r_100/format,png"));
        $waterMark = [];
        $waterMark[] = [
            "image_" . $waterImgEncode,
            "x_" . $posterConfig['thumb_x'],
            "y_" . $posterConfig['thumb_y'],
            "g_nw",
        ];
        $waterMark[] = [
            "image_" . str_replace(["+", "/"], ["-", "_"], base64_encode($userQrPath."?x-oss-process=image/resize,w_".$posterConfig['qr_w'].",h_".$posterConfig['qr_h'])),
            "x_" . $posterConfig['qr_x'],
            "y_" . $posterConfig['qr_y'],
            "g_nw",
        ];
        $waterMark[] = self::getTextWaterMark($name, ['x' => $posterConfig['name_x'], 'y' => $posterConfig['name_y'], 'g' => 'nw', 's' => 30]);
        $waterMark[] = self::getTextWaterMark($studentInfo['lesson_count'], self::getTextConfig($studentInfo['lesson_count'], 'lesson'));
        $waterMark[] = self::getTextWaterMark($studentInfo['duration_sum'], self::getTextConfig($studentInfo['duration_sum'], 'duration'));
        $waterMark[] = self::getTextWaterMark($percent.'%', self::getTextConfig($percent, 'percent'));
        $waterMark[] = self::getTextWaterMark('分钟', self::getTextConfig($studentInfo['duration_sum'], 'minute'));
        $waterMark[] = self::getTextWaterMark('首', self::getTextConfig($studentInfo['lesson_count'], 'qu'));

        $waterMarkStr = [];
        foreach ($waterMark as $wm) {
            $waterMarkStr[] = implode(",", $wm);
        }
        $imgSize = [
            "w_" . $posterConfig['width'],
            "h_" . $posterConfig['height'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';
        $resImgFile = AliOSS::signUrls($posterInfo['path'], "", "", "", false, $waterMarkStr, $imgSizeStr);
        return ['poster_save_full_path' => $resImgFile, 'unique' => md5($studentInfo['id'] . $day . $posterInfo['path']) . ".jpg"];
    }

    /**
     * 获取文字水印
     * @param $string
     * @param $config
     * @return string[]
     */
    public static function getTextWaterMark($string, $config)
    {
        $r = [
            "text_" . str_replace(["+", "/"], ["-", "_"], base64_encode($string)),
            "type_d3F5LW1pY3JvaGVp", // 文泉微米黑
            "x_" . $config['x'],
            "y_" . $config['y'],
            "g_" . $config['g']
        ];
        if (!empty($config['s'])) {
            $r[] = "size_" . $config['s'];
        }
        return $r;
    }

    /**
     * 获取海报渠道
     * @param $day
     * @return string
     */
    public static function getChannelByDay($day)
    {
        $config = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'day_channel');
        $config = json_decode($config, true);
        return $config[$day] ?? '';
    }

    /**
     * 获取文字水印参数
     * @param $string
     * @param $configKey
     * @return array
     */
    public static function getTextConfig($string, $configKey)
    {
        $len = strlen($string);
        if (empty($len) || $len > 3) {
            $len = 3;
        }
        $config = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'text_position');
        $configArray = json_decode($config, true);
        if (empty($configArray)) {
            SimpleLogger::error("EMPTY TEXT POSITION CONFIG", [$config]);
        }
        return array_merge(['g' => 'nw'], $configArray[$len][$configKey]);
    }

    /**
     * 生成获取学生打卡文案所需的数据
     * @param $studentId
     * @return array|null
     */
    public static function getUserInfoForSendData($studentId, $nodeDate)
    {
        if (empty($studentId)) {
            return [];
        }
        $sendData = DssStudentModel::getRecord(['id' => $studentId], ['id', 'collection_id', 'thumb', 'name']);
        if (empty($sendData)) {
            return [];
        }
        $wechatInfo = DssUserWeiXinModel::getRecord(
            [
                'user_id'   => $studentId,
                'status'    => DssUserWeiXinModel::STATUS_NORMAL,
                'app_id'    => Constants::SMART_APP_ID,
                'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
                'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT
            ]
        );
        $sendData['open_id'] = $wechatInfo['open_id'] ?? '';
        $collectionInfo = DssCollectionModel::getRecord(['id' => $sendData['collection_id']]);
        $sendData['teaching_start_time'] = $collectionInfo['teaching_start_time'] ?? 0;
        $today = new \DateTime(date('Y-m-d', $nodeDate));
        $startDay = new \DateTime(date('Y-m-d', $sendData['teaching_start_time']));
        $dayDiff = $today->diff($startDay)->format('%a');
        if ($dayDiff <0 || $dayDiff > 5) {
            SimpleLogger::error("WRONG DAY DATA", [$sendData]);
            return [];
        }
        $day = date("Y-m-d", strtotime("-1 days", $today->getTimestamp()));
        $playInfo = DssAiPlayRecordCHModel::getStudentBetweenTimePlayRecord(intval($studentId), strtotime($day), strtotime($day.' 23:59:59'));
        $sd = array_sum(array_column($playInfo, 'sum_duration'));
        $lc = count(array_unique(array_column($playInfo, 'lesson_id')));
        $sendData['lesson_count'] = $lc;
        $sendData['duration_sum'] = $sd ?? 0;
        $sendData['wechat'] = self::getWechatInfoForPush($sendData);
        $sendData['day'] = $dayDiff;
        return $sendData;
    }

    /**
     * 获取推送用户信息
     * @param $studentInfo
     * @return array
     */
    public static function getWechatInfoForPush($studentInfo)
    {
        if (!empty($studentInfo['thumb'])) {
            return [
                'nickname'   => $studentInfo['name'],
                'headimgurl' => AliOSS::replaceCdnDomainForDss($studentInfo['thumb'])
            ];
        }
        $defaultData = [
            'nickname'   => '小叶子',
            'headimgurl' => AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'))
        ];
        $wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);
        $data = $wechat->getUserInfo($studentInfo['open_id']);
        if (empty($data['headimgurl'])) {
            $data = $defaultData;
        }
        $data['nickname'] = $data['nickname'].'的宝贝';
        return $data;
    }

    /**
     * 根据分数生成排行百分比
     * @param $duration
     * @return int
     */
    public static function getRandScore($duration)
    {
        if ($duration < 10) {
            return rand(60, 70);
        }
        if ($duration < 30) {
            return rand(70, 80);
        }
        return rand(80, 99);
    }

    /**
     * 格式化海报名字长度
     * @param $name
     * @return string
     */
    public static function getPosterName($name)
    {
        $len = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'max_name_length');
        if (strlen($name) > $len) {
            return mb_substr($name, 0, 4) . '...' . mb_substr($name, -4);
        }
        return $name;
    }

    /**
     * 获取不展示任务节点
     * @return false|string[]
     */
    public static function getNotDisplayWaitGiveTask()
    {
        $value = DictConstants::get(DictConstants::NODE_SETTING, 'not_display_wait');
        return explode(',', $value);
    }

    /**
     * 获取展示节点
     * @param $source
     * @return array
     */
    public static function getAwardNode($source)
    {
        $awardNodeArr = [
            'referee' => DictConstants::COMMON_CASH_NODE,
            'reissue_award' => DictConstants::REISSUE_CASH_NODE
        ];
        $type = empty($awardNodeArr[$source]) ? DictConstants::REFEREE_CASH_NODE : $awardNodeArr[$source];
        return DictConstants::getSet($type);
    }

    /**
     * 领取人微信信息
     * @param $userEventTaskAwardId
     * @return array|false|mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getReceiveInfo($userEventTaskAwardId)
    {
        $data = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $userEventTaskAwardId]);
        $openId = $data['open_id'] ?? '';
        $wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);
        $wxInfo = $openId ? $wechat->getUserInfo($openId) : [];
        return $wxInfo;
    }

    /**
     * 我邀请的学员列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function myInviteStudentList($params, $page, $count)
    {
        $returnList = [
            'invite_total_num' => 6,
            'invite_student_list' => [],
        ];
        // 获取用户信息
        $studentInfo = DssStudentModel::getRecord(['uuid' => $params['referrer_uuid']], ['id']);
        $where = ['referee_id' => $studentInfo['id']];
        $returnList['invite_total_num'] = StudentReferralStudentStatisticsModel::getCount($where);
        if ($returnList['invite_total_num'] <= 0) {
            return $returnList;
        }

        $where['LIMIT'] = [($page - 1) * $count, $count];
        $where['ORDER'] = ['id' => 'DESC'];
        // 获取邀请学生id列表
        $list = StudentReferralStudentStatisticsModel::getRecords($where);
        $inviteStudentId = array_column($list,'student_id');
        // 获取所有学生信息
        $inviteStudentList = DssStudentModel::getRecords(['id' => $inviteStudentId], ['name', 'mobile', 'thumb']);
        $inviteStudentArr = [];
        if (is_array($inviteStudentList)) {
            foreach ($inviteStudentList as $_item){
                $inviteStudentArr['id'] = $_item;
            }
        }
        // 获取学生节点名称
        $stageNameList = DictConstants::getSet(DictConstants::AGENT_USER_STAGE);
        // 获取学生节点
        $studentStageList = StudentReferralStudentDetailModel::getRecords(['student_id' => $inviteStudentId]);
        $studentStageArr = [];
        foreach ($studentStageList as $item) {
            $studentStageArr[$item['student_id']][$item['stage']+1] = [
                'stage_name' => $stageNameList[$item['stage']] ?? '',
                'create_time' => date("Y-m-d", $item['create_time']),
                'stage' => $item['stage']
            ];
        }

        foreach ($list as $_invite) {
            $s_info = $inviteStudentArr[$_invite['student_id']] ?? [];
            $returnList['invite_student_list'][] = [
                'mobile' => isset($s_info['mobile']) ? Util::hideUserMobile($s_info['mobile']) : '',
                'name' => isset($s_info['name']) ? $s_info['name'] : '',
                'thumb' => isset($s_info['thumb']) ? AliOSS::signUrls($s_info['thumb']) : '',
                'stage' => $studentStageArr[$_invite['student_id']] ?? [],
            ];
        }

        return $returnList;
    }
}
