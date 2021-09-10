<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/7
 * Time: 15:16
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;

class ActivityCenterModel extends Model
{
    public static $table = "activity_center";
    //状态：1 上架 2 下架
    const STATUS_OK = 1;
    const STATUS_NO = 2;

    const STATUS_DICT = [
        self::STATUS_OK => '上架',
        self::STATUS_NO => '下架'
    ];

    //渠道：1 公众号 2 APP
    const CHANNEL_WX = 1;
    const CHANNEL_APP = 2;

    //渠道
    const CHANNEL_DICT = [
        self::CHANNEL_WX  => '公众号',
        self::CHANNEL_APP => 'APP',
    ];

    /**
     * 新增
     * @param array $data
     * @return bool
     */
    public static function add(array $data): bool
    {
        //记录代理商基础数据
        $id = self::insertRecord($data);
        if (empty($id)) {
            SimpleLogger::error('insert activity center data error', $data);
            return false;
        }

        return true;
    }

    /**
     * 获取列表
     * @param string $where
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function list(string $where, int $page, int $pageSize): array
    {
        $a = self::$table;
        $e = EmployeeModel::$table;

        $data = ['count' => 0, 'list' => []];
        $db   = MysqlDB::getDB();

        $total = $db->queryAll("SELECT count(id) as count FROM {$a} a WHERE {$where}");

        if (empty($total[0]['count'])) {
            return $data;
        }
        $data['count'] = $total[0]['count'];

        $limit = Util::limitation($page, $pageSize);

        $data['list'] = $db->queryAll("SELECT a.*,e.name as e_name FROM {$a} a 
LEFT JOIN {$e} e ON a.create_by = e.id
WHERE {$where} ORDER BY a.weight DESC,a.id DESC {$limit}
");
        return $data;
    }



}