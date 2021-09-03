<?php
/**
 * ch op库 qr_info 表
 */

namespace App\Models;

use App\Libs\CHDB;
use App\Libs\DictConstants;
use App\Libs\KeyErrorRC4Exception;
use App\Libs\RC4;
use App\Libs\SimpleLogger;

class QrInfoOpCHModel
{
    public static $table = 'qr_info_all';

    /**
     * 获取二维码id的信息
     * @param $qrSign
     * @param array $fields
     * @return array
     */
    public static function getQrInfoBySign($qrSign, array $fields = []): array
    {
        if (empty($qrSign)) {
            return [];
        }
        $fieldStr = '*';
        if (!empty($fields)) {
            $fieldStr = '`' . implode('`,`', $fields) . '`';
        }
        $sql  = "SELECT " . $fieldStr . " FROM " . self::$table;
        if (is_array($qrSign)) {
            $sql .= " WHERE qr_sign in (:qr_sign)";
        } else {
            $sql .= " WHERE qr_sign=:qr_sign";
            $qrSign = (string)$qrSign;
        }
        $db   = CHDB::getDB(CHDB::OP);
        $data = $db->queryAll($sql, ['qr_sign' => $qrSign]);
        return $data ?? [];
    }

    /**
     * 获取二维码id的信息
     * @param $qrId
     * @param array $fields
     * @return array|mixed
     */
    public static function getQrInfoById($qrId, array $fields = [])
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
                'qr_type',
                'create_type',
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
     * @return false
     */
    public static function saveQrInfo($qrData): bool
    {
        $time       = date("Y-m-d H:i:s");
        $insertData = [];
        foreach ($qrData as $v) {
            if (!self::checkSaveQrData($v)) {
                return false;
            }
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
                'qr_type'      => isset($v['qr_type']) ? intval($v['qr_type']) : 0,
                'create_type'  => isset($v['create_type']) ? intval($v['create_type']) : 0,
                'create_time'  => $time,
            ];

            try {
                $qrInfo['qr_ticket'] = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $qrInfo['user_type'] . "_" . $qrInfo['user_id']);
            } catch (KeyErrorRC4Exception $e) {
                SimpleLogger::info("create qr ticket", [$qrInfo]);
                return false;
            }

            // 删除已独立的字段，保留剩余字段
            foreach ($qrInfo as $f) {
                unset($v[$f]);
            }
            $qrInfo['qr_data'] = json_encode($v);

            $insertData[] = $qrInfo;
        }

        if (empty($insertData)) {
            SimpleLogger::info("insert_data_empty", [$insertData]);
            return false;
        }
        $db = CHDB::getDB(CHDB::OP);
        $res = $db->insert(self::$table, $insertData, array_keys($insertData[0]));
        if (empty($res)) {
            SimpleLogger::error("saveQrInfo error", ['qr_data' => $qrData]);
            return false;
        }
        SimpleLogger::info("saveQrInfo save qr info", ['qr_data' => $insertData]);
        return true;
    }

    /**
     * 检查保存的信息是否正确
     * @param $qrData
     * @return bool
     */
    public static function checkSaveQrData($qrData): bool
    {
        if (empty($qrData['qr_id'])) {
            SimpleLogger::error("checkSaveQrData error qr_id empty", [$qrData]);
            return false;
        }
        if (empty($qrData['user_id'])) {
            SimpleLogger::info("checkSaveQrData error user_id empty", [$qrData]);
            return false;
        }
        if (!isset($qrData['qr_path']) || !isset($qrData['qr_type'])) {
            SimpleLogger::info("checkSaveQrData error user_id empty", [$qrData]);
            return false;
        }
        if (empty($qrData['qr_path']) && $qrData['qr_type'] != DictConstants::get(DictConstants::MINI_APP_QR, 'qr_type_none')) {
            SimpleLogger::info("checkSaveQrData error user_id empty", [$qrData]);
            return false;
        }
        if (!empty($qrData['qr_path']) && $qrData['qr_type'] == DictConstants::get(DictConstants::MINI_APP_QR, 'qr_type_none')) {
            SimpleLogger::info("checkSaveQrData error user_id empty", [$qrData]);
            return false;
        }
        return true;
    }
}
