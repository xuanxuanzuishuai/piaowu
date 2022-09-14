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
    public static function createDir ($subDir, $filename='')
    {
        $hashPath = '';
        if($filename){
            // 文件夹 hash
            $d1 = substr($filename, 0, 2);
            $d2 = substr($filename, 2, 2);
            $hashPath = "{$d1}/{$d2}";
        }
        $subPath = "{$subDir}/{$hashPath}";
        $fullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/{$subPath}";

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        return [$subPath, $hashPath, $fullPath];
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

    /**
     * 删除目录文件
     * @param $filePath
     */
    public static function delDirFile($filePath)
    {
        if(is_dir($filePath)){
            if ($handle = opendir($filePath)) {
                while (false !== ($item = readdir($handle))) {
                    if ($item != "." && $item != "..") {
                        if (is_dir("$filePath/$item")) {
                            //递归调用删除目录文件
                            self::delDirFile("$filePath/$item");
                        } else {
                            //删除文件
                            unlink("$filePath/$item");
                        }
                    }
                }
                //关闭文件句柄
                closedir($handle);
                //删除目录
                rmdir($filePath);
            }
        }
    }
}