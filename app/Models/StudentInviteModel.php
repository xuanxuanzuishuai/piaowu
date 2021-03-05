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

class StudentInviteModel extends Model
{
    const REFEREE_TYPE_STUDENT = 1; // 推荐人类型：学生
    const REFEREE_TYPE_AGENT = 4; // 推荐人类型：代理商
    public static $table = "student_invite";


    /**
     * 查询推荐人的被推荐人首次购买指定类型课包的时间数据
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
        $si = self::$table;
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
        if (!empty($where['app_id'])) {
            $condition .= ' AND si.app_id = :app_id ';
            $map[':app_id'] = $where['app_id'];
        }
        if (!empty($where['referee_type'])) {
            $condition .= ' AND si.referee_type = :referee_type ';
            $map[':referee_type'] = $where['referee_type'];
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
}
