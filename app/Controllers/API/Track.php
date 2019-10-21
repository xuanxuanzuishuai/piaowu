<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/11
 * Time: 7:08 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Services\TrackService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Track extends ControllerBase
{
    public function adEventOceanEngine(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        $info = [];
        switch ($params['os']) {
            case 0:
                $info['platform'] = TrackService::PLAT_ID_ANDROID;
                $info['imei_hash'] = $params['imei'];
                $info['android_id_hash'] = $params['android_id'];
                break;
            case 1:
                $info['platform'] = TrackService::PLAT_ID_IOS;
                $info['idfa'] = $params['idfa'];
                break;
            default:
                $info['platform'] = TrackService::PLAT_ID_UNKNOWN;
        }
        $info['ad_channel'] = TrackService::CHANNEL_OCEAN;
        $info['ad_id'] = $params['ad_id'];
        $info['mac_hash'] = $params['mac'];
        $info['create_time'] = $params['create_time'];
        $info['callback'] = $params['callback'];

        $success = TrackService::addInfo($info);

        $ret = ['status' => $success ? 0 : 1];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function adEventGdt(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        $ret = ['ret' => 0, 'msg' => 'OK'];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }
}