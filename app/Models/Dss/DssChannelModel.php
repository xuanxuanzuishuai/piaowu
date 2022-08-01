<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/27
 * Time: 14:52
 */
namespace App\Models\Dss;

class DssChannelModel extends DssModel
{
    public static $table = "channel";
    
    const STATUS_ENABLE = '1';

    /**
     * 获取渠道和父级渠道的信息
     * @param $channelIds
     * @return array
     */
    public static function getChannelAndParentInfo($channelIds)
    {
        $table = self::$table;
        return self::dbRO()->select($table . '(c1)', [
            "[><]$table(c2)" => ["c1.parent_id" => "id"],
        ], [
            'c1.id',
            'c1.name',
            'c1.app_id',
            'c1.parent_id',
            'c2.name(parent_name)',
        ], [
            'c1.id' => $channelIds
        ]);
    }
}