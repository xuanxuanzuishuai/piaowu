<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/11/17
 * Time: 11:14 AM
 */

namespace App\Models;
class StudentCertificateTemplateModel extends Model
{
    //表名称
    public static $table = "student_certificate_template";
    //证书类型：1勤奋榜2王者榜3卓越奖4结业证书
    const CERTIFICATE_TYPE_DILIGENCE_LIST= 1;
    const CERTIFICATE_TYPE_KING_LIST = 2;
    const CERTIFICATE_TYPE_EXCELLENCE_AWARD= 3;
    const CERTIFICATE_TYPE_COLLECTION_GRADUATION = 4;
    //状态: 0无效 1有效
    const STATUS_DISABLE = 0;
    const STATUS_ABLE = 1;
}