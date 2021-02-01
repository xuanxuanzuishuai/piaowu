<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;

use App\Libs\DictConstants;

class GoodsResourceModel extends Model
{
    public static $table = "goods_resource";

    const CONTENT_TYPE_IMAGE  = 1; // 图片
    const CONTENT_TYPE_TEXT   = 2; // 文字
    const CONTENT_TYPE_POSTER = 3; // 海报

    /**
     * 查询代理商渠道
     * @param $type
     * @return array|mixed|null
     */
    public static function getAgentChannel($type)
    {
        $default = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_distribution');
        $config = DictConstants::get(DictConstants::AGENT_CONFIG, 'channel_dict');
        $config = json_decode($config, true);
        if (empty($type) || empty($config)) {
            return $default;
        }
        return $config[$type] ?? $default;
    }
}