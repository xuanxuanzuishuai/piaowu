<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssErpPackageGoodsV1Model;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssGoodsV1Model;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use App\Models\Erp\ErpUserEventTaskModel;

class StudentInviteModel extends Model
{
    const REFEREE_TYPE_STUDENT = 1; // 推荐人类型：学生
    const REFEREE_TYPE_AGENT = 4; // 推荐人类型：代理商
    public static $table = "student_invite";


    /**
     * 获取指定推荐人推荐的所有学员，首次购买指定类型课包的时间数据
     * @param $where
     * @param int $type
     * @param int $limit
     * @return array|null
     */
    public static function getRefereeBuyData($where, $type = DssStudentModel::REVIEW_COURSE_1980, $limit = 1000)
    {
        if (empty($where)) {
            return [];
        }
        $si = StudentReferralStudentStatisticsModel::getTableNameWithDb();
        $gc = DssGiftCodeModel::getTableNameWithDb();
        $p  = DssErpPackageV1Model::getTableNameWithDb();
        $pg = DssErpPackageGoodsV1Model::getTableNameWithDb();
        $g  = DssGoodsV1Model::getTableNameWithDb();
        $c  = DssCategoryV1Model::getTableNameWithDb();
        $condition = ' 1=1 ';
        $map = [];
        if (!empty($where['referee_id'])) {
            $condition .= ' AND si.referee_id = :referee_id ';
            $map[':referee_id'] = $where['referee_id'];
        }
        // 查询所有被推荐人：
        $sql = "
        SELECT si.student_id
        FROM $si si
        WHERE $condition";
        $db = MysqlDB::getDB();
        $allUser = $db->queryAll($sql, $map);
        $allUser = array_column($allUser, 'student_id');
        if (empty($allUser)) {
            return [];
        }
        // 查询被推荐人购买信息，按创建时间正序排序
        // 带购买次数query_order标记
        $sql = "
        SELECT *
        FROM ( 
            SELECT gc.id,
                   gc.buyer,
                   gc.create_time,
                   ROW_NUMBER() over (PARTITION BY gc.buyer
                                      ORDER BY gc.id) AS query_order
           FROM  $gc gc
           INNER JOIN  $p p ON gc.bill_package_id = p.id
           INNER JOIN  $pg pg ON pg.package_id = p.id
           INNER JOIN  $g g ON g.id = pg.goods_id
           INNER JOIN  $c c ON c.id = g.category_id
           WHERE gc.buyer IN (".implode(',', $allUser).")
             AND p.sale_shop = ".DssErpPackageV1Model::SALE_SHOP_AI_PLAY."
             AND c.sub_type = ".($type == DssStudentModel::REVIEW_COURSE_1980 ? DssCategoryV1Model::DURATION_TYPE_NORMAL : DssCategoryV1Model::DURATION_TYPE_TRAIL)."
           ORDER BY gc.create_time
          ) t
        WHERE t.query_order = 1 AND t.create_time >= ".$where['create_time']." LIMIT $limit";
        return self::dbRO()->queryAll($sql);
    }

    /**
     * 查询推荐人是否已经完成了一次性的额外奖励任务
     * @param $where
     * @param $taskId
     * @return array
     */
    public static function getRefereeExtraTask($where, $taskId)
    {
        if (empty($taskId)) {
            return [];
        }
        $uet = ErpUserEventTaskModel::getTableNameWithDb();
        $ueta = ErpUserEventTaskAwardModel::getTableNameWithDb();
        $s = DssStudentModel::getTableNameWithDb();
        $es = ErpStudentModel::getTableNameWithDb();
        $sql = "
            SELECT 
                uet.id,
                uet.user_id,
                uet.status
            FROM {$ueta} ueta
            INNER JOIN {$uet} uet ON ueta.uet_id = uet.id
            INNER JOIN {$es} es on ueta.user_id = es.id
            INNER JOIN {$s} s ON s.uuid = es.uuid
            WHERE s.id = :user_id
            AND uet.create_time >= :create_time
            AND uet.event_task_id = :task_id
        ";
        $map = [
            ':user_id' => $where['referee_id'],
            ':task_id' => $taskId,
            ':create_time' => $where['create_time'],
        ];
        return self::dbRO()->queryAll($sql, $map);
    }
}
