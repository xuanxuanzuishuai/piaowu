<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * 作用：FTP操作类( 拷贝、移动、删除文件/创建目录 )
 * Date: 2018/11/11
 * Time: 5:42 PM
 */
namespace App\Libs;

class FTP
{
    public $off; // 返回操作状态(成功/失败)
    public $conn_id; // FTP连接
    /**
     * 方法：FTP连接
     * @FTP_HOST -- FTP主机
     * @FTP_PORT -- 端口
     * @FTP_USER -- 用户名
     * @FTP_PASS -- 密码
     */
//    const FTP_HOST='www.php2.cc';
//    const FTP_PORT='21';
//    const FTP_USER='jackey';
//    const FTP_PASS='810706080';

    function __construct()
    {
        $this->conn_id = @ftp_connect($_ENV['FTP_HOST'],$_ENV['FTP_PORT']) or die("FTP服务器连接失败");
        @ftp_login($this->conn_id,$_ENV['FTP_USER'],$_ENV['FTP_PASS']) or die("FTP服务器登陆失败");
        @ftp_pasv($this->conn_id,1); // 打开被动模拟
    }
    /**
     * 方法：上传文件
     * @path -- 本地路径
     * @newpath -- 上传路径
     * @type -- 若目标目录不存在则新建
     */
    function up_file($path,$newpath,$type=true)
    {
        if($type) $this->dir_mkdirs($newpath);
        $this->off = @ftp_put($this->conn_id,$newpath,$path,FTP_BINARY);
        if(!$this->off) echo "文件上传失败，请检查权限及路径是否正确！";
    }
    /**
     * 方法：移动文件
     * @path -- 原路径
     * @newpath -- 新路径
     * @type -- 若目标目录不存在则新建
     */
    function move_file($path,$newpath,$type=true)
    {
        if($type) $this->dir_mkdirs($newpath);
        $this->off = @ftp_rename($this->conn_id,$path,$newpath);
        if(!$this->off) echo "文件移动失败，请检查权限及原路径是否正确！";
    }
    /**
     * 方法：复制文件
     * 说明：由于FTP无复制命令,本方法变通操作为：下载后再上传到新的路径
     * @path -- 原路径
     * @newpath -- 新路径
     * @type -- 若目标目录不存在则新建
     */
    function copy_file($path,$newpath,$type=true)
    {
        $downpath = tempnam(getcwd()."/", "tmp_"); // 创建唯一的临时文件
        $this->off = @ftp_get($this->conn_id,$downpath,$path,FTP_BINARY);// 下载
        if(!$this->off) echo "文件复制失败，请检查权限及原路径是否正确！";
        $this->up_file($downpath,$newpath,$type);
        unlink($downpath);    // 删除临时文件
    }
    /**
     * 方法：删除文件
     * @path -- 路径
     */
    function del_file($path)
    {
        $this->off = @ftp_delete($this->conn_id,$path);
        return $this->off;
    }
    /**
     * 方法：生成目录
     * @path -- 路径
     */
    function dir_mkdirs($path)
    {
        $path_arr = explode('/',$path); // 取目录数组
        $file_name = array_pop($path_arr); // 弹出文件名
        $path_div = count($path_arr); // 取层数
        foreach($path_arr as $val) // 创建目录
        {
            if(@ftp_chdir($this->conn_id,$val) == FALSE)
            {
                $tmp = @ftp_mkdir($this->conn_id,$val);
                if($tmp == FALSE)
                {
                    echo "目录创建失败，请检查权限及路径是否正确！";
                    exit;
                }
                @ftp_chdir($this->conn_id,$val);
            }
        }
        for($i=1;$i<=$path_div;$i++)         // 回退到根
        {
            @ftp_cdup($this->conn_id);
        }
    }

    /*
     * 文件下载
     */
    function download($select_file)
    {
        $file = explode('/',$select_file);
        $file = end($file);
        $tmpfile = tempnam(getcwd()."/", ""); // 创建唯一的临时文件
        $tmpfile = $tmpfile.$file;
        if(ftp_get($this->conn_id, $tmpfile, $select_file, FTP_BINARY)) {    // 下载指定的文件到临时文件
            $this->close();    // 关闭连接
        }
        return $tmpfile;
    }

    /**
     * 方法：关闭FTP连接
     */
    function close()
    {
        @ftp_close($this->conn_id);
    }
}