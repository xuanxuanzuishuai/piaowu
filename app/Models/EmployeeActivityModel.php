<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:37 PM
 */

namespace App\Models;

use App\Libs\Exceptions\RunTimeException;
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

    const ACT_TIME_STATUS_PENDING     = 1;
    const ACT_TIME_STATUS_IN_PROGRESS = 2;
    const ACT_TIME_STATUS_OVER        = 3;

    //海报的位置/大小信息
    public static $activityPosterConfig = [
        'qr_x'          => 225,
        'qr_y'          => 121,
        'poster_width'  => 750,
        'poster_height' => 1334,
        'qr_width'      => 300,
        'qr_height'     => 300
    ];

    public static function insert($data)
    {
        //同步增加op_activity总表
        $now = time();
        $id = OperationActivityModel::insertRecord(
            [
                'name' => $data['name'],
                'create_time' => $now,
                'update_time' => $now,
                'app_id' => $data['app_id'] ?? UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            ]
        );
        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }
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
            'op_activity_id'  => $id,
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
        //同步更新op_activity总表
        $opActivityId = EmployeeActivityModel::getRecord(['id' => $activityID], 'op_activity_id');
        if (isset($data['name'])) {
            OperationActivityModel::updateRecord($opActivityId, ['name' => $data['name']]);
        }
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
     * 为了便于统计所有的活动会放到一个总表，当前这个活动会有一个对应的总表的id
     * @param $activityId
     * @return mixed
     */
    public static function getEmployeeActivityRelateOpActivityId($activityId)
    {
        return EmployeeActivityModel::getRecord(['id' => $activityId], 'op_activity_id');
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
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $limit = Util::limitation($params['page'] ?? 1, $params['count'] ?? $_ENV['PAGE_RESULT_COUNT']);

        $results = MysqlDB::getDB()->queryAll("
            select *
            from " . $table . $where . $order . $limit, $map);

        return [$results, $totalCount];
    }
}