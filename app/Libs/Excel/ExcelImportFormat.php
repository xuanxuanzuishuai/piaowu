<?php

namespace App\Libs\Excel;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelImportFormat
{
    //表格输出方式类型：1保存到服务器 2输出到浏览器
    const OUTPUT_TYPE_SAVE_FILE = 1;
    const OUTPUT_TYPE_BROWSER_EXPORT = 2;

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
                //表格第一列和目标key做交集检测
                $keyDiff = array_diff($targetKeysList, $titleKeys);
                if (!empty($keyDiff)) {
                    SimpleLogger::info("targetKeys and titleKeys diff", [$keyDiff]);
                    return [];
                }
                foreach ($sheetData as $sv) {
                    $fillData[] = array_combine($titleKeys, $sv);
                }
                return self::filterSheetData($fillData, $targetKeysList);
            } else {
                return $sheetData;
            }
        } catch (Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        } finally {
            unlink($filename);
        }
    }

    /**
     * 过滤表格数据
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

    /**
     * 生成表格
     * @param $dataResult
     * @param $title
     * @param $fileName
     * @param $outputType
     * @param string $fileType
     * @return false|string|void
     */
    public static function createExcelTable($dataResult, $title, $fileName, $outputType, string $fileType = 'Xlsx')
    {
        try {
            if (empty($dataResult)) {
                return false;
            }
            // 实例化 Spreadsheet 对象
            $spreadsheet = new Spreadsheet();
            // 获取工作薄
            $worksheet = $spreadsheet->getActiveSheet();
            //设置单元格内容 遍历表头的数组设置到单元格上
            foreach ($title as $key => $value) {
                $worksheet->setCellValueByColumnAndRow($key + 1, 1, $value);
            }
            //设置工作表标题名称
            $worksheet->setTitle('工作表格1');
            //遍历数据填充到对应的单元格
            $row = 2; //从第二行开始
            foreach ($dataResult as $item) {
                $column = 1; //列从第一格开始
                foreach ($item as $value) {
                    $worksheet->setCellValueByColumnAndRow($column, $row, $value);
                    $column++;
                }
                $row++;  //每一列填充完，+1
            }
            if ($outputType == self::OUTPUT_TYPE_SAVE_FILE) {
                $tmpSavePath = '/tmp/' . $fileName . '.' . $fileType;
                self::outputSaveTmpFile($spreadsheet, $tmpSavePath, $fileType);
                return $tmpSavePath;
            } else {
                self::outputClientBrowser($spreadsheet, $fileType);
            }
        } catch (Exception $e) {
            SimpleLogger::error("create excel table error", ['err_msg' => $e->getMessage()]);
        }
    }

    /**
     * 下载到服务器
     * @param $spreadsheet
     * @param $savePath
     * @param $fileType
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    private static function outputSaveTmpFile($spreadsheet, $savePath, $fileType)
    {
        $writer = IOFactory::createWriter($spreadsheet, $fileType);
        $writer->save($savePath);
    }

    /**
     * 输出到浏览器
     * @param $spreadsheet
     * @param $fileType
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    private static function outputClientBrowser($spreadsheet, $fileType)
    {
        $writer = IOFactory::createWriter($spreadsheet, $fileType); //按照指定格式生成Excel文件
        $writer->save('php://output');
    }
}