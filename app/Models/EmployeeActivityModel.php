<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:37 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;

class EmployeeActivityModel extends Model
{
    public static $table = 'employee_activity';
    // 活动状态 0未启用 1启用 2禁用
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE  = 1;
    const STATUS_DOWN    = 2;


    //海报的位置/大小信息
    public static $activityPosterConfig = [
        'qr_x'          => 533,
        'qr_y'          => 92,
        'poster_width'  => 750,
        'poster_height' => 1334,
        'qr_width'      => 154,
        'qr_height'     => 154
    ];

    public static function insert($data)
    {
        $now = time();
        return self::insertRecord([
            'name'            => $data['name'],
            'status'          => self::STATUS_DISABLE,
            'start_time'      => is_numeric($data['start_time']) ? $data['start_time'] : strtotime($data['start_time']),
            'end_time'        => is_numeric($data['end_time']) ? $data['end_time'] : strtotime($data['end_time']),
            'rules'           => $data['rules'],
            'banner'          => $data['banner'],
            'figure'          => $data['figure'] ?? '',
            'invite_text'     => $data['invite_text'],
            'poster'          => $data['poster'],
            'remark'          => $data['remark'] ?? '',
            'employee_share'  => $data['employee_share'],
            'employee_poster' => $data['employee_poster'],
            'app_id'          => $data['app_id'] ?? UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            'create_time'     => $now,
        ]);
    }

    /**
     * 修改活动
     * @param $data
     * @param $activityID
     * @return int|null
     */
    public static function modify($data, $activityID)
    {
        $data = self::formatData($data);
        return self::updateRecord($activityID, $data);
    }

    private static function formatData($data)
    {
        $res = ['update_time' => time()];
        foreach ($data as $key => $value) {
            $res[$key] = $value;
        }
        return $res;
    }

    /**
     * 活动列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = " where 1=1 ";
        $map = [];
        if (!empty($params['name'])) {
            $where .= " and name like :name ";
            $map[':name'] = "%{$params['name']}%";
        }
        if (isset($params['status']) && is_numeric($params['status'])) {
            $where .= " and status = :status ";
            $map[':status'] =  $params['status'];
        }
        if (!empty($params['status']) && is_array($params['status'])) {
            $status = implode(',', $params['status']);
            $where .= " and status in ( {$status} ) ";
        }
        $table = self::$table;

        $totalCount = MysqlDB::getDB()->queryAll("select count(id) count from {$table}" . $where, $map);
        $totalCount = $totalCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }

        $order = ' order by id desc ';
        $limit = Util::limitation($params['page'] ?? 1, $params['count'] ?? $_ENV['PAGE_RESULT_COUNT']);

        $results = MysqlDB::getDB()->queryAll("
            select *
            from " . $table . $where . $order . $limit, $map);

        return [$results, $totalCount];
    }
}