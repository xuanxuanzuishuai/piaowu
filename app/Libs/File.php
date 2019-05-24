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
}