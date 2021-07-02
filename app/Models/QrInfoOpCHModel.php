<?php
/**
 * ch op库 qr_info 表
 */

namespace App\Models;


use App\Libs\CHDB;
use App\Libs\RC4;
use App\Libs\SimpleLogger;

class QrInfoOpCHModel
{
    public static $table = 'qr_info';

    /**
     * 获取二维码id的信息
     * @param $qrSign
     * @param array $fields
     * @return array|mixed
     */
    public static function getQrInfoBySign($qrSign, array $fields = [])
    {
        $fieldStr = '*';
        if (!empty($fields)) {
            $fieldStr = '`' . implode('`,`', $fields) . '`';
        }
        $sql  = "SELECT " . $fieldStr . " FROM " . self::$table . " WHERE qr_sign=:qr_sign";
        $db   = CHDB::getDB(CHDB::OP);
        $data = $db->queryAll($sql, ['qr_sign' => $qrSign]);
        return $data[0] ?? [];
    }

    /**
     * 获取二维码id的信息
     * @param $qrTicket
     * @param string[] $fields
     * @return array|mixed
     */
    public static function getQrInfoById($qrId, $fields = [])
    {
        if (!empty($fields)) {
            $fieldStr = '`' . implode('`,`', $fields) . '`';
        } else {
            $fieldStr = '`' . implode('`,`', [
                'qr_id',
                'qr_path',
                'qr_sign',
                'qr_ticket',
                'user_id',
                'user_type',
                'channel_id',
                'landing_type',
                'activity_id',
                'employee_id',
                'poster_id',
                'app_id',
                'busies_type',
                'user_status',
                'qr_data',
                ]) . '`';
        }
        $sql  = "SELECT " . $fieldStr . " FROM " . self::$table;
        if (is_array($qrId)) {
            $sql .= " WHERE qr_id in (:qr_id)";
        } else {
            $sql .= " WHERE qr_id=:qr_id";
            $qrId = (string)$qrId;
        }
        $db   = CHDB::getDB(CHDB::OP);
        $data = $db->queryAll($sql, ['qr_id' => $qrId]);
        return $data[0] ?? [];
    }

    /**
     * 保存二维码信息
     * @param $qrData
     * @return bool
     */
    public static function saveQrInfo($qrData)
    {
        $time       = date("Y-m-d H:i:s");
        $fields = [
            'qr_id',
            'qr_path',
            'qr_sign',
            'user_id',
            'user_type',
            'channel_id',
            'landing_type',
            'activity_id',
            'employee_id',
            'poster_id',
            'app_id',
            'busies_type',
            'user_status',
            'create_time',
            'qr_ticket',
            'qr_data',
        ];
        $insertData = [];
        foreach ($qrData as $v) {
            if (empty($v['qr_id'])) {
                SimpleLogger::error("saveQrInfo error qr_id empty", ['qr_info' => $v]);
                continue;
            }
            // 注意数字字段顺序需要和$fields顺序一致
            $qrInfo              = [
                'qr_id'        => $v['qr_id'],
                'qr_path'      => $v['qr_path'] ?? '',
                'qr_sign'      => $v['qr_sign'] ?? '',
                'user_id'      => isset($v['user_id']) ? intval($v['user_id']) : 0,
                'user_type'    => isset($v['user_type']) ? intval($v['user_type']) : 0,
                'channel_id'   => isset($v['channel_id']) ? intval($v['channel_id']) : 0,
                'landing_type' => isset($v['landing_type']) ? intval($v['landing_type']) : 0,
                'activity_id'  => isset($v['activity_id']) ? intval($v['activity_id']) : 0,
                'employee_id'  => isset($v['employee_id']) ? intval($v['employee_id']) : 0,
                'poster_id'    => isset($v['poster_id']) ? intval($v['poster_id']) : 0,
                'app_id'       => isset($v['app_id']) ? intval($v['app_id']) : 0,
                'busies_type'  => isset($v['busies_type']) ? intval($v['busies_type']) : 0,
                'user_status'  => isset($v['user_status']) ? intval($v['user_status']) : 0,
                'create_time'  => $time,
            ];
            $qrInfo['qr_ticket'] = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $qrInfo['user_type'] . "_" . $qrInfo['user_id']);

            // 删除已独立的字段，保留剩余字段
            foreach ($fields as $f) {
                if ($f == 'qr_data') {
                    continue;
                }
                unset($v[$f]);
            }
            $qrInfo['qr_data'] = json_encode($v);

            $insertData[]        = $qrInfo;
        }

        if (empty($insertData)) {
            return false;
        }
        $db     = CHDB::getDB(CHDB::OP);
        $res    = $db->insert(self::$table, $insertData, $fields);
        if (empty($res)) {
            SimpleLogger::error("saveQrInfo error", ['qr_data' => $qrData]);
        }
        return true;
    }
}