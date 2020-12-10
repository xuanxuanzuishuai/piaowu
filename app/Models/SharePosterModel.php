<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/10
 * Time: 3:52 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class SharePosterModel extends Model
{
    public static $table = "share_poster";
    //审核状态 1待审核 2合格 3不合格
    const VERIFY_STATUS_WAIT = 1;
    const VERIFY_STATUS_QUALIFIED = 2;
    const VERIFY_STATUS_UNQUALIFIED = 3;

    //节点状态
    const NODE_STATUS_LOCK = 1;//待解锁
    const NODE_STATUS_ING = 2;//进行中
    const NODE_STATUS_VERIFY_ING = 3;//审核中
    const NODE_STATUS_VERIFY_UNQUALIFIED = 4;//审核不通过
    const NODE_STATUS_HAVE_SIGN = 5;//已打卡
    const NODE_STATUS_EXPIRED = 6;//已过期
    const NODE_STATUS_UN_PLAY = 7;//未练琴

    /**
     * 获取打卡活动节点截图数据
     * @param $studentId
     * @param $nodeId
     * @return array|null
     */
    public static function signInNodePoster($studentId, $nodeId)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT
                id,
                student_id,
                image_path,
                verify_status,
                verify_time,
                verify_reason,
                ext ->> '$.node_id' AS node_id,
                ext ->> '$.valid_time' AS valid_time 
            FROM
                " . self::$table . " 
            WHERE
                student_id = " . $studentId . " 
                AND ext ->> '$.node_id' in( " . $nodeId . ")";
        return $db->queryAll($sql);

    }
}