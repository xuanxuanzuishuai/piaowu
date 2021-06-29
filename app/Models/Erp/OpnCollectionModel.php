<?php

namespace App\Models\Erp;


class OpnCollectionModel extends ErpModel
{
    public static $table = 'opn_collection';
    //  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '套课类型， 1：可报名；2：待上线；3：制作中',
    const TYPE_SIGN_UP = 1;
    const TYPE_READY_ONLINE = 2;
    const TYPE_IN_PRODUCTION = 3;

    /**
     * 获取曲谱教材信息
     * @param $collectionIds
     * @return array
     */
    public static function getCollectionDataById($collectionIds)
    {
        //从库对象
        $db = self::dbRO();
        return $db->select(self::$table,
            [
                '[>]' . OpnArtistModel::$table => ['artist_id' => 'id']
            ],
            [
                self::$table . '.id',
                self::$table . '.name',
                self::$table . '.author',
                self::$table . '.press',
                OpnArtistModel::$table . '.name(artist_name)',
            ],
            [
                self::$table . '.id' => $collectionIds,
                self::$table . '.type' => self::TYPE_SIGN_UP,

            ]);
    }
}
