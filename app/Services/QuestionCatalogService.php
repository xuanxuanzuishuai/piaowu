<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/25
 * Time: 下午12:01
 */

namespace App\Services;
use App\Libs\RedisDB;
use App\Models\QuestionCatalogModel;

class QuestionCatalogService
{
    private static $cacheQuestionCatalogKey = 'question_catalog';

    public static function selectAll()
    {
        $conn = RedisDB::getConn();
        $catalog = $conn->get(self::$cacheQuestionCatalogKey);
        if(!empty($catalog)) {
            return json_decode($catalog, 1);
        }

        $records = QuestionCatalogModel::getRecords(['id[>]' => 0], ['id', 'catalog', 'parent_id', 'type'], false);

        $conn->set(self::$cacheQuestionCatalogKey, json_encode($records, 1));
        $conn->expire(self::$cacheQuestionCatalogKey, 7 * 86400);

        return $records;
    }
}