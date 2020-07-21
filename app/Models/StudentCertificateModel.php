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
    //毕业证书(学生名字/毕业日期)x轴和y轴位置偏移量以及图片长宽
    const STUDENT_CERTIFICATE_WATER_POSITION_CONFIG = [
        'student_name' => ['x' => 230, 'y' => 350, 'size' => 21, 'type' => 'fangzhengkaiti', 'color' => '5a5b58'],
        'certificate_date' => ['x' => 180, 'y' => 104, 'size' => 14, 'type' => 'fangzhengkaiti', 'color' => '5a5b58'],
        'img_height' => 595,
        'img_width' => 842,
    ];

}