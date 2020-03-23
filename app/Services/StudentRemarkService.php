<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/3/20
 * Time: 3:24 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Models\StudentRemarkImagesModel;
use App\Models\StudentRemarkModel;

class StudentRemarkService
{
    public static function addRemark($studentId, $remarkStatus, $remark, $images, $employeeId)
    {
        $now = time();
        $remarkId = StudentRemarkModel::addRemark([
            'student_id' => $studentId,
            'remark' => $remark,
            'remark_status' => $remarkStatus,
            'create_time' => $now,
            'employee_id' => $employeeId
        ]);

        StudentService::updateStudentRemark($studentId, $remarkId, $remarkStatus);

        if (!empty($images)) {
            $imgs = [];
            foreach ($images as $image) {
                $imgs[] = [
                    'student_remark_id' => $remarkId,
                    'image_url' => $image,
                    'status' => 1,
                    'create_time' => $now
                ];
            }
            StudentRemarkImagesModel::addRemarkImages($imgs);
        }
    }

    public static function getRemarkList($studentId, $page, $count)
    {
        $total = StudentRemarkModel::getRemarksCount($studentId);
        if ($total == 0) {
            return [[], 0];
        }

        $remarks = StudentRemarkModel::selectRemarks($studentId, $page, $count);
        foreach ($remarks as &$remark) {
            $remark['remark_status_str'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_REMARK_STATUS, $remark['remark_status']);
            $remark['create_time'] = empty($remark['create_time']) ? '-' : date('Y-m-d H:i:s', $remark['create_time']);

            $images = StudentRemarkImagesModel::getRemarkImages($remark['id']);
            if (!empty($images)) {
                foreach ($images as &$image) {
                    $image['image_url'] = AliOSS::signUrls($image['image_url']);
                }
            }
            $remark['images'] = $images;
        }

        return [$remarks, $total];
    }
}