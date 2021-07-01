<?php


namespace App\Models;


use App\Libs\Util;

class SourceMaterialModel extends Model
{
    const NOT_ENABLED_STATUS = 1;//未启用

    const ENABLED_STATUS = 2;//已启用

    public static $table = "source_material";

    public static $enableStatusLists = [
        '0' => '全部',
        '1' => '未启用',
        '2' => '已启用'
    ];

    /**
     * 素材库列表
     * @param $params
     * @param $limit
     * @return array
     */
    public static function sourceList($params, $limit)
    {
        $where = ['status' => 1];
        if (!empty($params['id'])) {
            $where['id'] = $params['id'];
        }
        if (!empty($params['type'])) {
            $where['type'] = $params['type'];
        }
        if (!empty($params['enable_status'])) {
            $where['enable_status'] = $params['enable_status'];
        }
        if (!empty($params['name'])) {
            $where['name'] = $params['name'];
        }
        if (!empty($params['image_path'])) {
            $where['image_path[~]'] = Util::sqlLike($params['image_path']);
        }
        $total = self::getCount($where);
        if ($total <= 0) {
            return [[], 0];
        }
        if (!empty($limit)) {
            $where['LIMIT'] = $limit;
        }
        $where['ORDER'] = ['id' => 'DESC'];
        $list = self::getRecords($where);
        return [$list, $total];
    }
}
