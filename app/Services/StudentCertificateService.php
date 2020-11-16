<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/15
 * Time: 11:14 AM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\AliOSS;
use App\Libs\Util;
use App\Models\StudentCertificateModel;
use App\Models\StudentCertificateTemplateModel;
use App\Models\StudentModel;

class StudentCertificateService
{
    /**
     * 保存学生证书数据
     * @param $studentId
     * @param $studentName
     * @param $certificateDate
     * @param $operatorId
     * @param $type
     * @return bool
     * @throws RunTimeException
     */
    public static function add($studentId, $studentName, $certificateDate, $operatorId, $type = StudentCertificateModel::CERTIFICATE_TYPE_COLLECTION_GRADUATION)
    {
        $studentInfo = StudentModel::getById($studentId);
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_ids_error']);
        }
        $res['mobile'] = Util::hideUserMobile($studentInfo['mobile']);
        //生成毕业证图片
        $waterMarkConfig = DictService::getKeyValue(Constants::STUDENT_CERTIFICATE_BASE_IMG, 'water_mark_config');
        $savePath = self::createCertificateWaterMarkAliOss($studentId,
            DictService::getKeyValue(Constants::STUDENT_CERTIFICATE_BASE_IMG, 'graduate'),
            json_decode($waterMarkConfig, true),
            [
                'student_name' => $studentName,
                'certificate_date' => date('Y年m月', $certificateDate),
            ]);
        $data = StudentCertificateModel::getRecord(['student_id' => $studentId, 'type' => $type], ['id'], false);
        if ($data) {
            $updateData = [
                'save_path' => $savePath,
                'operator_id' => $operatorId,
                'update_time' => time()
            ];
            $affectRows = StudentCertificateModel::updateRecord($data['id'], $updateData, false);
        } else {
            $insertData = [
                'student_id' => $studentId,
                'save_path' => $savePath,
                'operator_id' => $operatorId,
                'type' => $type,
                'status' => StudentCertificateModel::CERTIFICATE_STATUS_ABLE,
                'create_time' => time()
            ];
            $affectRows = StudentCertificateModel::insertRecord($insertData, false);
        }
        if (empty($affectRows)) {
            throw new RunTimeException(['update_date_failed']);
        }
        $res['certificate_url'] = AliOSS::signUrls($savePath);
        return $res;
    }

    /**
     * 生成学生毕业证水印图片
     * @param $studentId
     * @param $baseImg
     * @param $config
     * @param $waterData
     * @return array|string
     * @throws RunTimeException
     */
    private static function createCertificateWaterMarkAliOss($studentId, $baseImg, $config, $waterData)
    {
        //底图图片资源
        $baseImgAliOssFileExists = AliOSS::doesObjectExist($baseImg);
        if (empty($baseImgAliOssFileExists)) {
            throw new RunTimeException(['base_img_oss_file_is_not_exists']);
        }
        //添加文字水印
        $waterMark = [];
        foreach ($waterData as $k => $v) {
            if (!empty($v)) {
                $waterImgEncode = str_replace(["+", "/"], ["-", "_"], base64_encode($v));
                $tmpWaterMark = [
                    "text_" . $waterImgEncode,
                    "x_" . $config[$k]['x'],
                    "y_" . $config[$k]['y'],
                    "size_" . $config[$k]['size'],
                    "type_" . base64_encode($config[$k]['type']),
                    "color_" . $config[$k]['color'],
                    "g_sw",//插入的基准位置以左下角作为原点
                ];
                $waterMark[] = implode(",", $tmpWaterMark);
            }
        }
        if (empty($waterMark)) {
            throw new RunTimeException(['water_mark_data_is_required']);
        }
        $imgSize = [
            "w_" . $config['img_width'],
            "h_" . $config['img_height'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';
        //通过添加水印生成海报宣传图
        $resImgFile = AliOSS::signUrls($baseImg, "", "", "", false, $waterMark, $imgSizeStr);
        //上传证书图片到阿里oss
        $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/" . $studentId . ".png";
        $tmpFile = file_get_contents($resImgFile);
        file_put_contents($tmpFileFullPath, $tmpFile);
        chmod($tmpFileFullPath, 0755);
        $savePath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_CERTIFICATE . '/' . md5($studentId) . ".png";
        AliOSS::uploadFile($savePath, $tmpFileFullPath);
        //删除临时文件
        unlink($tmpFileFullPath);
        //返回数据
        return $savePath;
    }

    /**
     * 获取证书模板列表
     * @param $type
     * @return array
     */
    public static function certificateTemplate($type)
    {
        $data = [];
        $list = StudentCertificateTemplateModel::getRecords(
            ['type' => $type, 'status' => StudentCertificateTemplateModel::STATUS_ABLE],
            ['save_path'],
            false);
        if (!empty($list)) {
            array_map(function ($lv) use (&$data) {
                $data[] = AliOSS::signUrls($lv['save_path']);
            }, $list);
        }
        return $data;
    }
}
