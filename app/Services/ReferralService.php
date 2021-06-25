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
use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RC4;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\WeChat\WXBizDataCrypt;
use App\Models\BillMapModel;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Libs\Util;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\OperationActivityModel;
use App\Models\ParamMapModel;
use App\Models\SharePosterModel;
use App\Models\PosterModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\WeChatAwardCashDealModel;
use App\Services\Queue\QueueService;
use App\Services\Queue\SaveTicketTopic;

class ReferralService
{
    const EXPECT_REGISTER = 1; //注册
    const EXPECT_TRAIL_PAY = 2; //付费体验卡
    const EXPECT_YEAR_PAY = 3; //付费年卡
    const EXPECT_FIRST_NORMAL = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    const PURCHASED_STATUS_NONE = 0; //未购买
    const PURCHASED_STATUS_IN_24 = 1; //已购买未超过24小时
    const PURCHASED_STATUS_OUT_24 = 2; //已购买已超过24小时

    const BUY_NAME_CACHE_KEY = 'zero_order_buy_name'; //0元订单 缓存用户名称

    // 转介绍小程序
    const REFERRAL_MINI_APP_ID = 2;


    //用户类型
    const STUDENT_TYPE_REGISTERED = 0; //注册用户
    const STUDENT_TYPE_GIFT_CODE = 1; //赠送(激活码)用户
    const STUDENT_TYPE_TRIAL = 2; //体验用户(9.9, 49.9)
    const STUDENT_TYPE_YEAR_CARD = 3; //年卡用户

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
                'buy_channel[Int]',
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
        $channelData = array_column(DssChannelModel::getRecords([
            'id' => array_unique(array_column($studentDetail, 'channel_id'))
        ], ['name', 'id']), null, 'id');
        //批量获取员工信息
        $employeeData = array_column(DssEmployeeModel::getRecords([
            'id' => array_unique(array_column($listData['list'], 'referee_employee_id'))
        ], ['name', 'id']), null, 'id');
        //批量获取活动信息
        $activityData = array_column(OperationActivityModel::getRecords([
            'id' => array_unique(array_column($listData['list'], 'activity_id'))
        ], ['name', 'id']), null, 'id');

        foreach ($listData['list'] as $lk => &$lv) {
            $lv['student_name'] = $studentDetail[$lv['student_id']]['name'];
            $lv['student_uuid'] = $studentDetail[$lv['student_id']]['uuid'];
            $lv['student_mobile_hidden'] = Util::hideUserMobile($studentDetail[$lv['student_id']]['mobile']);
            $lv['register_time'] = $studentDetail[$lv['student_id']]['create_time'];
            $lv['channel_name'] = $channelData['buy_channel']['name'] ?? '';
            $lv['last_stage_show'] = $dictData[$lv['last_stage']];

            $lv['referrer_mobile_hidden'] = Util::hideUserMobile($studentDetail[$lv['referee_id']]['mobile']);
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
        if (isset($params['last_stage']) && is_numeric($params['last_stage'])) {
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
        $referralStudentWhere = [];
        if (!empty($params['referral_mobile'])) {
            $referralStudentWhere['mobile'] = $params['referral_mobile'];
        }
        //推荐人uuid
        if (!empty($params['referral_uuid'])) {
            $referralStudentWhere['uuid'] = $params['referral_uuid'];
        }
        if (!empty($referralStudentWhere)) {
            $referralStudentList = DssStudentModel::getRecords($referralStudentWhere,['id[Int]']);
            if (!empty($referralStudentList)) {
                $statisticsWhere['referee_id'] = array_column($referralStudentList,'id');
            }else {
                $statisticsWhere['referee_id'] = 0;
            }
        }

        //学员手机号
        $studentWhere = $studentIds = [];
        if (!empty($params['mobile'])) {
            $studentWhere['mobile'] = $params['mobile'];
        }
        //学员uuid
        if (!empty($params['student_uuid'])) {
            $studentWhere['uuid'][] = $params['student_uuid'];
        }
        //学员注册渠道
        if (!empty($params['channel_id'])) {
            $studentWhere['channel_id'] = (int)$params['channel_id'];
        }
        if (!empty($studentWhere)) {
            $studentIds = array_column(DssStudentModel::getRecords($studentWhere, ['id[Int]']), 'id');
            if (empty($studentIds)) {
                return [];
            }
        }
        //学员注册时间
        $registerStudentWhere = $registerStudentIds = [];
        if (!empty($params['register_s_create_time'])) {
            $registerStudentWhere['create_time[>=]'] = $params['register_s_create_time'];
        }
        if (!empty($params['register_e_create_time'])) {
            $registerStudentWhere['create_time[<=]'] = $params['register_e_create_time'];
        }
        if (!empty($registerStudentWhere)) {
            $registerStudentWhere['stage'] = StudentReferralStudentStatisticsModel::STAGE_REGISTER;
            $registerStudentIds = array_column(StudentReferralStudentDetailModel::getRecords($registerStudentWhere, ['student_id[Int]']), 'student_id');
            if (empty($registerStudentIds)) {
                return [];
            }
            if (empty($studentIds)) {
                $studentIds = $registerStudentIds;
            } else {
                $studentIds = array_intersect($studentIds, $registerStudentIds);
            }
        }
        //搜索条件
        if (!empty($studentIds)) {
            $statisticsWhere['student_id'] = $studentIds;
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
     * @param $parentBillId
     * @return mixed
     */
    public static function getReferralInfo($appId, $studentId, $parentBillId = '')
    {
        if ($appId == Constants::SMART_APP_ID) {
            $referralData = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $studentId], ['referee_id']);
            if (!empty($referralData)) {
                return $referralData;
            }
            if (empty($parentBillId)) {
                return [];
            } else {
                //支付成功消息队列处理不及时，直接通过订单ID映射关系来检查推荐人信息
                $bindReferralRelationCondition = StudentReferralStudentService::checkBindReferralCondition($studentId);
                if (empty($bindReferralRelationCondition)) {
                    return [];
                } else {
                    //通过订单ID获取成单人的映射关系
                    $mapData = BillMapModel::paramMapDataByBillId($parentBillId, $studentId);
                    return ['referee_id' => (int)$mapData['user_id']];
                }
            }
        }
        return null;
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
        return null;
    }

    /**
     * 获取推送消息文案和图片
     * @param $day
     * @param $studentInfo
     * @return array
     */
    public static function getCheckinSendData($day, $studentInfo)
    {
        $configData = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'day_' . $day);
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
            return [
                'poster_save_full_path' => AliOSS::signUrls($posterInfo['path']),
                'unique' => md5($studentInfo['id'] . $day . $posterInfo['path']) . ".jpg"
            ];
        }
        $headImageUrl = $studentInfo['wechat']['headimgurl'];
        $name = self::getPosterName($studentInfo['wechat']['nickname']);
        $studentInfo['duration_sum'] = Util::formatDuration($studentInfo['duration_sum']);
        $percent = SharePosterModel::getUserCheckInPercent($studentInfo['collection_id'], $studentInfo['id'], $day,
            $studentInfo['duration_sum']);
        $posterConfig = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'poster_config');
        if (!empty($posterConfig)) {
            $posterConfig = json_decode($posterConfig, true);
        }

        $fileName = md5($headImageUrl);
        $thumb = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName . '.jpg';
        if (!AliOSS::doesObjectExist($thumb)) {
            $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/" . $fileName . ".jpg";
            chmod($tmpFileFullPath, 0755);
            file_put_contents($tmpFileFullPath, file_get_contents($headImageUrl));
            if (file_exists($tmpFileFullPath)) {
                AliOSS::uploadFile($thumb, $tmpFileFullPath);
            }
            unlink($tmpFileFullPath);
        }
        $userQrPath = DssUserQrTicketModel::getUserQrURL(
            $studentInfo['id'],
            DssUserQrTicketModel::STUDENT_TYPE,
            self::getChannelByDay($day),
            DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
            ['p' => $posterInfo['id']]
        );
        $waterImgEncode = str_replace(["+", "/"], ["-", "_"],
            base64_encode($thumb . "?x-oss-process=image/resize,w_90,h_90/circle,r_100/format,png"));
        $waterMark = [];
        $waterMark[] = [
            "image_" . $waterImgEncode,
            "x_" . $posterConfig['thumb_x'],
            "y_" . $posterConfig['thumb_y'],
            "g_nw",
        ];
        $waterMark[] = [
            "image_" . str_replace(["+", "/"], ["-", "_"],
                base64_encode($userQrPath . "?x-oss-process=image/resize,w_" . $posterConfig['qr_w'] . ",h_" . $posterConfig['qr_h'])),
            "x_" . $posterConfig['qr_x'],
            "y_" . $posterConfig['qr_y'],
            "g_nw",
        ];
        $waterMark[] = self::getTextWaterMark($name,
            ['x' => $posterConfig['name_x'], 'y' => $posterConfig['name_y'], 'g' => 'nw', 's' => 30]);
        $waterMark[] = self::getTextWaterMark($studentInfo['lesson_count'],
            self::getTextConfig($studentInfo['lesson_count'], 'lesson'));
        $waterMark[] = self::getTextWaterMark($studentInfo['duration_sum'],
            self::getTextConfig($studentInfo['duration_sum'], 'duration'));
        $waterMark[] = self::getTextWaterMark($percent . '%', self::getTextConfig($percent, 'percent'));
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
        return [
            'poster_save_full_path' => $resImgFile,
            'unique' => md5($studentInfo['id'] . $day . $posterInfo['path']) . ".jpg"
        ];
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
                'user_id' => $studentId,
                'status' => DssUserWeiXinModel::STATUS_NORMAL,
                'app_id' => Constants::SMART_APP_ID,
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
        if ($dayDiff < 0 || $dayDiff > 5) {
            SimpleLogger::error("WRONG DAY DATA", [$sendData]);
            return [];
        }
        $day = date("Y-m-d", strtotime("-1 days", $today->getTimestamp()));
        $playInfo = DssAiPlayRecordCHModel::getStudentBetweenTimePlayRecord(intval($studentId), strtotime($day),
            strtotime($day . ' 23:59:59'));
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
                'nickname' => $studentInfo['name'],
                'headimgurl' => AliOSS::replaceCdnDomainForDss($studentInfo['thumb'])
            ];
        }
        $defaultData = [
            'nickname' => '小叶子',
            'headimgurl' => AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO,
                'default_thumb'))
        ];
        $wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);
        $data = $wechat->getUserInfo($studentInfo['open_id']);
        if (empty($data['headimgurl'])) {
            $data = $defaultData;
        }
        $data['nickname'] = $data['nickname'] . '的宝贝';
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
            'invite_total_num' => 0,
            'invite_student_list' => [],
        ];

        // 获取用户信息
        $studentInfo = DssStudentModel::getRecord(['uuid' => $params['referrer_uuid']], ['id']);

        $where = ['referee_id' => $studentInfo['id']];
        // 是我的奖金页面的邀请名单，只读取到发放积分开始的日期
        if ($params['award_type'] == ErpEventTaskModel::AWARD_TYPE_CASH) {
            // 获取开始发放积分的时间节点
            $stopTime = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'student_invite_send_points_start_time');
            $where['create_time[<]'] = $stopTime;
        }
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
        foreach ($studentStageList as $item) {
            $studentStageArr[$item['student_id']][$item['stage'] + 1] = [
                'stage_name' => $stageNameList[$item['stage']] ?? '',
                'create_time' => date("Y-m-d", $item['create_time']),
                'unix_create_time' => $item['create_time'],
                'stage' => $item['stage']
            ];
        }

        foreach ($list as $_invite) {
            $s_info = $inviteStudentArr[$_invite['student_id']] ?? [];
            // 兼容2021.05.10之前用户注册就建立转介绍关系 - 会存在建立转介绍关系后先购买年卡再购买体验卡的情况;
            // 如果购买体验课的时间比购买年卡的时间大，不显示体验卡节点
            $stage = $studentStageArr[$_invite['student_id']] ?? [];
            if (isset($stage[3]) && isset($stage[2]) && $stage[3]['unix_create_time'] < $stage[2]['unix_create_time']) {
                unset($stage[2]);
            }

            // 更改绑定关系建立的时间
            if (isset($stage[1])) {
                $stage[1]['create_time'] = date("Y-m-d", $_invite['create_time']);
                $stage[1]['unix_create_time'] =$_invite['create_time'];
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


    /**
     * 0元 体验营
     * @param array $sceneData
     * @param string $openid
     * @return array
     */
    public static function getMiniAppIndexData(array $sceneData, string $openid): array
    {
        $data = [];
        $data['had_purchased'] = 0;
        $data['mobile'] = '';
        $data['openid'] = $openid;
        $data['uuid'] = '';
        $data['staff'] = [];
        $data['share_scene'] = '';
        $data['scene_data'] = '';
        $data['subscribe_status'] = null;
        $packageType = PayServices::PACKAGE_990;
        $isAgent = false;

        // 推荐人信息：
        if (!empty($sceneData['r'])) {
            $referrerInfo = DssUserQrTicketModel::getRecord(['qr_ticket' => $sceneData['r']], ['user_id']);
            $referrerUserId = $referrerInfo['user_id'] ?? '';
        } else {
            $referrerUserId = null;
        }

        // 转介绍注册时用的推荐人ticket参数
        $data['referrer_info']['uuid'] = '';
        $data['referrer_info']['ticket'] = $sceneData['r'];

        // 判断当前ticket是否是代理商
        if ($sceneData['type'] == DssUserQrTicketModel::AGENT_TYPE) {
            $packageType = PayServices::PACKAGE_0;
            $isAgent = true;
        } elseif ($sceneData['type'] == DssUserQrTicketModel::STUDENT_TYPE && $referrerUserId) {
            $refereeStudent = DssStudentModel::getRecord(['id' => $referrerUserId]);
            $data['referrer_info']['uuid'] = $refereeStudent['uuid'] ?? '';
            list($data['subscribe_status']) = self::formatStudentSubscribeStatus($refereeStudent['has_review_course'], $refereeStudent['sub_status'], $refereeStudent['sub_end_date']);
            if ($refereeStudent['has_review_course'] == DssStudentModel::REVIEW_COURSE_1980) {
                $packageType = PayServices::PACKAGE_0;
            }else{
                $giftCode = DssGiftCodeModel::hadPurchasePackageByType($referrerUserId, DssPackageExtModel::PACKAGE_TYPE_NORMAL);
                if (!empty($giftCode)){
                    $packageType = PayServices::PACKAGE_0;
                }
            }
        }
        // 是否可0元购买开关
        $enableFlag = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'zero_package_enable');
        if (empty($enableFlag) && $packageType == PayServices::PACKAGE_0) {
            $packageType = PayServices::PACKAGE_1;
        }

        //产品包
        $data['pkg'] = $packageType;

        //用户信息
        $mobile = DssUserWeiXinModel::getUserInfoBindWX($openid, self::REFERRAL_MINI_APP_ID,
            DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP);
        if (!empty($mobile) && isset($mobile[0]['mobile'])) {
            $data['mobile'] = $mobile[0]['mobile'];
            $data['uuid'] = $mobile[0]['uuid'];
            $data['had_purchased'] = self::getPurchasedStatus($mobile[0]['id']);
        }

        // 员工信息：
        if (!empty($sceneData['e'])) {
            $data['staff'] = DssEmployeeModel::getRecord(['id' => $sceneData['e']], ['uuid']);
        }

        if ($isAgent) {
            // 代理商参数的小程序，转发参数依然为代理商
            unset($sceneData['type']);
            $paramId = $sceneData['param_id'];
            $shareScene = urlencode('&param_id=' . $paramId);
        } else {
            $shareScene = self::makeReferralMiniShareScene($mobile[0],$sceneData);
        }

        $data['share_scene'] = $shareScene;

        $data['scene_data'] = $sceneData;

        return $data;
    }

    /**
     * 生成小程序非首页的分享转介绍参数
     * @param array $studentData 学生信息
     * @param array $scene 首页原始的分享参数
     * @param array $ext 附加参数
     * @param bool $isTakeActivity 是否保留活动id参数 false不保留 true保留
     * @param bool $isTakeEmployee 是否保留员工id参数 false不保留 true保留
     * @param bool  $isChannel 是否保留渠道id参数 false不保留 true保留
     * @return string
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function makeReferralMiniShareScene(
        $studentData,
        $scene,
        $ext = [],
        $isTakeActivity = false,
        $isTakeEmployee = false,
        $isChannel = false
    ) {
        $userId = $studentData['id'] ?? 0;

        $sceneData['e'] = $sceneData['a'] = $sceneData['c'] = $sceneData['r'] = '';
        //若用户绑定过小程序，使用小程序转发功能推荐给好友后，若好友注册并购买了体验课，则注册渠道ID为1220，与转发用户形成转介绍关系。
        //若用户未绑定小程序，使用小程序转发功能推荐给好友后，若好友注册并购买了体验课，则注册渠道ID为2185，不形成转介绍关系。

        if ($isChannel === true){
            $sceneData['c'] = $scene['c'];
        } elseif (!empty($studentData['id'])) {
            $sceneData['c'] = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL,
                'NORMAL_STUDENT_INVITE_STUDENT');
        } else {
            $sceneData['c'] = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL,
                'REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT');
        }

        //获取用户的ticket
        $sceneData['r'] = self::getUserQrTicket($userId, $sceneData['c'], $ext);

        //是否保留活动id参数
        if ($isTakeActivity === true) {
            $sceneData['a'] = $scene['a'];
        }
        //是否保留员工id参数
        if ($isTakeEmployee === true) {
            $sceneData['e'] = $scene['e'];
        }

        $paramId = ReferralActivityService::getParamsId(array_merge($sceneData,[
                'app_id' => Constants::SMART_APP_ID,
                'type' => ParamMapModel::TYPE_STUDENT,
                'user_id' => $userId,
            ])
        );

        return urlencode('&param_id=' . $paramId);
    }

    /**
     * 注册
     * @param $openId
     * @param $iv
     * @param $encryptedData
     * @param $sessionKey
     * @param $mobile
     * @param $countryCode
     * @param $referrerId
     * @param string $channel
     * @param array $extParams
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function remoteRegister(
        $openId,
        $iv,
        $encryptedData,
        $sessionKey,
        $mobile,
        $countryCode,
        $referrerId,
        $channel = '',
        $extParams = []
    ) {
        $wxCode = $extParams['wx_code'] ?? '';
        unset($extParams['wx_code']);
        if (!empty($encryptedData)) {
            $jsonMobile = self::decodeMobile(
                $iv,
                $encryptedData,
                $sessionKey,
                ['wx_code' => $wxCode, 'open_id' => $openId]
            );
            if (empty($jsonMobile)) {
                return [$openId, 0, null];
            }
            $mobile = $jsonMobile['purePhoneNumber'];
            $countryCode = $jsonMobile['countryCode'];
        }

        $userInfo = (new Dss())->studentRegisterBound([
            'mobile' => $mobile,
            'channel_id' => $channel ?: DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL,
                'REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT'),
            'open_id' => $openId,
            'busi_type' => DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP,
            'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
            'referee_id' => $referrerId,
            'country_code' => $countryCode,
            'ext_params' => $extParams
        ]);
        $lastId = $userInfo['student_id'] ?? '';
        if (empty($lastId)) {
            throw new RunTimeException(['user_register_fail']);
        }
        $uuid = $userInfo['uuid'] ?? '';
        $hadPurchased = self::getPurchasedStatus($lastId);

        return [$openId, $lastId, $mobile, $uuid, $hadPurchased];
    }

    /**
     * 解密手机号
     * @param $iv
     * @param $encryptedData
     * @param $sessionKey
     * @param array $extParams
     * @return mixed|null
     * @throws RunTimeException
     */
    public static function decodeMobile($iv, $encryptedData, $sessionKey, $extParams = [])
    {
        if (empty($sessionKey)) {
            SimpleLogger::error('session key is empty', []);
            return null;
        }
        $appId = DictConstants::get(DictConstants::WECHAT_APPID, '8_8');
        $w = new WXBizDataCrypt($appId, $sessionKey);
        $code = $w->decryptData($encryptedData, $iv, $data);
        if ($code == 0) {
            return json_decode($data, 1);
        }

        if (!empty($extParams['wx_code']) && !empty($extParams['open_id'])) {
            $wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_MINI_BUSI_TYPE);
            $wechat->code2Session($extParams['wx_code']);
            $sessionKey = $wechat->getSessionKey($extParams['open_id']);
            return self::decodeMobile($iv, $encryptedData, $sessionKey);
        }
        SimpleLogger::error("DECODE MOBILE ERROR", [$sessionKey, $extParams]);
        return null;
    }

    /**
     * 解析scene参数
     *
     * @param string $scene
     * @return array
     */
    public static function getSceneData(string $scene): array
    {
        $sceneData = ShowMiniAppService::getSceneData($scene);
        // 用户生成参数时状态转成文字
        if (isset($sceneData['user_current_status'])) {
            $sceneData['user_current_status_zh'] = DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$sceneData['user_current_status']];
        }
        return $sceneData;
    }

    /**
     * 可购买超时状态
     *
     * @param int $userId
     * @return int
     */
    private static function getPurchasedStatus(int $userId): int
    {
        //获取最新一条体验课信息
        $giftCode = DssGiftCodeModel::hadPurchasePackageByType(
            $userId,
            DssPackageExtModel::PACKAGE_TYPE_TRIAL,
            false,
            ['order' => ' id desc', 'limit' => 1]
        );
        if (empty($giftCode)) {
            return self::PURCHASED_STATUS_NONE;
        }
        if (time() - $giftCode[0]['buy_time'] < Util::TIMESTAMP_ONEDAY) {
            return self::PURCHASED_STATUS_IN_24;
        }
        return self::PURCHASED_STATUS_OUT_24;
    }


    /**
     * 前50名用户
     * @return array
     */
    public static function getBuyUserName(): array
    {
        $redis = RedisDB::getConn();
        $username = $redis->get(self::BUY_NAME_CACHE_KEY);
        if (empty($username)) {
            // 最近购买信息
            $username = DssGiftCodeModel::getBuyUserName();

            array_walk($username, function (&$value) {
                $value['name'] = mb_substr($value['name'], 0, 3, 'utf-8') . '***';
            });
            $redis->set(self::BUY_NAME_CACHE_KEY, json_encode($username));
            $redis->expire(self::BUY_NAME_CACHE_KEY, 5 * 60);  //5分钟
        } else {
            $username = json_decode($username, true);
        }

        $words = [];
        foreach ($username as $key => $value){
            if ($key%2 == 0){
                $words[] = '恭喜'.$value['name'].'抢到了<span>5天</span>体验营';
            }else{
                $words[] = '恭喜'.$value['name'].'获得了<span>19.8元</span>现金红包';
            }
        }

        return $words;
    }

    /**
     * 获取用户某个渠道的ticket
     *
     * @param int $userId
     * @param int $channelId
     * @param array $extParams
     * @return mixed|string|string[]|null
     * @throws \App\Libs\KeyErrorRC4Exception
     * @throws RunTimeException
     */
    public static function getUserQrTicket(int $userId, int $channelId, array $extParams = [])
    {
        //获取学生转介绍学生二维码资源数据
        $type = DssUserQrTicketModel::STUDENT_TYPE;
        $landingType = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
        $res = DssUserQrTicketModel::getUserQrRecord($userId, $type, $channelId, $landingType);
        if (!empty($res['qr_ticket'])) {
            return $res['qr_ticket'];
        } else {
            QueueService::genTicket(
                [
                    'user_id'      => $userId,
                    'type'         => $type,
                    'channel_id'   => $channelId,
                    'landing_type' => $landingType,
                    'ext'          => $extParams
                ]
            );
            return RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userId);
        }
    }


    /**
     * 小程序已购买体验课转介绍海报
     *
     * @param string $openId
     * @return array
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function miniBuyPageReferralPoster(string $openId): array
    {
        //微信绑定数据
        $weiXinBindData = DssUserWeixinModel::getByOpenId($openId, self::REFERRAL_MINI_APP_ID,
            DssUserWeixinModel::USER_TYPE_STUDENT, DssUserWeixinModel::BUSI_TYPE_REFERRAL_MINAPP);
        if (empty($weiXinBindData)) {
            throw new RunTimeException(['student_need_bind_mini']);
        }
        // 获取用户信息
        $studentInfo = StudentService::dssStudentStatusCheck($weiXinBindData['user_id']);

        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }

        //海报底图数据
        $posterBaseId = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'buy_trail_re_mini_sis_p_id');
        $posterBaseInfo = PosterModel::getRecord(['id' => $posterBaseId, 'status' => Constants::STATUS_TRUE], ['path']);

        if (empty($posterBaseInfo)) {
            throw new RunTimeException(['wechat_poster_not_exists']);
        }
        //渠道ID
        $channelId = DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'BUY_TRAIL_REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT');
        if (empty($channelId)) {
            throw new RunTimeException(['generate_channel_invalid']);
        }

        //生成二维码海报
        $posterConfig = PosterService::getPosterConfig();
        $referralPoster = PosterService::generateQRPosterAliOss(
            $posterBaseInfo['path'],
            $posterConfig,
            $studentInfo['student_info']['id'],
            DssUserQrTicketModel::STUDENT_TYPE,
            $channelId,
            ['p' => $posterBaseId, 'user_current_status' => $studentInfo['student_status']]
        );

        if (empty($referralPoster)) {
            throw new RunTimeException(['referral_poster_make_fail']);
        }
        //分享给好友的scene
        $shareScene = self::makeReferralMiniShareScene($studentInfo['student_info'],['c' => $channelId],['p' => $posterBaseId,'user_current_status' => $studentInfo['student_status']], false, false, true);
        return ['poster' => $referralPoster['poster_save_full_path'], 'share_scene' => $shareScene];
    }




    /**
     * 格式化学生订阅状态
     * @param $hasReviewCourse
     * @param $subStatus
     * @param $subEndDate
     * @return array
     */
    public static function formatStudentSubscribeStatus($hasReviewCourse, $subStatus, $subEndDate)
    {

        switch ($hasReviewCourse) {
            case DssStudentModel::REVIEW_COURSE_49:
                $subscribeStatus = self::STUDENT_TYPE_TRIAL;
                break;
            case DssStudentModel::REVIEW_COURSE_1980:
                $subscribeStatus = self::STUDENT_TYPE_YEAR_CARD;
                break;
            default:
                $subscribeStatus = empty($subEndDate) ? self::STUDENT_TYPE_REGISTERED : self::STUDENT_TYPE_GIFT_CODE;
        }

        if (empty($subEndDate)) {
            $subscribeStatusStr = '未订阅';
        } elseif (self::checkSubStatus($subStatus, $subEndDate)) {
            $subscribeStatusStr = (ceil((strtotime($subEndDate) - time()) / Util::TIMESTAMP_THIRTY_DAYS) > 120) ? '长期有效' : '有效期至' . date('Y-m-d', strtotime($subEndDate));
        } else {
            $subscribeStatusStr = ($hasReviewCourse == DssStudentModel::REVIEW_COURSE_49) ? '体验期已到期' : '已到期';
        }
        return [$subscribeStatus, $subscribeStatusStr];
    }

    public static function checkSubStatus($subStatus, $subEndDate)
    {
        if ($subStatus != DssStudentModel::SUB_STATUS_ON) {
            return false;
        }

        $endTime = strtotime($subEndDate) + 86400;
        return $endTime > time();
    }

    /**
     * 获取助教老师微信
     * @param $openid
     * @return array|false|mixed
     */
    public static function assistantInfo($openid)
    {
        $userType  = DssUserWeiXinModel::USER_TYPE_STUDENT;
        $status    = DssUserWeiXinModel::STATUS_NORMAL;
        $busiType  = DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP;
        $assistant = DssUserWeiXinModel::getWxQr($openid, $userType, $status, $busiType);
        $assistant = end($assistant) ?? [];
        if (empty($assistant) || empty($assistant['wx_qr'])) {
            return array();
        }
        $assistant['wx_qr'] = AliOSS::replaceCdnDomainForDss($assistant['wx_qr']);
        return $assistant;
    }
}
