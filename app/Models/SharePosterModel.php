<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:05 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;

class SharePosterModel extends Model
{
    public static $table = 'share_poster';
    //审核状态:1待审核 2合格 3不合格
    const STATUS_WAIT_CHECK = 1;
    const STATUS_QUALIFIED = 2;
    const STATUS_UNQUALIFIED = 3;


    public static function posterList($params)
    {
        $where = " WHERE 1 = 1 ";
        $map = [];
        if (!empty($params['student_mobile'])) {
            $where .= " AND s.mobile = :student_mobile ";
            $map[':student_mobile'] = $params['student_mobile'];
        }
        if (!empty($params['student_name'])) {
            $where .= " AND s.name like :student_name ";
            $map[':student_name'] = "%" . $params['student_name'] . "%";
        }

        if (!empty($params['activity_id'])) {
            $where .= " AND sp.activity_id = :activity_id ";
            $map[':activity_id'] = $params['activity_id'];
        }

        if (!empty($params['poster_status'])) {
            $where .= " AND sp.status = :poster_status ";
            $map[':poster_status'] = $params['poster_status'];
        }

        if (!empty($params['start_time'])) {
            $where .= " AND sp.create_time >= :start_time";
            $map[':start_time'] = strtotime($params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $where .= " AND sp.create_time <= :end_time";
            $map[':end_time'] = strtotime($params['end_time']);
        }

        $join = "
        INNER JOIN
    " . StudentModel::$table . " s ON s.id = sp.student_id
        INNER JOIN
    " . ReferralActivityModel::$table . " ra ON ra.id = sp.activity_id
        LEFT JOIN
    " . EmployeeModel::$table . " e ON e.id = sp.operator_id ";

        $totalCount = MysqlDB::getDB()->queryAll(
            "SELECT count(sp.id) count FROM " . self::$table . " sp $join $where ",
            $map);
        $totalCount = $totalCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }

        $sql = "
        SELECT
    sp.id,
    sp.student_id,
    sp.activity_id,
    sp.img_url,
    sp.status poster_status,
    sp.create_time,
    sp.check_time,
    sp.operator_id,
    sp.reason,
    sp.remark,
    s.name student_name,
    s.mobile,
    ra.name activity_name,
    e.name operator_name
FROM
    " . self::$table . " sp ";


        $order = " ORDER BY id DESC ";
        $limit = Util::limitation($params['page'], $params['count']);
        $sql = $sql . $join . $where . $order . $limit;
        $posters = MysqlDB::getDB()->queryAll($sql, $map);

        return [$posters, $totalCount];
    }

    /**
     * 截图用户数据
     * @param $posterIds
     * @return array|null
     */
    public static function getPostersByIds($posterIds)
    {

        $sql = "
        SELECT
    sp.id,
    sp.student_id,
    sp.activity_id,
    sp.status poster_status,
    sp.award_id,
    ra.name activity_name,
    uw.open_id
FROM
    " . self::$table . " sp
    INNER JOIN
    " . ReferralActivityModel::$table . " ra ON ra.id = sp.activity_id
    LEFT JOIN
    " . UserWeixinModel::$table . " uw ON uw.user_id = sp.student_id
        AND uw.user_type = " . UserWeixinModel::USER_TYPE_STUDENT . "
        AND uw.status = " . UserWeixinModel::STATUS_NORMAL . "
        AND uw.busi_type = " . UserWeixinModel::BUSI_TYPE_STUDENT_SERVER . "
        AND uw.app_id = " . UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT . "
WHERE sp.id in ( " . implode(',', $posterIds) . " )
    AND sp.status = " . self::STATUS_WAIT_CHECK;

        $posters = MysqlDB::getDB()->queryAll($sql);
        return $posters;
    }
}