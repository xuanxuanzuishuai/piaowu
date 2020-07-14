<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/15
 * Time: 11:14 AM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\StudentCertificateModel;

class StudentCertificateService
{
    /**
     * 保存学生证书数据
     * @param $studentId
     * @param $savePath
     * @param $operatorId
     * @return bool
     * @throws RunTimeException
     */
    public static function addData($studentId, $savePath, $operatorId)
    {
        $insertData = [
            'student_id' => $studentId,
            'save_path' => $savePath,
            'operator_id' => $operatorId,
            'type' => StudentCertificateModel::CERTIFICATE_TYPE_COLLECTION_GRADUATION,
            'status' => StudentCertificateModel::CERTIFICATE_STATUS_ABLE,
            'create_time' => time()
        ];
        $insertId = StudentCertificateModel::insertRecord($insertData, false);
        if (empty($insertId)) {
            throw new RunTimeException(['insert_failure']);
        }
        return true;
    }
}
