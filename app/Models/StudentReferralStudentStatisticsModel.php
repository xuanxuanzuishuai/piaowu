<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/13
 * Time: 19:52
 */


namespace App\Models;


use App\Libs\MysqlDB;

class StudentReferralStudentStatisticsModel extends Model
{
    public static $table = 'student_referral_student_statistics';
    // 进度:0注册 1体验 2年卡(定义与代理转介绍学生保持一致)
    const STAGE_REGISTER = AgentUserModel::STAGE_REGISTER;
    const STAGE_TRIAL = AgentUserModel::STAGE_TRIAL;
    const STAGE_FORMAL = AgentUserModel::STAGE_FORMAL;

    /**
     * 获取推荐人指定起始时间到当前的指定数量的推荐人
     * @param $refereeId
     * @param $stage
     * @param $createTime
     * @param $limit
     * @return array
     */
    public static function getStudentList ($refereeId, $stage, $createTime, $limit) {
        $db = MysqlDB::getDB();
        $statisTable = StudentReferralStudentStatisticsModel::$table;
        $detailTable = StudentReferralStudentDetailModel::$table;
        $list = $db->select(
            $statisTable,
            [
                "[>]" . $detailTable  => ['student_id' => 'student_id'],
            ],
            [
                $detailTable.'.id',
                $detailTable.'.student_id',
            ],
            [
                $statisTable . '.referee_id' => $refereeId,
                $detailTable . '.stage' => $stage,
                $detailTable . '.create_time[>=]' => $createTime,
                'ORDER' => [$detailTable.'.create_time' => 'ASC'],
                'LIMIT' => $limit,
            ]
        );
        return $list;
    }
}
