<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/8
 * Time: 11:53
 */
namespace App\Models\Dss;

use App\Libs\Constants;

class DssGiftCodeDetailedModel extends DssModel
{
    public static $table = "gift_code_detailed";


    public static function studentDetails()
    {
        $limit       = 2;
        $table       = self::$table;
        $status      = DssUserWeiXinModel::STATUS_NORMAL;
        $appId       = Constants::SMART_APP_ID;
        $busiType    = Constants::SMART_WX_SERVICE;

        $weiXinTable  = DssUserWeiXinModel::$table;
        $studentTable = DssStudentModel::$table;
        $packageType = DssStudentModel::REVIEW_COURSE_49;

        $sql = " SELECT
                    c.id,
                    b.open_id,
                    c.thumb
                FROM {$table} a
                INNER JOIN {$weiXinTable} b ON a.apply_user = b.user_id
                INNER JOIN {$studentTable} c ON a.apply_user = c.id

                WHERE
                 a . package_type = {$packageType} 
                AND b . status = {$status}
                AND b . app_id = {$appId}
                AND b . busi_type = {$busiType}
                ORDER BY a.id DESC LIMIT {$limit}
        ";
        return self::dbRO()->queryAll($sql);
    }

}