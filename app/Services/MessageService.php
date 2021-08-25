<?php
/**
 * User: lizao
 * Date: 2021/01/06
 * Time: 19:00
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Libs\Util;
use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssSharePosterModel;
use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\MessagePushRulesModel;
use App\Models\MessageManualPushLogModel;
use App\Models\MessageRecordLogModel;
use App\Models\MessageRecordModel;
use App\Models\PosterModel;
use App\Models\SharePosterModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\UserPointsExchangeOrderModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatConfigModel;
use App\Services\Queue\QueueService;
use App\Services\Queue\SaBpDataTopic;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MessageService
{
    const PUSH_MESSAGE_RULE_KEY = 'push_message_rules';
    //用户接收微信消息整体规则的限制
    const PUSH_MESSAGE_RULE = [
        ['expire_time' => '14400', 'limit' => 3],
        ['expire_time' => '172800', 'limit' => 4]
    ];
    //用户接收微信消息单一规则的限制
    const PER_RULE_MESSAGE_RULE = [
        ['expire_time' => '600', 'limit' => 1],
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
        $res = MessagePushRulesModel::updateRecord($params['id'], $updateData);
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
        $posterConfig = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
        foreach ($keyTypeDict as $key => $type) {
            if (isset($params[$key]) && !Util::emptyExceptZero($params[$key])) {
                $item = [];
                if ($type == WeChatConfigModel::CONTENT_TYPE_IMG) {
                    $item = $posterConfig;
                    $item['poster_id'] = PosterModel::getIdByPath($params[$key]);
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
        $res = MessagePushRulesModel::updateRecord($params['id'], $updateData);
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
        $rule['display_is_active'] = MessagePushRulesModel::RULE_STATUS_DICT[$rule['is_active']];
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
        $filePath     = $_ENV['ENV_NAME'].'/'.AliOSS::DIR_MESSAGE_EXCEL."/student_uuid.xlsx";
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
                'setting'   => json_decode($rule['time'], true),
                'name' => $rule['name'] ?? '',
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

            $filterRow = 0;
            foreach ($activeSheet->getRowIterator() as $row) {
                $rowIndex     = $row->getRowIndex();
                //TOCRM-1191 bi里筛选导出的用户没有手机号了，这里要换成uuid
                $uuid = trim($activeSheet->getCellByColumnAndRow(1, $rowIndex)->getValue());
                if (empty($uuid) || $rowIndex == 1) {
                    if ($filterRow > 10) {
                        break;
                    }
                    $filterRow++;
                    continue;
                }

                $data[] = $uuid;
            }
//            if (count($data) > 10000) {
//                return 'file_over_maximum_limits';
//            }
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
        return MessageManualPushLogModel::insertRecord($insertData);
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
            $res = self::pushCustomMessage($messageRule, $data, $data['app_id'] ?? null);
            //推送日志记录
            MessageRecordService::addRecordLog($data['open_id'], MessageRecordModel::ACTIVITY_TYPE_AUTO_PUSH, $data['rule_id'], $res);
        }
        //记录发送限制
        self::recordUserMessageRuleLimit($data['open_id'], $data['rule_id']);
    }

    /**
     * @param $data
     * 手动消息
     */
    public static function realSendManualMessage($data)
    {
        $info = MessageManualPushLogModel::getById($data['log_id']);
        $appId = DssUserWeiXinModel::dealAppId($data['app_id']);
        if ($info['type'] == MessagePushRulesModel::PUSH_TYPE_TEMPLATE) {
            //模版消息
            $templateConfig = json_decode($info['data'], true);
            $sendData = [];
            //根据关键标志替换模板内容
            $sendData['first']['value'] = $templateConfig['first_sentence'] ?? '';
            $sendData['keyword1']['value'] = $templateConfig['activity_detail'] ?? '';
            $sendData['keyword2']['value'] = $templateConfig['activity_desc'] ?? '';
            $sendData['remark']['value'] = $templateConfig['remark'] ?? '';
            $result = PushMessageService::notifyUserWeixinTemplateInfo(
                $appId,
                $data['open_id'],
                DictConstants::get(DictConstants::MESSAGE_RULE, 'assign_template_id'),
                $sendData,
                $templateConfig['link']
            );
            //推送日志记录
            MessageRecordService::addRecordLog(
                $data['open_id'],
                $data['activity_type'],
                $data['log_id'],
                (empty($result) || !empty($result['errcode'])) ? MessageRecordLogModel::PUSH_FAIL : MessageRecordLogModel::PUSH_SUCCESS
            );
        }
    }

    /**
     * @param $messageRule
     * @param $data
     * @return bool
     * 基于规则 发送客服消息
     */
    private static function pushCustomMessage($messageRule, $data, $appId = null)
    {
        $posterId = 0;
        $user_current_status = DssStudentModel::STATUS_REGISTER;
        $appId = DssUserWeiXinModel::dealAppId($appId);
        //即时发送
        $res = MessageRecordLogModel::PUSH_SUCCESS;
        $wechat = WeChatMiniPro::factory($appId, PushMessageService::APPID_BUSI_TYPE_DICT[$appId]);
        if (empty($wechat)) {
            SimpleLogger::error('wechat create fail', ['pushCustomMessage']);
            return false;
        }
        foreach ($messageRule['content'] as $item) {
            if (empty($item['value'])) {
                continue;
            }
            if ($item['type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) { //发送文本消息
                //转义数据
                $content = Util::textDecode($item['value']);
                $content = Util::pregReplaceTargetStr($content, $data);
                $res1 = $wechat->sendText($data['open_id'], $content);
                //全部推送成功才算成功
                if (empty($res1) || !empty($res1['errcode'])) {
                    $res = MessageRecordLogModel::PUSH_FAIL;
                }
            } elseif ($item['type'] == WeChatConfigModel::CONTENT_TYPE_IMG) { //发送图片消息
                $posterImgFile = self::dealPosterByRule($data, $item);
                if (empty($posterImgFile)) {
                    SimpleLogger::error('empty poster file', ['pushCustomMessage', $data, $item]);
                    continue;
                }
                $wxData = $wechat->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
                //发送海报
                if (!empty($wxData['media_id'])) {
                    $res2 = $wechat->sendImage($data['open_id'], $wxData['media_id']);
                    //全部推送成功才算成功
                    if (empty($res2) || !empty($res2['errcode'])) {
                        $res = MessageRecordLogModel::PUSH_FAIL;
                    }
                }
                $posterId = PosterModel::getIdByPath($item['path']);
                $user_current_status = $posterImgFile['user_current_status'];
            }
        }

        // 关注规则，无法获取转介绍二维码
        if ($data['rule_id'] != DictConstants::get(DictConstants::MESSAGE_RULE, 'subscribe_rule_id')) {
            // 海报埋点 - 全部推送成功
            if ($res == MessageRecordLogModel::PUSH_SUCCESS && $posterId > 0) {
                $openidUserInfo = DssUserWeiXinModel::getUserInfoBindWX($data['open_id'], $appId, PushMessageService::APPID_BUSI_TYPE_DICT[$appId]);
                if (empty($openidUserInfo[0]['uuid'])) {
                    return $res;
                }
                $queueData = [
                    'uuid' => $openidUserInfo[0]['uuid'],
                    'poster_id' => intval($posterId),
                    'activity_name' => $messageRule['name'] ?? '',
                    'user_status' => DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$user_current_status],
                ];
                SimpleLogger::info('MessageService::pushCustomMessage', ['info' => 'SaBpDataTopic', 'queueData' => $queueData, 'param_data' => $data]);
                (new SaBpDataTopic())->posterPush($queueData)->publish();
            }
        }

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
        if (in_array($data['rule_id'], DictConstants::getValues(DictConstants::MESSAGE_RULE,
            ['subscribe_rule_id', 'life_subscribe_rule_id']))
        ) {
            $posterImgFile = ['poster_save_full_path' => $item['value'], 'unique' => md5($data['open_id'].$item['value']) . '.jpg', 'user_current_status' => DssStudentModel::STATUS_REGISTER];
        } elseif ($data['rule_id'] == DictConstants::get(DictConstants::MESSAGE_RULE,'invite_friend_rule_id')) {
            //真人邀请好友
            $config = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
            //用户二维码图片信息在referral项目中获取
            $posterImgFile = PosterService::generateLifeQRPosterAliOss(
                $item['path'],
                $config,
                $data['user_id']
            );

        }else {
            //非关注拼接转介绍二维码
            $userInfo = DssUserWeiXinModel::getByOpenId($data['open_id']);
            if (empty($userInfo['user_id'])) {
                return;
            }
            list($baseChannelId, $yearBuyChannel) = DictConstants::getValues(DictConstants::STUDENT_INVITE_CHANNEL, ['NORMAL_STUDENT_INVITE_STUDENT', 'BUY_NORMAL_STUDENT_INVITE_STUDENT']);
            $channelId = $data['rule_id'] == DictConstants::get(DictConstants::MESSAGE_RULE, 'year_pay_rule_id') ? $yearBuyChannel : $baseChannelId;
            $config = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);

            // 埋点 - 海报id、用户身份
            $userStatus  = StudentService::dssStudentStatusCheck($userInfo['user_id']); // 获取用户当前状态
            $posterName = $item['name'] ?? ''; // message_push_rules表中name字段作为海报名称
            $posterImgFile = PosterService::generateQRPosterAliOss(
                $item['path'],
                $config,
                $userInfo['user_id'],
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                $channelId,
                [
                    'p' => PosterModel::getIdByPath($item['path'], ['name' => $posterName]),
                    'user_current_status' => $userStatus['student_status']
                ]
            );
            $posterImgFile['user_current_status'] = $userStatus['student_status'];
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
        //年卡c体验c注册c
        if (in_array(
            $data['rule_id'],
            DictConstants::getValues(
                DictConstants::MESSAGE_RULE,
                [
                    'year_user_c_rule_id',
                    'trail_user_c_rule_id',
                    'register_user_c_rule_id'
                ]
            )
        )) {
            $data['rule_id'] = self::judgeUserRelateRuleId($data['open_id']);
            return $data;
        }
        //体验绑定，年卡绑定
        if (in_array(
            $data['rule_id'],
            DictConstants::getValues(
                DictConstants::MESSAGE_RULE,
                [
                    'only_trail_rule_id',
                    'only_year_rule_id'
                ]
            )
        )) {
            $data['rule_id'] = self::boundJudgeUserRuleId($data['open_id']);
            return $data;
        }
        return $data;
    }

    /**
     * @param $data [open_id => [data]]
     * @param $ruleId
     * @param $appId
     * @param int $deferMax
     * 发送消息
     */
    public static function sendMessage($data, $ruleId, $appId = null, $deferMax = 0)
    {
        $appId = DssUserWeiXinModel::dealAppId($appId);
        $sendArr = [];
        foreach ($data as $open_id => $value) {
            $check = self::preSendVerify($open_id, $ruleId, $appId);
            if (empty($check)) {
                continue;
            }
            $tmp = array_merge($value, $check);
            $sendArr[] = $tmp;
        }
        if (empty($sendArr)) {
            return;
        }
        QueueService::messageRulePushMessage($sendArr, $deferMax);
    }

    /**
     * @param $openId
     * @param $ruleId
     * @return array|void
     * 发送消息的前置校验
     */
    public static function preSendVerify($openId, $ruleId, $appId = null)
    {
        if (empty($ruleId)) {
            return;
        }

        //是否超过次数限制
        if (self::judgeOverMessageRuleLimit($openId, $ruleId)) {
            return;
        }
        //是否开启
        $messageRule = self::getMessagePushRuleByID($ruleId);
        if ($messageRule['is_active'] != MessagePushRulesModel::STATUS_ENABLE) {
            SimpleLogger::info('message rule not active ', ['rule_id' => $ruleId]);
            return;
        }
        //延迟时间
        $delayTime = $messageRule['setting']['delay_time'];
        return ['delay_time' => $delayTime, 'rule_id' => $ruleId, 'open_id' => $openId, 'app_id' => $appId];
    }

    /**
     * @param $openId
     * 清除用户的消息规则限制
     */
    public static function clearMessageRuleLimit($openId)
    {
        $redis = RedisDB::getConn();
        //整体限制清除
        array_map(function ($item) use ($redis, $openId) {
            $redis->del([self::getMessageKey($openId, $item['expire_time'])]);
        }, self::PUSH_MESSAGE_RULE);
        //单一规则清除
        $allRule = MessagePushRulesModel::getRecords(['id[>]' => 0], ['id', 'time']);
        array_map(function ($item) use ($redis, $openId, $allRule) {
            array_map(function ($i) use ($redis, $openId, $item) {
                $timeConfig = json_decode($i['time'], true);
                $redis->del(
                    [
                        self::getMessageKey($openId, $item['expire_time'], $i['id']),
                        self::getMessageKey($openId, $timeConfig['expire'] ?? 0, $i['id']),
                    ]
                );
            }, $allRule);
        }, self::PER_RULE_MESSAGE_RULE);
    }

    /**
     * 消息存储key
     * @param $openId
     * @param $expireTime
     * @param null $ruleId
     * @return string
     */
    private static function getMessageKey($openId, $expireTime, $ruleId = null)
    {
        return empty($ruleId) ? self::MESSAGE_RULE_KEY . $openId . '_' .$expireTime  : self::MESSAGE_RULE_KEY . $openId . '_' . $expireTime . '_' . $ruleId;
    }

    /**
     * @param $openId
     * @param $ruleId
     * @return bool
     * 判断当前用户是否超过限制条数
     */
    public static function judgeOverMessageRuleLimit($openId, $ruleId)
    {
        if (self::isActiveClickPushMessage($ruleId)){
            return false;
        }

        $redis = RedisDB::getConn();

        $ruleInfo = MessagePushRulesModel::getById($ruleId);
        $timeConfig = json_decode($ruleInfo['time'], true);
        if (!empty($timeConfig['expire'])) {
            $limit = $timeConfig['limit'] ?? 1;
            $key = self::getMessageKey($openId, $timeConfig['expire'], $ruleId);
            $num = intval($redis->get($key));
            if ($num >= $limit) {
                SimpleLogger::info('message over num per rule limit ', ['open_id' => $openId, 'rule_id' => $ruleId]);
                return true;
            }
        }
        //单个规则是否超过
        foreach (self::PER_RULE_MESSAGE_RULE as $value) {
            $key = self::getMessageKey($openId, $value['expire_time'], $ruleId);
            $num = intval($redis->get($key));
            if ($num >= $value['limit']) {
                SimpleLogger::info('message over num per rule limit ', ['expire_time' => $value['expire_time'], 'open_id' => $openId, 'rule_id' => $ruleId]);
                return true;
            }
        }

        //整体规则限制是否超过
        foreach (self::PUSH_MESSAGE_RULE as $v) {
            $key = self::getMessageKey($openId, $v['expire_time']);
            $num = intval($redis->get($key));
            if ($num >= $v['limit']) {
                SimpleLogger::info('message over num limit ', ['expire_time' => $v['expire_time'], 'open_id' => $openId]);
                return true;
            }
        }
        return false;
    }

    /**
     * 是否主动点击推送消息
     * @param int $ruleId
     * @return bool
     */
    private static function isActiveClickPushMessage(int $ruleId): bool
    {
        //首关  邀请好友
        return in_array($ruleId, DictConstants::getValues(DictConstants::MESSAGE_RULE,
            [
                'subscribe_rule_id',
                'life_subscribe_rule_id',
                'invite_friend_rule_id',
                'invite_friend_pay_rule_id',
                'invite_friend_not_pay_rule_id'
            ]));
    }

    /**
     * 记录用户有效时间消息发送的条数
     * @param $openId
     * @param $ruleId
     */
    public static function recordUserMessageRuleLimit($openId, $ruleId)
    {
        //首关  邀请好友  不记录
        if (self::isActiveClickPushMessage($ruleId)){
            return false;
        }

        $redis = RedisDB::getConn();
        $ruleInfo = MessagePushRulesModel::getById($ruleId);
        $timeConfig = json_decode($ruleInfo['time'], true);
        if (!empty($timeConfig['expire'])) {
            $key = self::getMessageKey($openId, $timeConfig['expire'], $ruleId);
            $value = intval($redis->get($key));
            $redis->setex($key, $timeConfig['expire'], intval($value + 1));
        }
        array_map(function ($item) use ($redis, $openId) {
            $key = self::getMessageKey($openId, $item['expire_time']);
            $value = $redis->get($key);
            $redis->setex($key, $item['expire_time'], (int)$value + 1);
        }, self::PUSH_MESSAGE_RULE);

        array_map(function ($item) use ($redis, $openId, $ruleId) {
            $key = self::getMessageKey($openId, $item['expire_time'], $ruleId);
            $value = $redis->get($key);
            $redis->setex($key, $item['expire_time'], (int)$value + 1);
        }, self::PER_RULE_MESSAGE_RULE);
    }

    /**
     * @param array $msgBody
     * @param int $appId
     * 用户与微信交互后的消息处理
     */
    public static function interActionDealMessage(array $msgBody, int $appId = 0)
    {
        $openId = $msgBody['FromUserName'];

        $msgType = $msgBody['MsgType'];

        // 消息推送事件
        if ($msgType == 'event') {
            $event = $msgBody['Event'];
            SimpleLogger::info('student weixin event: ' . $event, []);
            switch ($event) {
                case 'subscribe':
                    // 关注公众号
                    $data = self::preSendVerify($openId, DictConstants::get(DictConstants::MESSAGE_RULE, 'subscribe_rule_id'));
                    if (!empty($data)) {
                        MessageService::realSendMessage($data);
                    }
                    break;
                case 'CLICK':
                    // 点击自定义菜单事件
                    self::menuClickEventHandler($msgBody);
                    break;
                case 'unsubscribe':
                    //取消关注公众号
                    self::clearMessageRuleLimit($openId);
                    WechatService::clearCurrentTag($openId);
                    break;
                default:
                    break;
            }
        }

        $ruleId = self::judgeUserRelateRuleId($openId, $appId);
        if (!empty($ruleId)) {
            $data = self::preSendVerify($openId, $ruleId, $appId);
            if (!empty($data)) {
                self::realSendMessage($data);
            }
        }

        // 记录用户公众号"登录"行为
        WechatService::wechatInteractionLog($openId);
    }



    /**
     * @param array $msgBody
     * 真人用户与微信交互后的消息处理
     */
    public static function lifeInterActionDealMessage(array $msgBody)
    {
        $openId = $msgBody['FromUserName'];

        $msgType = $msgBody['MsgType'];

        // 消息推送事件
        if ($msgType == 'event') {
            $event = $msgBody['Event'];
            SimpleLogger::info('life student weixin event: ' . $event, []);
            switch ($event) {
                case 'subscribe':
                    // 关注公众号
                    $data = self::preSendVerify($openId, DictConstants::get(DictConstants::MESSAGE_RULE, 'life_subscribe_rule_id'));
                    if (!empty($data)) {
                        $data['is_verify'] = false;
                        $data['app_id']  = Constants::REAL_APP_ID;
                        MessageService::realSendMessage($data);
                    }
                    break;
                case 'CLICK':
                    // 点击自定义菜单事件
                    self::MenuClickEventHandler($msgBody, Constants::REAL_APP_ID);
                    break;
                default:
                    break;
            }
        }
    }


    /**
     * 自定义点击事件
     * @param array $msgBody
     * @param int $appId
     * @return bool
     */
    public static function menuClickEventHandler(array $msgBody,int $appId = 0)
    {
        // 自定义KEY事件
        $keyEvent = $msgBody['EventKey'];
        // 事件发送者
        $userOpenId = $msgBody['FromUserName'];
        if ($appId == Constants::REAL_APP_ID){
            switch ($keyEvent) {
                // 推荐好友
                case 'PUSH_MSG_USER_SHARE':
                    WechatService::lifeStudentPushMsgUserShare($userOpenId, DictConstants::get(DictConstants::MESSAGE_RULE, 'invite_friend_rule_id'));
                    break;
            }
        }else{
            switch ($keyEvent) {
                // 付费推荐好友
                case 'STUDENT_PUSH_MSG_SHARE_AWARD':
                    WechatService::studentPushMsgUserShare($userOpenId, DictConstants::get(DictConstants::MESSAGE_RULE, 'invite_friend_pay_rule_id'));
                    break;

                // 未付费推荐好友
                case 'STUDENT_PUSH_MSG_USER_SHARE':
                    WechatService::studentPushMsgUserShare($userOpenId, DictConstants::get(DictConstants::MESSAGE_RULE, 'invite_friend_not_pay_rule_id'));
                    break;
            }
        }
        return false;
    }

    /**
     * 用户绑定后发送消息
     * @param $openId
     * @param $appId
     * @param $userType
     * @param $busiType
     */
    public static function boundWxActionDealMessage($openId, $appId, $userType, $busiType)
    {
        if ($appId != Constants::SMART_APP_ID
            || $userType != DssUserWeiXinModel::USER_TYPE_STUDENT
            || $busiType != DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER) {
            return;
        }
        $ruleId = self::boundJudgeUserRuleId($openId);
        if (empty($ruleId)) {
            SimpleLogger::error('EMPTY RULE ID', [$openId, $appId, $userType, $busiType]);
            return;
        }
        $data = self::preSendVerify($openId, $ruleId, $appId);
        if (empty($data)) {
            SimpleLogger::error("EMPTY SEND DATA", [$openId, $ruleId, $appId]);
            return;
        }
        self::realSendMessage($data);
    }

    /**
     * 年卡支付成功后消息推送
     * @param $studentId
     * @param $packageType
     */
    public static function yearPayActionDealMessage($studentId, $packageType, $appId = null)
    {
        $appId = DssUserWeiXinModel::dealAppId($appId);
        if ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'year_pay_rule_id');
            $userWeiXinInfo = DssUserWeiXinModel::getByUserId($studentId, $appId);
            if (!empty($ruleId) && !empty($userWeiXinInfo['open_id'])) {
                $data = self::preSendVerify($userWeiXinInfo['open_id'], $ruleId, $appId);
                if (!empty($data)) {
                    self::realSendMessage($data);
                }
            }
        }
    }

    /**
     * 当前绑定用户适用哪种规则消息
     * @param $openId
     * @return mixed|null
     */
    private static function boundJudgeUserRuleId($openId)
    {
        $userWx = DssUserWeiXinModel::getByOpenId($openId);
        $studentInfo = !empty($userWx['user_id']) ? DssStudentModel::getById($userWx['user_id']) : null;
        $arr = [
            DssStudentModel::REVIEW_COURSE_NO => null,
            DssStudentModel::REVIEW_COURSE_49 => DictConstants::get(DictConstants::MESSAGE_RULE, 'only_trail_rule_id'),
            DssStudentModel::REVIEW_COURSE_1980 => DictConstants::get(DictConstants::MESSAGE_RULE, 'only_year_rule_id')
        ];
        return $arr[$studentInfo['has_review_course']] ?? null;
    }

    /**
     * 与公众号交互 当前交互用户适用哪类推送
     * @param $openId
     * @return array|mixed|void|null
     */
    public static function judgeUserRelateRuleId($openId, $appId = null)
    {
        $appId = DssUserWeiXinModel::dealAppId($appId);
        //当前用户
        $userInfo = DssUserWeiXinModel::getByOpenId($openId, $appId);
        //当前用户属于何种用户分类
        if (empty($userInfo['user_id'])) {
            return;
        }
        $time = time();
        $studentInfo = DssStudentModel::getById($userInfo['user_id']);
        list($inviteDiffTime, $resultDiffTime) = DictConstants::getValues(DictConstants::MESSAGE_RULE, ['how_long_not_invite', 'how_long_not_result']);
        //所有的体验包id
        $trailPackageIdArr = array_column(DssPackageExtModel::getPackages(['package_type' => DssPackageExtModel::PACKAGE_TYPE_TRIAL]), 'package_id');
        //所有年包id
        $yearPackageIdArr = array_column(DssPackageExtModel::getPackages(['package_type' => DssPackageExtModel::PACKAGE_TYPE_NORMAL]), 'package_id');
        // 新产品中心：
        // 所有体验课产品包ID：
        $v1TrialPackageIdArr = DssErpPackageV1Model::getTrailPackageIds();
        // 所有正式课产品包ID：
        $v1NormalPackageIdArr = DssErpPackageV1Model::getNormalPackageIds();

        //X天内转介绍行为
        $inviteOperateInfo = SharePosterModel::getRecord(['student_id' => $studentInfo['id'], 'create_time[>]' => $time - $inviteDiffTime, 'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED]);
        $dssInviteInfo = DssSharePosterModel::getRecords(['student_id' => $studentInfo['id'], 'create_time[>]' => $time - $inviteDiffTime, 'status' => SharePosterModel::VERIFY_STATUS_QUALIFIED]);
        if ($studentInfo['has_review_course'] == DssStudentModel::REVIEW_COURSE_1980) {
            //转介绍结果（付过费就算)
            $inviteResultInfo = DssGiftCodeModel::refereeBuyCertainPackage($studentInfo['id'], array_merge($yearPackageIdArr, $trailPackageIdArr, $v1TrialPackageIdArr, $v1NormalPackageIdArr), $time - $resultDiffTime);
            if (empty($inviteOperateInfo) && empty($dssInviteInfo) && empty($inviteResultInfo)) {
                //年卡c用户
                return DictConstants::get(DictConstants::MESSAGE_RULE, 'year_user_c_rule_id');
            }
        } else {
            //转介绍结果（30天被推荐人付费年卡）
            $inviteResultInfo = DssGiftCodeModel::refereeBuyCertainPackage($studentInfo['id'], $yearPackageIdArr, $time - $resultDiffTime, DssGiftCodeModel::PACKAGE_V1_NOT);
            $inviteResultInfo2 = DssGiftCodeModel::refereeBuyCertainPackage($studentInfo['id'], $v1NormalPackageIdArr, $time - $resultDiffTime, DssGiftCodeModel::PACKAGE_V1);
            if (empty($inviteOperateInfo) && empty($inviteResultInfo) && empty($inviteResultInfo2)) {
                return $studentInfo['has_review_course'] == DssStudentModel::REVIEW_COURSE_NO ? DictConstants::get(DictConstants::MESSAGE_RULE, 'register_user_c_rule_id') : DictConstants::get(DictConstants::MESSAGE_RULE, 'trail_user_c_rule_id');
            }
        }
        SimpleLogger::info('not message rule apply user ', ['student_id' => $studentInfo['id']]);
        return ;
    }

    /**
     * @param $logId
     * @param $uuidArr
     * @param $employeeId
     * 手动发送消息
     */
    public static function manualPushMessage($logId, $uuidArr, $employeeId)
    {
        $boundUsers = DssUserWeiXinModel::getByUuid($uuidArr);
        $info = MessageManualPushLogModel::getById($logId);
        $content = json_decode($info['data'], true);
        if ($info['type'] == MessagePushRulesModel::PUSH_TYPE_CUSTOMER) {
            // 放到nsq队列中一个个处理
            QueueService::pushWX(
                $boundUsers,
                $content['content_1'] ?? '',
                $content['content_2'] ?? '',
                $content['image'] ?? '',
                $logId,
                $employeeId,
                MessageRecordModel::ACTIVITY_TYPE_MANUAL_PUSH
            );
        } else {
            QueueService::manualPushMessage(
                $boundUsers,
                $logId,
                $employeeId,
                MessageRecordModel::ACTIVITY_TYPE_MANUAL_PUSH
            );
        }
    }

    /**
     * 每月活动消息
     * @param $openId
     * @param $ruleId
     * @param null $appId
     * @return bool
     */
    public static function monthlyEvent($openId, $ruleId = null, $appId = null)
    {
        if (empty($ruleId)) {
            $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'monthly_event_rule_id');
        }
        if (empty($openId) || empty($ruleId)) {
            return false;
        }
        if (!is_array($openId)) {
            $openId = [$openId];
        }
        $appId = DssUserWeiXinModel::dealAppId($appId);
        $messageRule = self::getMessagePushRuleByID($ruleId);
        if ($messageRule['is_active'] != MessagePushRulesModel::STATUS_ENABLE) {
            SimpleLogger::error('RULE NOT ENABLE', [$messageRule]);
            return false;
        }
        $data = [
            'rule_id' => $ruleId,
            'app_id'  => $appId
        ];
        foreach ($openId as $open_id) {
            $data['open_id'] = $open_id;
            if (self::judgeOverMessageRuleLimit($open_id, $ruleId)) {
                continue;
            }
            if ($messageRule['type'] == MessagePushRulesModel::PUSH_TYPE_CUSTOMER) {
                $res = self::pushCustomMessage($messageRule, $data, $appId);
                //推送日志记录
                MessageRecordService::addRecordLog($open_id, MessageRecordModel::ACTIVITY_TYPE_AUTO_PUSH, $ruleId, $res);
            }
            //记录发送限制
            self::recordUserMessageRuleLimit($open_id, $ruleId);
        }
        return true;
    }

    /**
     * 给用户发送微信消息
     * @param $msgBody
     * @throws RunTimeException
     */
    public static function pushWXMsg($msgBody)
    {
        $studentId    = $msgBody['student_id'];
        $openId       = $msgBody['open_id'];
        $guideWord    = $msgBody['guide_word'];
        $shareWord    = $msgBody['share_word'];
        $posterUrl    = $msgBody['poster_url'];
        $activityId   = $msgBody['activity_id'];
        $employeeId   = $msgBody['employee_id'];
        $pushTime     = $msgBody['push_wx_time'];
        $activityType = $msgBody['activity_type'];

        $appId = DssUserWeiXinModel::dealAppId($msgBody['app_id'] ?? 0);
        $wechat = WeChatMiniPro::factory($appId, PushMessageService::APPID_BUSI_TYPE_DICT[$appId]);
        $successFlag = true;

        if (!empty($guideWord)) {
            $res = $wechat->sendText($openId, Util::textDecode($guideWord));
            if (empty($res) || !empty($res['errcode'])) {
                $successFlag = false;
            }
        }

        if (!empty($shareWord)) {
            $res = $wechat->sendText($openId, Util::textDecode($shareWord));
            if (empty($res) || !empty($res['errcode'])) {
                $successFlag = false;
            }
        }

        if (!empty($posterUrl)) {
            $config = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
            $posterImgFile = PosterService::generateQRPosterAliOss(
                $posterUrl,
                $config,
                $studentId,
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'NORMAL_STUDENT_INVITE_STUDENT'),
                [
                    'p' => PosterModel::getIdByPath($posterUrl)
                ]
            );
            if (!empty($posterImgFile['unique'])) {
                $tempMedia = $wechat->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
                if (!empty($tempMedia['media_id'])) {
                    $res = $wechat->sendImage($openId, $tempMedia['media_id']);
                    if (empty($res) || !empty($res['errcode'])) {
                        $successFlag = false;
                    }
                }
            }
        }
        $successNum = $successFlag ? 1 : 0;
        $failNum = $successFlag ? 0 : 1;

        // 发微信的记录
        $record = MessageRecordService::getMsgRecord(
            $activityId,
            $employeeId,
            $pushTime,
            $activityType
        );
        if (empty($record)) {
            MessageRecordService::add(
                MessageRecordModel::MSG_TYPE_WEIXIN,
                $activityId,
                $successNum,
                $failNum,
                $employeeId,
                $pushTime,
                $activityType
            );
        } else {
            MessageRecordService::updateMsgRecord($record['id'], [
                'success_num[+]' => $successNum,
                'fail_num[+]'    => $failNum,
                'update_time'    => time()
            ]);
        }
        //目前仅需要记录消息规则相关
        if (in_array($activityType, [MessageRecordModel::ACTIVITY_TYPE_MANUAL_PUSH])) {
            MessageRecordService::addRecordLog(
                $openId,
                $activityType,
                $activityId,
                $successFlag
            );
        }
    }

    /**
     * 打卡海报审核消息
     * @param $data
     * @return array|bool|mixed
     */
    public static function checkinMessage($data)
    {
        $day    = $data['day'] ?? 0;
        $status = $data['status'] ?? 0;
        $openId = $data['open_id'] ?? '';
        $appId  = $data['app_id'] ?? Constants::SMART_APP_ID;
        if (empty($openId)) {
            SimpleLogger::error('EMPTY OPEN ID', [$data]);
            return false;
        }
        return PushMessageService::notifyUserCustomizeMessage(
            DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'verify_message_config_id'),
            [
                'day'    => $day,
                'status' => DictConstants::get(DictConstants::SHARE_POSTER_CHECK_STATUS, $status),
                'url'    => DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'page_url'),
                'remark' => '【点此消息】查看更多打卡进度',
            ],
            $openId,
            $appId
        );
    }

    /**
     * 年卡召回页面按钮点击短信
     * @param $data
     * @return bool
     */
    public static function sendRecallPageSms($data)
    {
        $uuid    = $data['uuid'];
        $mobile  = $data['mobile'];
        $stage   = $data['stage'];
        $action  = $data['action'];
        $sMobile = $data['sMobile'];
        $sign    = $data['sign'] ?? CommonServiceForApp::SIGN_STUDENT_APP;
        $buyTime = !empty($data['buyTime']) ? date('Y-m-d', $data['buyTime']) : '暂未';
        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $success = $sms->sendWebPageClickNotify($sign, $mobile, $stage, $action, $sMobile, $buyTime);
        if (!$success) {
            SimpleLogger::error('SEND RECALL PAGE SMS FAILED', [$data]);
            return false;
        }
        return true;
    }

    /**
     * 积分任务奖励微信消息
     * @param $msgData
     * @return bool
     */
    public static function sendTaskAwardPointsMessage($msgBody)
    {
        $awardDetailList = ErpUserEventTaskAwardGoldLeafModel::getRecords(['id' => $msgBody['points_award_ids']]);
        if (empty($awardDetailList)) {
            SimpleLogger::info("MessageService::sendTaskAwardPointsMessage>>",['info' => "not_found", "awardDetailInfo" => $awardDetailList]);
            return false;
        }
        $ext = [
            'activity_id' => $msgBody['activity_id'] ?? 0
        ];
        foreach ($awardDetailList as $item) {
            $awardDetailInfo = $item;
            $awardDetailInfo['app_id'] = Constants::SMART_APP_ID;
            $eventTaskInfo = ErpEventTaskModel::getRecord(['id' => $awardDetailInfo['event_task_id']]);
            $eventInfo = ErpEventModel::getRecord(['id' => $eventTaskInfo['event_id']]);
            $awardDetailInfo['type'] = $eventInfo['type'];
            $awardDetailInfo['get_award_uuid'] = $awardDetailInfo['finish_task_uuid'];
            PushMessageService::sendAwardPointsMessage($awardDetailInfo, $ext);
        }
        return true;
    }

    /**
     * 发送审核消息
     * @param $openId
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function sendPosterVerifyMessage($openId, $params = [])
    {
        $appId  = DssUserWeiXinModel::dealAppId($params['app_id'] ?? '');
        $name   = $params['activity_name'] ?? '';
        $status = $params['status'] ?? '';
        $url    = $_ENV["REFERRAL_FRONT_DOMAIN"] . DictConstants::get(DictConstants::REFERRAL_CONFIG, 'refused_poster_url');

        //审核未通过客服消息
        if ($status == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
            $wechat = WeChatMiniPro::factory($appId, PushMessageService::APPID_BUSI_TYPE_DICT[$appId]);
            $content = '您好，您上传的截图审核结束，详情如下：'. PHP_EOL.
                '任务名称：周周领奖'. PHP_EOL.
                '任务内容：'.$name. PHP_EOL.
                '完成情况：未通过'. PHP_EOL.
                '<a href="'.$url.'">【点此消息】查看更多任务记录，或进入“当前活动”重新上传</a>';
            $wechat->sendText($openId, $content);
        }
        return true;
    }

    /**
     * 手动推送周周有奖活动信息
     * @param $data
     * @return bool
     */
    public static function batchPushWeekActivityInfo($data)
    {
        $logId = $data['log_id'] ?? 0;
        $uuidArr = $data['uuids'] ?? [];
        $employeeId = $data['employee_id'] ?? 0;
        if (empty($logId) || empty($uuidArr)) {
            return false;
        }
        self::manualPushMessage($logId, $uuidArr, $employeeId);
        return true;
    }

    /**
     * 发放金叶子微信消息
     *
     * @param $data
     * @return bool
     */
    public static function sendTaskGoldLeafMessage($data)
    {
        $data['url'] = $_ENV['STUDENT_AWARD_POINTS_URL'];

        PushMessageService::sendTaskGoldLeafMessage($data);

        return true;
    }
}