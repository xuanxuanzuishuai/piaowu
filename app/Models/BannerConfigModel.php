<?php


namespace App\Models;


use App\Services\DictService;

class BannerConfigModel extends Model
{
    public static $table = "banner_config";

    const INITIAL_STATUS = 0; //初始
    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS  = 2; //上线

    const NOT_ALLOW_JUMP = 1; //不跳转
    const IS_ALLOW_JUMP = 2; //跳转

    const NOT_STARTED = '未开始';
    const IN_PROGRESS = '进行中';
    const HAS_ENDED = '已结束';

    public static $statusArray = [
        0 => '未上架',
        1 => '已下架',
        2 => '上架',
    ];

    public static function bannerLists($params, $limit)
    {
        $where = [];
        $time = time();
        $bannerStatus = DictService::getList('SHARE_BANNER_STATUS');
        $bannerStatus = array_column($bannerStatus, 'key_code', 'key_name');
        if (!empty($params['id'])) {
            $where['id[~]'] = $params['id'];
        }
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }
        if (!empty($params['status'])) {
            switch ($params['status']) {
                case $bannerStatus['INITIAL_STATUS']: //未上架
                    $where['status'] = self::INITIAL_STATUS;
                    break;
                case $bannerStatus['NOT_STARTED']: //未开始
                    $where['status']        = self::NORMAL_STATUS;
                    $where['start_time[>]'] = $time;
                    break;
                case $bannerStatus['IN_PROGRESS']: //进行中
                    $where['status']         = self::NORMAL_STATUS;
                    $where['start_time[<=]'] = $time;
                    $where['end_time[>=]']   = $time;
                    break;
                case $bannerStatus['HAS_ENDED']: //已结束
                    $where['status']      = self::NORMAL_STATUS;
                    $where['end_time[<]'] = $time;
                    break;
                case $bannerStatus['DISABLE_STATUS']: //已下架
                    $where['status'] = self::DISABLE_STATUS;
                    break;
            }
        }
        $total = self::getCount($where);
        if ($total <= 0) {
            return [[], 0];
        }
        if (!empty($limit)) {
            $where['LIMIT'] = $limit;
        }
        $where['ORDER'] = ['order' => 'ASC','create_time' => 'DESC'];
        $list = self::getRecords($where);
        return [$list, $total];
    }
}