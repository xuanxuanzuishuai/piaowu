<?php
/**
 * User: lizao
 * Date: 2020/9/23
 * Time: 15:08 PM
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\GiftCodeModel;
use App\Models\MessagePushRulesModel;
use App\Models\MessageManualPushLogModel;
use App\Libs\Util;
use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Models\MessageRecordLogModel;
use App\Models\MessageRecordModel;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Models\PosterModel;
use App\Models\PackageExtModel;
use App\Models\ReviewCourseModel;
use App\Models\SharePosterModel;
use App\Models\StudentModel;
use App\Models\StudentRefereeModel;
use App\Models\UserQrTicketModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatConfigModel;
use App\Services\Queue\QueueService;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MessageService
{
    const PUSH_MESSAGE_RULE_KEY = 'push_message_rules';
    //用户接收微信消息的限制
    const PUSH_MESSAGE_RULE = [
        ['expire_time' => '14400', 'limit' => 1],
        ['expire_time' => '172800', 'limit' => 2]
    ];
    //用户消息规则key
    const MESSAGE_RULE_KEY = 'message_rule_key_';

    /**
     * @param $params
     * @return array
     * 消息推送规则列表
     */
    public static function rulesList($params)
    {
        list($rules, $totalCount) = MessagePushRulesModel::rulesList($params);

        if (!empty($rules)) {
            foreach ($rules as &$rule) {
                $rule = self::ruleFormat($rule);
                // $rule['extra'] = self::getMessagePushRuleByID($rule['id']);
            }
        }

        return [$rules, $totalCount];
    }

    /**
     * @param $id
     * @return array
     * 单条推送规则详情
     */
    public static function ruleDetail($id)
    {
        if (empty($id)) {
            return [];
        }
        $detail = MessagePushRulesModel::getById($id);
        return self::ruleFormat($detail);
    }

    /**
     * @param $params
     * @return string|null
     * 推送规则启用状态修改
     */
    public static function ruleUpdateStatus($params)
    {
        $updateData                = [];
        $updateData['is_active']   = $params['status'];
        $updateData['update_time'] = time();
        $res = MessagePushRulesModel::updateRecord($params['id'], $updateData, false);
        if (is_null($res)) {
            return 'update_failure';
        }
        self::updatePushRuleCache($params['id']);
        return null;
    }

    /**
     * @param $params
     * @return string|null
     * 推送规则更新
     */
    public static function ruleUpdate($params)
    {
        $updateData                = [];
        $updateData['remark']      = $params['remark'] ?? '';
        $updateData['update_time'] = time();
        $updateData['content']     = [];
        $keyTypeDict = [
            'content_1' => WeChatConfigModel::CONTENT_TYPE_TEXT,
            'content_2' => WeChatConfigModel::CONTENT_TYPE_TEXT,
            'image'     => WeChatConfigModel::CONTENT_TYPE_IMG,
        ];
        foreach ($keyTypeDict as $key => $type) {
            if (isset($params[$key]) && !Util::emptyExceptZero($params[$key])) {
                $item = [];
                if ($type == WeChatConfigModel::CONTENT_TYPE_IMG) {
                    $item = PosterModel::$settingConfig[PosterModel::APPLY_TYPE_TEACHER_WECHAT];
                } elseif ($type == WeChatConfigModel::CONTENT_TYPE_TEXT) {
                    $params[$key] = Util::textEncode($params[$key]);
                }

                $item['key']   = $key;
                $item['type']  = $type;
                $item['value'] = $params[$key];
                $updateData['content'][] = $item;
            }
        }
        if (!empty($updateData['content'])) {
            $updateData['content'] = json_encode($updateData['content']);
        }
        $res = MessagePushRulesModel::updateRecord($params['id'], $updateData, false);
        if (is_null($res)) {
            return 'update_failure';
        }
        self::updatePushRuleCache($params['id']);
        return null;
    }

    /**
     * @param $rule
     * @return mixed
     * 消息规则格式化
     */
    public static function ruleFormat($rule)
    {
        $rule['display_type']      = MessagePushRulesModel::PUSH_TYPE_DICT[$rule['type']] ?? '';
        $rule['display_target']    = MessagePushRulesModel::PUSH_TARGET_DICT[$rule['target']] ?? '';
        $rule['update_time']       = date('Y-m-d H:i:s', $rule['update_time']);
        $rule['display_is_active'] = DictService::getKeyValue('message_rule_active_status', $rule['is_active']);
        // 解析【推送时间】字段
        if (!isset($rule['display_time']) && isset($rule['time'])) {
            $time = json_decode($rule['time'], true);
            $rule['display_time'] = $time['desc'] ?? '';
            unset($rule['time']);
        }
        // 规则内容解析，文字解码，图片URL处理
        if (!isset($rule['content_detail']) && isset($rule['content'])) {
            $content = json_decode($rule['content'], true);
            foreach ($content as $key => &$value) {
                if ($value['type'] == WeChatConfigModel::CONTENT_TYPE_IMG) {
                    $value['path']  = $value['value'];
                    $value['value'] = AliOSS::replaceCdnDomainForDss($value['value']);
                } elseif ($value['type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) {
                    $value['value'] = Util::textDecode($value['value']);
                }
            }
            $rule['content_detail'] = $content;
            unset($rule['content']);
        }
        return $rule;
    }

    /**
     * @return array
     * 上次推送记录
     */
    public static function manualLastPush()
    {
        list($cnt, $record) = MessageManualPushLogModel::getPage(['ORDER' => ['id' => 'DESC']], 1, 1, false, "*");
        $data = [];
        if (!empty($record)) {
            $lastRecord = $record[0] ?? [];
            if (!empty($lastRecord)) {
                $data = json_decode($lastRecord['data'], true);
                foreach ($data as $key => &$value) {
                    if (is_string($value)) {
                        $value = Util::textDecode($value);
                    }
                    if ($key == 'image') {
                        $data['image_url'] = AliOSS::replaceCdnDomainForDss($value);
                    }
                }
            }
        }
        // 手机号模板文件：
        // 上传手机号模板文件脚本：script/upload_mobile_number_template.php
        $filePath     = $_ENV['ENV_NAME'].'/'.AliOSS::DIR_MESSAGE_EXCEL."/mobile_number.xlsx";
        $templateFile = AliOSS::replaceCdnDomainForDss($filePath);
        return [$templateFile, $data];
    }

    /**
     * @param $id
     * @return array|mixed
     * 根据规则ID获取推送设置
     */
    public static function getMessagePushRuleByID($id)
    {
        if (empty($id)) {
            return [];
        }
        $redis = RedisDB::getConn();
        $cache = $redis->hget(self::PUSH_MESSAGE_RULE_KEY, $id);
        if (!empty($cache)) {
            return json_decode($cache, true);
        }
        return self::updatePushRuleCache($id);
    }

    /**
     * @param $id
     * @return array
     * 更新推送消息规则缓存
     */
    public static function updatePushRuleCache($id)
    {
        $redis = RedisDB::getConn();
        $rule  = MessagePushRulesModel::getById($id);
        $data  = [];
        if (!empty($rule) && isset($rule['time'])) {
            $ruleFormat = self::ruleFormat($rule);
            $data = [
                'is_active' => $rule['is_active'],
                'target'    => $rule['target'],
                'type'      => $rule['type'],
                'content'   => $ruleFormat['content_detail'],
                'setting'   => json_decode($rule['time'], true)
            ];
        }
        if (!empty($data)) {
            $redis->hset(self::PUSH_MESSAGE_RULE_KEY, $id, json_encode($data));
        }
        return $data;
    }

    /**
     * @param $fileName
     * @return RunTimeException|array|string
     * 验证手机号格式
     */
    public static function verifySendList($fileName)
    {
        try {
            $fileType    = ucfirst(pathinfo($fileName)['extension']);
            $reader      = IOFactory::createReader($fileType);
            $spreadSheet = $reader->load($fileName);
            $activeSheet = $spreadSheet->getActiveSheet();
            $data = [];
            foreach ($activeSheet->getRowIterator() as $row) {
                $rowIndex     = $row->getRowIndex();
                $mobileNumber = trim($activeSheet->getCellByColumnAndRow(1, $rowIndex)->getValue());
                if (empty($mobileNumber) || $rowIndex == 1) {
                    continue;
                }
                if (!Util::isChineseMobile($mobileNumber)) {
                    return 'mobile_format_error';
                }
                $data[] = $mobileNumber;
            }
            return $data;
        } catch (Exception $e) {
            return new RunTimeException([$e->getMessage()]);
        }
    }

    /**
     * @param $data
     * @return int|mixed|string|null
     * 保存手动发送记录
     */
    public static function saveSendLog($data)
    {
        $insertData = [];
        $insertData['type'] = $data['push_type'] ?? 0;
        $insertData['file'] = $data['push_file'] ?? '';
        $insertData['create_time'] = time();
        $encodeList = ['content_1', 'content_2', 'image', 'first_sentence', 'activity_detail', 'activity_desc', 'remark', 'link'];
        foreach ($encodeList as $key) {
            if (isset($data[$key]) && !Util::emptyExceptZero($data[$key]) && is_string($data[$key])) {
                $data[$key] = Util::textEncode($data[$key]);
            }
        }
        $insertData['data'] = json_encode($data);
        return MessageManualPushLogModel::insertRecord($insertData, false);
    }

    /**
     * @param $data
     * 消息队列发送消息
     */
    public static function realSendMessage($data)
    {
        //实际发送前再次确认带过来的规则是否适用
        $data = self::transformOpenidRelateRule($data);
        //校验是否超过发送消息限制
        $verify = self::preSendVerify($data['open_id'], $data['rule_id']);
        if (empty($verify)) {
            return;
        }
        $messageRule = self::getMessagePushRuleByID($data['rule_id']);
        //发送客服消息
        if ($messageRule['type'] == MessagePushRulesModel::PUSH_TYPE_CUSTOMER) {
            $res = self::pushCustomMessage($messageRule, $data);
            //推送日志记录
            MessageRecordService::addRecordLog($data['open_id'], MessageRecordModel::AUTO_PUSH, $data['rule_id'], $res);
        }
        //首关不记录
        if ($data['rule_id'] == DictConstants::get(DictConstants::MESSAGE_RULE, 'subscribe_rule_id')) {
            return;
        }
        //记录发送限制
        self::recordUserMessageRuleLimit($data['open_id']);
    }

    /**
     * @param $data
     * 手动消息
     */
    public static function realSendManualMessage($data)
    {
        $info = MessageManualPushLogModel::getById($data['log_id']);
        if ($info['type'] == MessagePushRulesModel::PUSH_TYPE_TEMPLATE) {
            //模版消息
            $templateConfig = json_decode($info['data'], true);
            $sendData = [];
            //根据关键标志替换模板内容
            $sendData['first']['value'] = $templateConfig['first_sentence'] ?? '';
            $sendData['keyword1']['value'] = $templateConfig['activity_detail'] ?? '';
            $sendData['keyword2']['value'] = $templateConfig['activity_desc'] ?? '';
            $sendData['remark']['value'] = $templateConfig['remark'] ?? '';
            $result = WeChatService::notifyUserWeixinTemplateInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, WeChatService::USER_TYPE_STUDENT,
                $data['open_id'],
                DictConstants::get(DictConstants::MESSAGE_RULE, 'assign_template_id'), $sendData, $templateConfig['link']);
            //推送日志记录
            MessageRecordService::addRecordLog($data['open_id'], $data['activity_type'], $data['log_id'], ((!empty($result) && $result['errcode'] != 0) || empty($result)) ? MessageRecordLogModel::PUSH_FAIL : MessageRecordLogModel::PUSH_SUCCESS);
        }
    }

    /**
     * @param $messageRule
     * @param $data
     * @return bool
     * 基于规则 发送客服消息
     */
    private static function pushCustomMessage($messageRule, $data)
    {
        //即时发送
        $res = MessageRecordLogModel::PUSH_SUCCESS;
        array_map(function ($item)use($data, &$res) {
            if (empty($item['value'])) {
                return;
            }
            if ($item['type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) { //发送文本消息
                //转义数据
                $content = Util::textDecode($item['value']);
                $res1 = WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                    UserWeixinModel::USER_TYPE_STUDENT, $data['open_id'], $content);
                //全部推送成功才算成功
                if ($res) {
                    ((!empty($res1) && $res1['errcode'] != 0) || empty($res1)) && $res = MessageRecordLogModel::PUSH_FAIL;
                }
            } elseif ($item['type'] == WeChatConfigModel::CONTENT_TYPE_IMG) { //发送图片消息
                $posterImgFile = self::dealPosterByRule($data, $item);
                if (empty($posterImgFile)) {
                    return;
                }
                //上传到微信服务器
                $weChatAppIdSecret = WeChatService::getWeCHatAppIdSecret(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT);

                $wx = WeChatMiniPro::factory(['app_id' => $weChatAppIdSecret['app_id'], 'app_secret' => $weChatAppIdSecret['secret']]);
                if (empty($wx)) {
                    SimpleLogger::error('wx create fail', ['we_chat_type'=>UserWeixinModel::USER_TYPE_STUDENT]);
                    return;
                }
                $wxData = $wx->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
                //发送海报
                if (!empty($wxData['media_id'])) {
                    $res2 =  WeChatService::toNotifyUserWeixinCustomerInfoForImage(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, $data['open_id'], $wxData['media_id']);
                    //全部推送成功才算成功
                    if ($res) {
                        ((!empty($res2) && $res2['errcode'] != 0) || empty($res2)) && $res = MessageRecordLogModel::PUSH_FAIL;
                    }
                }
            }
        }, $messageRule['content']);
        return $res;
    }

    /***
     * @param $data
     * @param $item
     * @return array|string|void
     * 基于规则处理要发送的图片
     */
    private static function dealPosterByRule($data, $item)
    {
        //走关注规则，无法获取转介绍二维码
        if ($data['rule_id'] == DictConstants::get(DictConstants::MESSAGE_RULE, 'subscribe_rule_id')) {
            $posterImgFile = ['poster_save_full_path' => $item['value'], 'unique' => md5($data['open_id'].$item['value']) . '.jpg'];
        } else {
            //非关注拼接转介绍二维码
            $userInfo = UserWeixinModel::getBoundInfoByOpenId($data['open_id'],
                UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                WeChatService::USER_TYPE_STUDENT,
                UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
            if (empty($userInfo['user_id'])) {
                return;
            }
            $posterImgFile = UserService::generateQRPosterAliOss($userInfo['user_id'], $item['path'], UserQrTicketModel::STUDENT_TYPE, $item['poster_width'], $item['poster_height'], $item['qr_width'], $item['qr_height'], $item['qr_x'], $item['qr_y'], DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'NORMAL_STUDENT_INVITE_STUDENT'));
        }
        return $posterImgFile;
    }

    /**
     * @param $data
     * @return mixed
     * 特定规则在实际发送的时候需要依据最新用户状态
     */
    public static function transformOpenidRelateRule($data)
    {
        if (!in_array($data['rule_id'], DictConstants::getValues(DictConstants::MESSAGE_RULE, [
            'year_user_c_rule_id',
            'trail_user_c_rule_id',
            'register_user_c_rule_id'
        ]))) {
            return $data;
        }
        $data['rule_id'] = self::judgeUserRelateRuleId($data['open_id']);
        return $data;
    }

    /**
     * @param $openId
     * @param $ruleId
     * 发送消息
     */
    public static function sendMessage($openId, $ruleId)
    {
        is_string($openId) && $openId = [$openId];
        $sendArr = [];
        array_map(function ($item)use($ruleId, &$sendArr){
            $sendArr[] = self::preSendVerify($item, $ruleId);
        },$openId);
        if (empty($sendArr)) {
            return;
        }
        QueueService::messageRulePushMessage($sendArr);
    }

    /**
     * @param $openId
     * @param $ruleId
     * @return array|void
     * 发送消息的前置校验
     */
    public static function preSendVerify($openId, $ruleId)
    {
        if (empty($ruleId)) {
            return;
        }
        //是否超过次数限制
        if (self::judgeOverMessageRuleLimit($openId)) {
            return;
        }
        //是否开启
        $messageRule = self::getMessagePushRuleByID($ruleId);
        if (in_array($messageRule['is_active'], [MessagePushRulesModel::STATUS_DOWN, MessagePushRulesModel::STATUS_DISABLE])) {
            SimpleLogger::info('message rule not active ', ['rule_id' => $ruleId]);
            return;
        }
        //延迟时间
        $delayTime = $messageRule['setting']['delay_time'];
        return ['delay_time' => $delayTime, 'rule_id' => $ruleId, 'open_id' => $openId];
    }

    /**
     * @param $openId
     * 清除用户的消息规则限制
     */
    public static function clearMessageRuleLimit($openId)
    {
        $redis = RedisDB::getConn();
        array_map(function ($item)use($redis, $openId){
            $redis->del([self::MESSAGE_RULE_KEY . $item['expire_time'] . '_' . $openId]);
        }, self::PUSH_MESSAGE_RULE);
    }

    /**
     * @param $openId
     * @return bool
     * 判断当前用户是否超过限制条数
     */
    public static function judgeOverMessageRuleLimit($openId)
    {
        $redis = RedisDB::getConn();
        foreach (self::PUSH_MESSAGE_RULE as $v) {
            $key = self::MESSAGE_RULE_KEY . $v['expire_time'] . '_' .  $openId;
            $num = intval($redis->get($key));
            if ($num >= $v['limit']) {
                SimpleLogger::info('message over num limit ', ['expire_time' => $v['expire_time'], 'open_id' => $openId]);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $openId
     * 记录用户有效时间消息发送的条数
     */
    public static function recordUserMessageRuleLimit($openId)
    {
        $redis = RedisDB::getConn();
        array_map(function ($item) use($redis, $openId) {
            $value = $redis->get(self::MESSAGE_RULE_KEY . $item['expire_time'] . '_' .  $openId);
            $redis->setex(self::MESSAGE_RULE_KEY . $item['expire_time'] . '_' . $openId, $item['expire_time'], (int)$value + 1);
        },self::PUSH_MESSAGE_RULE);
    }

    /**
     * @param $openId
     * 用户与微信交互后的消息处理
     */
    public static function interActionDealMessage($openId)
    {
        $ruleId = self::judgeUserRelateRuleId($openId);
        if (!empty($ruleId)) {
            self::sendMessage($openId, $ruleId);
        }

    }

    /**
     * @param $openId
     * @return array|mixed|void|null
     * 当前交互用户适用哪类推送
     */
    public static function judgeUserRelateRuleId($openId)
    {
        //当前用户
        $userInfo = UserWeixinModel::getBoundInfoByOpenId($openId,
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
        //当前用户属于何种用户分类
        if (empty($userInfo['user_id'])) {
            return;
        }
        $time = time();
        $studentInfo = StudentModel::getById($userInfo['user_id']);
        list($inviteDiffTime, $resultDiffTime) = DictConstants::getValues(DictConstants::MESSAGE_RULE, ['how_long_not_invite', 'how_long_not_result']);
        //所有的体验包id
        $trailPackageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL]), 'package_id');
        //所有年包id
        $yearPackageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL]), 'package_id');
        // 新产品中心：
        // 所有体验课产品包ID：
        $v1TrialPackageIdArr = ErpPackageV1Model::getTrailPackageIds();
        // 所有正式课产品包ID：
        $v1NormalPackageIdArr = ErpPackageV1Model::getNormalPackageIds();

        //X天内转介绍行为
        $inviteOperateInfo = SharePosterModel::getRecord(['student_id' => $studentInfo['id'], 'create_time[>]' => $time - $inviteDiffTime, 'status' => SharePosterModel::STATUS_QUALIFIED]);
        if ($studentInfo['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980) {
            //转介绍结果（付过费就算)
            $inviteResultInfo = StudentRefereeModel::refereeBuyCertainPackage($studentInfo['id'], array_merge($yearPackageIdArr, $trailPackageIdArr, $v1TrialPackageIdArr, $v1NormalPackageIdArr), $time - $resultDiffTime);
            if (empty($inviteOperateInfo) && empty($inviteResultInfo)) {
                //年卡c用户
                return DictConstants::get(DictConstants::MESSAGE_RULE, 'year_user_c_rule_id');
            }
        } else {
            //转介绍结果（30天被推荐人付费年卡）
            $inviteResultInfo = StudentRefereeModel::refereeBuyCertainPackage($studentInfo['id'], $yearPackageIdArr, $time - $resultDiffTime, GiftCodeModel::PACKAGE_V1_NOT);
            $inviteResultInfo2 = StudentRefereeModel::refereeBuyCertainPackage($studentInfo['id'], $v1NormalPackageIdArr, $time - $resultDiffTime, GiftCodeModel::PACKAGE_V1);
            if (empty($inviteOperateInfo) && empty($inviteResultInfo) && empty($inviteResultInfo2)) {
                return $studentInfo['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_NO ? DictConstants::get(DictConstants::MESSAGE_RULE, 'register_user_c_rule_id') : DictConstants::get(DictConstants::MESSAGE_RULE, 'trail_user_c_rule_id');
            }
        }
        SimpleLogger::info('not message rule apply user ', ['student_id' => $studentInfo['id']]);
        return ;
    }

    /**
     * @param $logId
     * @param $mobileArr
     * @param $employeeId
     * 手动发送消息
     */
    public static function manualPushMessage($logId, $mobileArr, $employeeId)
    {
        $boundUsers = UserWeixinModel::getBoundInfoByMobile($mobileArr, UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, WeChatService::USER_TYPE_STUDENT, UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
        $info = MessageManualPushLogModel::getById($logId);
        $content = json_decode($info['data'], true);
        if ($info['type'] == MessagePushRulesModel::PUSH_TYPE_CUSTOMER) {
            // 放到nsq队列中一个个处理
            QueueService::pushWX($boundUsers,$content['content_1'] ?? '', $content['content_2'] ?? '', $content['image'] ?? '', $logId, $employeeId, MessageRecordModel::MANUAL_PUSH);
        } else {
            QueueService::manualPushMessage($boundUsers, $logId, $employeeId, MessageRecordModel::MANUAL_PUSH);
        }
    }
}