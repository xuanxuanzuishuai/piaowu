<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/15
 * Time: 11:14 AM
 */

namespace App\Models;
class StudentCertificateModel extends Model
{
    //表名称
    public static $table = "student_certificate";
    //证书类型：1班级毕业证书
    const CERTIFICATE_TYPE_COLLECTION_GRADUATION = 1;
    //证书状态: 0无效 1有效
    const CERTIFICATE_STATUS_DISABLE = 0;
    const CERTIFICATE_STATUS_ABLE = 1;
}