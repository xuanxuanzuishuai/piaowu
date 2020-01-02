<?php


namespace App\Services;


use App\Models\WeChatCSModel;

class WeChatCSService
{
    /**
     * 获取当前微信客服信息（唯一）
     * @return mixed
     */
    public static function getWeChatCS()
    {
        return WeChatCSModel::getRecord(['status'=> WeChatCSModel::STATUS_NORMAL]);
    }

    /**
     * 设置当前微信客服
     * @param $id
     * @return int|null
     */
    public static function setWeChatCS($id)
    {
        return WeChatCSModel::setWeChatCS($id);
    }

    /**
     * @param $name
     * @param $url
     * @return int|mixed|string|null
     */
    public static function addWeChatCS($name,$url)
    {
        return WeChatCSModel::insertRecord(['name' => $name, 'qr_url' => $url, 'create_time' => time()]);
    }

    /**
     * @return array
     */
    public static function getWeChatCSList()
    {
        return WeChatCSModel::getRecords([],[],false);
    }
}