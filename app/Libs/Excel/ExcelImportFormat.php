<?php

namespace App\Libs\Excel;

use App\Libs\Exceptions\RunTimeException;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportFormat
{
    /**
     * 格式化处理excel表格导入数据，返回数组
     * @param bool $firstRowAsKey 第一行的列值是否作为数组key使用：true是 false不是
     * @param array $targetKeysList 第一行的列值是作为数组key使用时，这个参数指定合法列集合，如果excel表格第一行的列值不满足抛出异常
     * @return array
     * @throws RunTimeException
     */
    public static function formatImportExcelData(bool $firstRowAsKey = true, array $targetKeysList = []): array
    {
        if (empty($_FILES['filename'])) {
            throw new RuntimeException(["upload_filename_is_required"]);
        }
        $extension = strtolower(pathinfo($_FILES['filename']['name'])['extension']);
        if (!in_array($extension, ['xls', 'xlsx'])) {
            throw new RuntimeException(["must_excel_format"]);
        }
        //临时文件完整存储路径
        $filename = '/tmp/' . md5(mt_rand() . time()) . '.' . $extension;
        if (move_uploaded_file($_FILES['filename']['tmp_name'], $filename) == false) {
            throw new RuntimeException(["move_file_fail"]);
        }
        $fileType = ucfirst(pathinfo($filename)["extension"]);
        //读取数据
        try {
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($filename);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            if ($firstRowAsKey === true) {
                $fillData = [];
                $titleKeys = array_shift($sheetData);
                if (empty($sheetData)) {
                    return [];
                }
                foreach ($sheetData as $sv) {
                    $fillData[] = array_combine($titleKeys, $sv);
                }
                return self::filterSheetData($fillData,$targetKeysList);
            } else {
                return $sheetData;
            }
        } catch (Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        }
    }

    /**
     * @param $sheetData
     * @param $filterKeys
     * @return array|array[]
     */
    private static function filterSheetData($sheetData, $filterKeys): array
    {
        $res = [];
        return array_map(function ($sv) use ($filterKeys, &$res) {
            foreach ($filterKeys as $fk) {
                $res[$fk] = $sv[$fk];
            }

            return $res;
        }, $sheetData);


    }
}