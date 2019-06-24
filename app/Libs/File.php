<?php
/**
 * 文件操作
 * @author yuxuan
 * @since 2019-07-07 16:15:45
 */

namespace App\Libs;
class File
{

    /**
     * 创建文件夹
     * @param string $subDir 存储的子目录
     * @param string $filename 文件名, 注：文件名通常为hash过的字符串
     * @return array [文件存储的子路径, 哈希后的文件夹路径, 本地存储的完整文件夹路径]
     */
    public static function createDir ($subDir, $filename)
    {
        // 文件夹 hash
        $d1 = substr($filename, 0, 2);
        $d2 = substr($filename, 2, 2);

        $hashPath = "{$d1}/{$d2}";
        $subPath = "{$subDir}/{$hashPath}";
        $fullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/{$subPath}";

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        return [$subPath, $hashPath, $fullPath];
    }

    /**
     * 导出文件
     * @param $fileName
     * @param $data
     */
    public static function exportFile($fileName, $data)
    {
        ini_set('max_execution_time', 0);

        // 开启缓冲区
        ob_end_clean();
        ob_start();

        // 设置header头
        header("Content-type: text/csv; charset=GB18030");
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        // 获取文件句柄直接输出到浏览器
        $fp = fopen('php://output', 'w+');

        // 写入表头
        foreach ($data as $key => $value) {
            fputcsv($fp, $value);
        }

        // 清空缓冲区
        ob_flush();
        flush();
        ob_end_clean();
        fclose($fp) or die("Can't close php://output");
    }

    /**
     * 导入文件，得到数据内容
     * @param $files
     * @return array
     */
    public static function importFile($files)
    {
        $data = [];
        fgetcsv($files); // skip table head
        while (!feof($files)) {
            $line = fgetcsv($files);
            if (is_array($line)){
                foreach ($line as $key => $value){
                    $encode = mb_detect_encoding($value, array('UTF-8','GB2312','GBK'));
                    if ($encode != 'UTF-8') {
                        $line[$key] = iconv('gb2312', 'utf-8', $value);
                    } else {
                        $line[$key] = $value;
                    }
                }
                $data[] = $line;
            }
        }
        fclose($files);
        return $data;
    }
}