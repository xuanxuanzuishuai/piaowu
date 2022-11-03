<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2021/1/18
 * Time: 11:15 上午
 */


namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\TPNS;
use App\Models\Dss\DssPushDeviceModel;
use App\Models\PushRecordModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PushServices
{
    //推送用户类型
    const PUSH_USER_ALL = 1;                            //全量用户推送
    const PUSH_USER_PART = 2;                           //指定用户推送
    const PUSH_USER_PAY = 3;                            //付费正式课用户推送
    const PUSH_USER_EXPERIENCE = 4;                     //付费体验课用户推送

    //跳转类型
    const PUSH_JUMP_TYPE_HOME_PAGE = 1;                 //跳转到首页
    const PUSH_JUMP_TYPE_WEB_VIEW = 2;                  //跳转到webview
    const PUSH_JUMP_TYPE_BROWSER = 3;                   //跳转到浏览器链接
    const PUSH_JUMP_TYPE_LITE_APP = 4;                  //跳转到小程序
    const PUSH_JUMP_TYPE_MUSICAL_NOTE_MALL = 5;         //跳转到音符商城
    const PUSH_JUMP_TYPE_PLAY_CALENDAR = 6;             //跳转到练琴日历
    const PUSH_JUMP_TYPE_COLLECTION_DETAIL = 7;         //跳转到套课详情页
    const PUSH_JUMP_TYPE_UNIFIED = 8;                   //业务端自定义跳转路径

    //Excel最大记录数
    const MAX_EXCEL_RECORD = 20000;


    /**
     * @param $params
     * @return bool
     * @throws RunTimeException
     * 推送接口
     */
    public static function push($params)
    {
        //数据检查
        $params = self::checkParams($params);

        //跳转类型
        switch ($params['jump_type']) {
            case self::PUSH_JUMP_TYPE_HOME_PAGE:
                TPNS::homePagePush($params);
                break;
            case self::PUSH_JUMP_TYPE_WEB_VIEW:
                TPNS::webViewPush($params);
                break;
            case self::PUSH_JUMP_TYPE_BROWSER:
                TPNS::browserPush($params);
                break;
            case self::PUSH_JUMP_TYPE_LITE_APP:
                TPNS::liteAppPush($params);
                break;
            case self::PUSH_JUMP_TYPE_MUSICAL_NOTE_MALL:
                TPNS::musicNoteMallPush($params);
                break;
            case self::PUSH_JUMP_TYPE_PLAY_CALENDAR:
                TPNS::playCalendarPush($params);
                break;
            case self::PUSH_JUMP_TYPE_COLLECTION_DETAIL:
                TPNS::collectionDetailPush($params);
                break;
            case self::PUSH_JUMP_TYPE_UNIFIED:
                TPNS::unifiedPush($params);
                break;
        }

        return true;
    }

    /**
     * @param $params
     * @return mixed
     * @throws RunTimeException
     * 根据推送类型进行参数检查
     */
    public static function checkParams($params)
    {
        if ($params['push_user_type'] == PushServices::PUSH_USER_ALL && $_ENV['ENV_NAME'] != 'prod') {
            throw new RunTimeException(['push_all_user_forbidden_test']);
        }

        switch ($params['jump_type']) {
            case self::PUSH_JUMP_TYPE_WEB_VIEW:
                if (empty($params['link_url']) || (empty($params['jump_to']) && !in_array($params['jump_to'], [0, '0']))) {
                    throw new RunTimeException(['params_can_not_be_empty']);
                }
                break;
            case self::PUSH_JUMP_TYPE_MUSICAL_NOTE_MALL:
            case self::PUSH_JUMP_TYPE_COLLECTION_DETAIL:
            case self::PUSH_JUMP_TYPE_BROWSER:
            case self::PUSH_JUMP_TYPE_PLAY_CALENDAR:
                if (empty($params['link_url'])) {
                    throw new RunTimeException(['params_can_not_be_empty']);
                }
                break;
            case self::PUSH_JUMP_TYPE_LITE_APP:
                if (empty($params['app_id'])) {
                    throw new RunTimeException(['params_can_not_be_empty']);
                }
                break;
        }

        if ($params['push_user_type'] == self::PUSH_USER_PART && (empty($params['file_name']) && empty($params['uuid_arr']))) {
            throw new RunTimeException(['push_file_is_required']);
        }

        return $params;
    }


    /**
     * @param $file
     * @return array
     * @throws RunTimeException
     * 解析Excel
     */
    public static function analysisExcel($file)
    {
        $extension = strtolower(pathinfo($file['name'])['extension']);
        if (!in_array($extension, ['xls', 'xlsx'])) {
            throw new RunTimeException(['push_file_is_required']);
        }

        $filename = $_ENV['STATIC_FILE_SAVE_PATH'] . '/push_user_' . md5(rand() . time()) . '.' . $extension;
        if (move_uploaded_file($file['tmp_name'], $filename) == false) {
            throw new RunTimeException(['move_file_fail']);
        }

        //获取Excel文件中数据
        $records = self::dumpPushUserRecords($filename);

        if (empty($records)) {
            throw new RunTimeException(['excel_records_empty']);
        }

        if (count($records) > self::MAX_EXCEL_RECORD) {
            throw new RunTimeException(['excel_records_num_exceed']);
        }

        unlink($filename);
        return $records;
    }

    /**
     * @param $filename
     * @return array
     * 获取上传Excel中的数据
     */
    public static function dumpPushUserRecords($filename)
    {
        try {
            $fileType = ucfirst(pathinfo($filename)["extension"]);
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($filename);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $data = [];
            foreach ($sheetData as $v) {
                if (!empty($v['A']) && $v['A'] != 'uuid') {
                    $data[] = $v['A'];
                }
            }
        } catch (\Exception $e) {
            return [];
        }
        return $data;
    }

    /**
     * @param $uuid
     * @return array
     * 根据手机号获取device_token
     */
    public static function getDeviceToken($uuid)
    {
        if (empty($uuid)) {
            return [];
        }

        $result = DssPushDeviceModel::getDeviceTokenByUUid($uuid);
        if (empty($result)) {
            return [];
        }
        $deviceTokenList['android'] = $deviceTokenList['ios'] = [];
        foreach ($result as $value) {
            if ($value['platform'] == TPNS::PLATFORM_ANDROID) {
                $deviceTokenList['android'][] = $value['device_token'];
            } elseif ($value['platform'] == TPNS::PLATFORM_IOS) {
                $deviceTokenList['ios'][] = $value['device_token'];
            }
        }

        return $deviceTokenList ?? [];
    }

    /**
     * @param $params
     * @return array
     * 推动列表
     */
    public static function pushList($params)
    {
        $count = PushRecordModel::getCount([]);
        if (empty($count)) {
            return [[], 0];
        }

        $startLimit = ($params['page'] - 1) * $params['limit'];
        $endLimit = $params['limit'];
        $where = [
            'LIMIT' => [$startLimit, $endLimit],
            'ORDER' => ['id' => 'DESC']
        ];
        $result = PushRecordModel::getRecords($where, [
            'id', 'jump_type', 'remark', 'push_id_android', 'push_id_ios', 'create_time'
        ]);

        $pushType = DictConstants::getSet(DictConstants::PUSH_TYPE);
        foreach ($result as $key => $value){
            $result[$key]['jump_type'] = $pushType[$value['jump_type']];
            $result[$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
        }

        return [$result, $count];
    }
}
