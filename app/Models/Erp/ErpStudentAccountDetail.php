<?php


namespace App\Models\Erp;


class ErpStudentAccountDetail extends  ErpModel
{
    public static $table = 'erp_student_account_detail';

    const TYPE_ENTER = 1; // 入账

    const SUB_TYPE_GOLD_LEAF = 3002; //金叶子积分

    /**
     * 批量获取金叶子总数-根据用户id
     * @return array|null
     */
    public static function getUserRewardTotal($studentIds)
    {
        $table       = self::$table;
        $operateType = self::TYPE_ENTER;
        $subType     = self::SUB_TYPE_GOLD_LEAF;
        $studentIds  = implode(",", $studentIds);

        $sql         = "SELECT 
                    student_id,
                    sum(num) as total
                FROM
                    {$table}
                WHERE operate_type = {$operateType}
                  AND sub_type = {$subType}
                  AND student_id IN ({$studentIds})
                  GROUP BY student_id 
                ";

        return self::dbRO()->queryAll($sql);
    }



}