<?php
/**
 * PhpSpreadsheet xml操作类
 */

namespace App\Libs;


use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Spreadsheet
{
    /**
     * 获取表格所有数据
     * @param $activeSheetIndex
     * @param $localFilePath
     * @param bool $delEmptyLine
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public static function getActiveSheetData($localFilePath, $activeSheetIndex = 0, $delEmptyLine = true)
    {
        $fileType = ucfirst(pathinfo($localFilePath)["extension"]);
        $reader = IOFactory::createReader($fileType);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($localFilePath);
        $spreadsheet->setActiveSheetIndex($activeSheetIndex);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        //删除空行
        if ($delEmptyLine == true) {
            foreach ($sheetData as $key => $item) {
                $delEmptyData = array_diff($item,[null]);
                if (empty($delEmptyData)) {
                    unset($sheetData[$key]);
                }
            }
        }
        return $sheetData;
    }

    /**
     * 创建一个新的xml文件
     * @param $localFilePath
     * @param array $data
     * @param int $activeSheetIndex
     * @param string $activeSheetName
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public static function createXml($localFilePath,$title = [], $data = [], $activeSheetIndex = 0, $activeSheetName = 'Sheet1')
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->setActiveSheetIndex($activeSheetIndex);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($activeSheetName);

        $titleColumn = 1;
        foreach ($title as $v) {
            $sheet->setCellValueByColumnAndRow($titleColumn, 1, $v);
            $titleColumn++;
        }
        $row = 2;
        foreach ($data as $item) {
            $column = 1;
            foreach ($item as $value) {
                // 单元格内容写入
                // $sheet->setCellValueByColumnAndRow($column, $row, $value);
                $sheet->setCellValueExplicitByColumnAndRow($column, $row, $value, DataType::TYPE_STRING);
                $column++;
            }
            $row++;
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($localFilePath);
        return true;
    }
}