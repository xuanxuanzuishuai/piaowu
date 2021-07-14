<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/7/13
 * Time: 10:52
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Services\DictService;

// use App\Models\CountingActivityModel;
// use App\Models\CountingActivityAwardModel;

class CountingActivitySignModel extends Model
{
    public static $table = "counting_activity_sign";

    const QUALIFIED_STATUS_NO  = 0; // 达标状态：未达标
    const QUALIFIED_STATUS_YES = 1; // 达标状态：已达标

    const AWARD_STATUS_NOT_QUALIFIED = 0; // 奖励领取状态：未达标
    const AWARD_STATUS_PENDING       = 1; // 奖励领取状态：未领取
    const AWARD_STATUS_RECEIVED      = 2; // 奖励领取状态：已领取

    const AWARD_STATUS_DICT = [
        self::AWARD_STATUS_NOT_QUALIFIED => '未达标',
        self::AWARD_STATUS_PENDING       => '待领取',
        self::AWARD_STATUS_RECEIVED      => '已领取',
    ];

    /**
     * 参与记录列表
     * @param array $params
     * @return array
     */
    public static function list($params = [])
    {
        // @TODO: table name and model const
        $s = DssStudentModel::getTableNameWithDb();
        $cas = self::getTableNameWithDb();
        // $ca = CountingActivityModel::getTableNameWithDb();
        // $caa = CountingActivityAwardModel::getTableNameWithDb();
        $ca = 'operation_dev.counting_activity';
        $caa = 'operation_dev.counting_activity_award';
        $a = OperationActivityModel::getTableNameWithDb();

        list($where, $map) = self::formatWhere($params);
        list($page, $count) = Util::formatPageCount($params);
        // @TODO: Model const
        $join = "
        INNER JOIN $s s ON cas.student_id = s.id
        INNER JOIN $ca ca ON cas.op_activity_id = ca.op_activity_id
        INNER JOIN $a a on a.id = ca.op_activity_id
        LEFT JOIN $caa caa on caa.sign_id = cas.id AND caa.type = " . 2;
        $limit = Util::limitation($page, $count);
        $sql = "
        SELECT %s
        FROM $cas cas
        %s
        WHERE %s
        ";

        $db = self::dbRO();
        $listField = "s.id,s.uuid,s.name,s.mobile,cas.continue_nums,cas.cumulative_nums,cas.op_activity_id,a.name,cas.award_status,cas.award_time,caa.unique_id";
        $totalField = "count(cas.id) as total";
        $total = $db->queryAll(sprintf($sql, $totalField, $join, $where), $map);
        $total = $total[0]['total'] ?? 0;
        if (empty($total)) {
            return [[], 0];
        }
        $list = $db->queryAll(sprintf($sql, $listField, $join, $where) . $limit, $map);
        return [$list, $total];
    }

    /**
     * 参与记录搜索条件格式化
     * @param array $params
     * @return array
     */
    public static function formatWhere($params = [])
    {
        $where = [' 1 '];
        $map = [];
        if (!empty($params['name'])) {
            $where[] = "s.name like :name";
            $map[':name'] = Util::sqlLike($params['name']);
        }
        if (!empty($params['student_id'])) {
            $where[] = "cas.student_id = :student_id";
            $map[':student_id'] = $params['student_id'];
        }
        if (!empty($params['student_uuid'])) {
            $where[] = "s.uuid = :student_uuid";
            $map[':student_uuid'] = $params['student_uuid'];
        }
        if (!empty($params['mobile'])) {
            $where[] = "s.mobile = :mobile";
            $map[':mobile'] = $params['mobile'];
        }
        if (!empty($params['cumulative_nums_s'])) {
            $where[] = "cas.cumulative_nums >= :cumulative_nums_s";
            $map[':cumulative_nums_s'] = $params['cumulative_nums_s'];
        }
        if (!empty($params['cumulative_nums_e'])) {
            $where[] = "cas.cumulative_nums <= :cumulative_nums_e";
            $map[':cumulative_nums_e'] = $params['cumulative_nums_e'];
        }
        if (!empty($params['continue_nums_s'])) {
            $where[] = "cas.continue_nums >= :continue_nums_s";
            $map[':continue_nums_s'] = $params['continue_nums_s'];
        }
        if (!empty($params['continue_nums_e'])) {
            $where[] = "cas.continue_nums <= :continue_nums_e";
            $map[':continue_nums_e'] = $params['continue_nums_e'];
        }
        if (!empty($params['award_time_s'])) {
            $where[] = "cas.award_time >= :award_time_s";
            $map[':award_time_s'] = $params['award_time_s'];
        }
        if (!empty($params['award_time_e'])) {
            $where[] = "cas.award_time <= :award_time_e";
            $map[':award_time_e'] = $params['award_time_e'];
        }
        if (!empty($params['activity_id'])) {
            $where[] = "cas.activity_id = :activity_id";
            $map[':activity_id'] = $params['activity_id'];
        }
        if (!empty($params['activity_name'])) {
            $where[] = "cas.activity_name like :activity_name";
            $map[':activity_name'] = Util::sqlLike($params['activity_name']);
        }
        if (!empty($params['award_status'])) {
            $where[] = "cas.award_status = :award_status";
            $map[':award_status'] = $params['award_status'];
        }
        return [implode(' AND ', $where), $map];
    }

    /**
     * 用户参与详情
     * @param int $userId
     * @param array $params
     * @return array
     */
    public static function getUserRecords($userId = 0, $params = [])
    {
        if (empty($userId)) {
            return [];
        }
        $ca = 'operation_dev.counting_activity';
        $caa = 'operation_dev.counting_activity_award';
        $student = DssStudentModel::getById($userId);
        if (empty($student)) {
            return [];
        }
        $weekActivityDetail = SharePosterModel::getWeekPosterList(['user_id' => $userId]);
        $field = "cas.id,cas.op_activity_id,ca.name,cas.create_time,cas.qualified_status,cas.award_time,
                  caa.unique_id";
        $table = self::$table;
        $join = "
        INNER JOIN $ca ca ON cas.op_activity_id = ca.op_activity_id
        LEFT JOIN $caa caa on caa.sign_id = cas.id AND caa.type = " . 2;
        $where = 'student_id = :student_id';
        $map = [':student_id' => $userId];
        $order = " ORDER BY cas.id DESC ";
        $sql = "
        SELECT %s
        FROM %s cas
        %s
        WHERE %s
        ";
        $db = MysqlDB::getDB();
        // @TODO:filter data unique
        // get logistics data
        $signRecordDetails = [];
        $records = $db->queryAll(sprintf($sql, $field, $table, $join, $where) . $order, $map);
        foreach ($records as $item) {
            if (!empty($signRecordDetails[$item['id']])) {
                $signRecordDetails[$item['id']] = $item;
            }
        }
        // @TODO: format data
        $statusDict = DictService::getTypeMap(Constants::DICT_TYPE_SHARE_POSTER_CHECK_STATUS);
        array_walk($weekActivityDetail, function ($item) use ($statusDict) {
            $item['create_time'] = Util::formatTimestamp($item['create_time']);
            $item['status_name'] = $statusDict[$item['poster_status']];
            return $item;
        });
        return [$student, $weekActivityDetail, $signRecordDetails];
    }
}
