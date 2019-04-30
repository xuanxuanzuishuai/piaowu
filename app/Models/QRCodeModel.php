<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/29
 * Time: 5:48 PM
 */

namespace App\Models;


use App\Libs\AliOSS;
use App\Libs\MysqlDB;
use App\Libs\QRcode;
use App\Libs\SimpleLogger;

class QRCodeModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 0;

    const REFEREE_TYPE_TEACHER = 1;
    const REFEREE_TYPE_STUDENT = 2;
    const REFEREE_TYPE_EMPLOYEE = 3;
    const REFEREE_TYPE_ORG = 4;

    const TYPE_ORG_BIND_TEACHER = 1;
    const TYPE_ORG_BIND_STUDENT = 2;

    const LANDING_TYPE_WX = 1;
    const LANDING_TYPE_WEB = 2;

    const API = [
        self::TYPE_ORG_BIND_TEACHER => '/Bind/bind/add',
        self::TYPE_ORG_BIND_STUDENT => '/Bind/student/add',
    ];

    protected static $table = 'qr_code';

    public static function get($params)
    {
        $where = [
            'type' => $params['type'],
            'org_id' => $params['org_id'],
            'channel_id' => $params['channel_id'] ?? 0,
            'status' => self::STATUS_NORMAL
        ];

        if (!empty($params['referee_type']) && !empty($params['referee_id'])) {
            $where['referee_type'] = $params['referee_type'];
            $where['referee_id'] = $params['referee_id'];
        } else {
            $where['referee_type'] = null;
            $where['referee_id'] = null;
        }

        $db = MysqlDB::getDB();
        $qr = $db->get(self::$table, '*', $where);
        return $qr;
    }

    public static function add($params, $genImage = false)
    {
        $host = self::getHost($params['landing_type']);
        $api = self::API[$params['type']];

        SimpleLogger::debug("ldkfjldkfjdfkldjfldkfj", ['$host' => $host]);

        if (empty($host) || empty($api)) {
            return null;
        }

        $queryData = [];
        if (!empty($params['org_id'])) {
            $queryData[] = 'org_id=' . $params['org_id'];
        }

        if (!empty($params['referee_type']) && !empty($params['referee_id'])) {
            $queryData[] = 'referee_type=' . $params['referee_type'];
            $queryData[] = 'referee_id=' . $params['referee_id'];
        }

        if (!empty($params['channel_id'])) {
            $queryData[] = 'channel_id=' . $params['channel_id'];
        }

        if (!empty($queryData)) {
            $query = '?' . implode('&', $queryData);
        } else {
            $query = '';
        }

        $url = $host . $api . $query;

        $time = time();
        if ($genImage) {
            list($filePath, $fileName) = self::genImage($url, $time);
            if (!empty($filePath)) {
                $envName = $_ENV['ENV_NAME'] ?? 'dev';
                $imageUrl = $envName . '/qr/' . $fileName;
                AliOSS::uploadFile($imageUrl, $filePath);
            } else {
                $imageUrl = '';
            }
        } else {
            $imageUrl = $params['qr_image'] ?? '';
        }

        $text = self::genText($url);

        $qrData = [
            'type' => $params['type'],
            'channel_id' => $params['channel_id'] ?? 0,
            'org_id' => $params['org_id'] ?? null,
            'referee_type' => $params['referee_type'] ?? null,
            'referee_id' => $params['referee_id'] ?? null,
            'url' => $url,
            'qr_image' => $imageUrl,
            'qr_text' => json_encode($text),
            'create_time' => $time,
            'status' => self::STATUS_NORMAL
        ];
        return self::insertRecord($qrData);
    }

    public static function getHost($landingType)
    {
        if ($landingType == self::LANDING_TYPE_WX) {
            return DictModel::getKeyValue('qr_landing_type', $landingType);
        }
        return '';
    }

    public static function setStatus($id, $status)
    {
        if ($status != self::STATUS_NORMAL && $status != self::STATUS_DISABLE) {
            return null;
        }

        $db = MysqlDB::getDB();
        return $db->updateGetCount(self::$table, ['status' => $status], ['id' => $id]);
    }

    public static function genImage($content, $time)
    {
        $outputPath = self::getOutputPath();
        if (empty($outputPath)) {
            return [null];
        }

        $fileName = md5($content . $time) . ".png";
        $outfile = $outputPath . $fileName;
        QRcode::png($content, $outfile, 0, 4, 2);

        return [file_exists($outfile) ? $outfile : null, $fileName];
    }


    public static function updateImage($id, $url)
    {

    }

    public static function genText($content)
    {
        return QRcode::text($content, false, 0, 4, 2);
    }

    public static function getOutputPath()
    {
        $default = '/tmp/qr/';
        if (!file_exists($default)) {
            if (!mkdir($default)) {
                return null;
            }
        }
        return $default;
    }
}