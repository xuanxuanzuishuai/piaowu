<?php

namespace App\Models;

class CopyManageModel extends Model
{
    public static $table = 'copy_manage';
    //类型:1智能业务线金叶子商城规则说明 2真人业务线魔法石商城规则说明
    const RULE_DESC_TYPE_DSS_GOLD_LEAF = 1;
    const RULE_DESC_TYPE_REAL_MAGIC_STONE = 2;

    //文案缓存key
    const RULE_DESC_CACHE_KEY_PRI = 'rule_desc_';
    const RULE_DESC_CACHE_KEY_EXPIRE = 2592000;

    /**
     * 文案列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     */
    public static function list($where, $page, $limit)
    {
        $data = [
            'total_count' => 0,
            'list' => [],
        ];
        $totalCount = self::getCount($where);
        if (empty($totalCount)) {
            return $data;
        }
        $data['total_count'] = $totalCount;
        $where['ORDER'] = ['id' => 'DESC'];
        $where['LIMIT'] = [($page - 1) * $limit, $limit];
        $data['list'] = CopyManageModel::getRecords($where, ['id', 'content', 'title']);
        return $data;
    }
}
