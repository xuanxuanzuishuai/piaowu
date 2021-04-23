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
