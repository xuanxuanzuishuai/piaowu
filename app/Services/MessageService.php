<?php
/**
 * User: lizao
 * Date: 2020/9/23
 * Time: 15:08 PM
 */

namespace App\Services;

use App\Libs\RedisDB;
use App\Models\MessagePushRulesModel;
use App\Models\MessageManualPushLogModel;
use App\Libs\Util;
use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Models\PosterModel;
use App\Models\WeChatConfigModel;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MessageService
{
    const PUSH_MESSAGE_RULE_KEY = 'push_message_rules';

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
        self::updatePushRuleCache($params['id']);
        $res = MessagePushRulesModel::updateRecord($params['id'], $updateData, false);
        if (is_null($res)) {
            return 'update_failure';
        }
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
                }
            }
        }
        return [MessagePushRulesModel::PUSH_TYPE_DICT, $data];
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
        $encodeList = ['content_1', 'content_2', 'image', 'first_sentence', 'activity_detail', 'activity_desc', 'remark', 'link'];
        foreach ($encodeList as $key) {
            if (isset($data[$key]) && !Util::emptyExceptZero($data[$key]) && is_string($data[$key])) {
                $data[$key] = Util::textEncode($data[$key]);
            }
        }
        $insertData['data'] = json_encode($data);
        return MessageManualPushLogModel::insertRecord($insertData, false);
    }
}