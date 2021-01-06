<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/22
 * Time: 12:53
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Models\Dss\DssUserWeiXinModel;

class PosterModel extends Model
{
    public static $table = "poster";

    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    /**
     * 获取海报ID
     * @param $path
     * @param array $params
     * @return int|mixed|string|null
     */
    public static function getIdByPath($path, $params = [])
    {
        if (empty($path)) {
            return 0;
        }
        $item = self::getRecord(['path' => $path, 'status' => self::STATUS_ENABLE]);
        if (!empty($item['id'])) {
            return $item['id'];
        }
        $data = [
            'name'        => $params['name'] ?? '',
            'path'        => $path,
            'status'      => self::STATUS_ENABLE,
            'create_time' => time()
        ];
        return self::insertRecord($data);
    }
    
}