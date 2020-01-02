<?php


namespace App\Models;


/**
 * Class WeChatCSModel
 * @package App\Models
 */
class WeChatCSModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_CANCEL = 0;
    public static $table = "wechat_cs";

    /**
     * @param $id
     * @return int|null
     */
    public static function setWeChatCS($id)
    {
        $now = time();
        self::batchUpdateRecord(['status' => self::STATUS_CANCEL, 'update_time' => $now], ['status' => self::STATUS_NORMAL]);
        return self::updateRecord($id, ['status' => self::STATUS_NORMAL, 'update_time' => $now], false);
    }
}