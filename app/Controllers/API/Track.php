<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/11
 * Time: 7:08 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
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
        $info['create_time'] = intval($params['create_time'] / 1000);
        $info['callback'] = $params['callback'];

        $trackData = TrackService::trackEvent(TrackService::TRACK_EVENT_INIT, $info);

        $ret = ['status' => $trackData['complete'] ? 0 : 1];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function adEventGdt(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        $ret = ['ret' => 0, 'msg' => 'OK'];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function adEventWx(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        $ret = ['ret' => 0, 'msg' => 'OK'];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    /**
     * OPPO点击监测回调
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function adEventOPPO(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OPPO::track", [$params]);

        $info = [];
        $info['platform'] = TrackService::PLAT_ID_ANDROID;
        $info['imei'] = $params['imei'];
        $info['ad_channel'] = TrackService::CHANNEL_OPPO;
        $info['ad_id'] = $params['ad_id'];
        $info['create_time'] = time();

        $trackData = TrackService::trackEvent(TrackService::TRACK_EVENT_INIT, $info);
        $ret = ['ret' => $trackData['complete'] ? 0 : 1, 'msg' => 'OK'];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    /**
     * 渠道商调用接口
     * 查询idfa是否存在
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function checkIdfa(Request $request, Response $response)
    {
        $params = $request->getParams();
        if (empty($params['idfa'])) {
            return $response->withJson([], StatusCode::HTTP_OK);
        }

        $data = TrackService::match($params);
        if (empty($data)) {
            return $response->withJson([$params['idfa'] => 0], StatusCode::HTTP_OK);
        }

        return $response->withJson([$params['idfa'] => 1], StatusCode::HTTP_OK);
    }

    /**
     * 推广用户开始任务
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function clickIdfa(Request $request, Response $response)
    {
        $params = $request->getParams();
        if (empty($params['idfa']) || empty($params['callback'])) {
            return $response->withJson(['errno' => 1, 'error' => 'fail'], StatusCode::HTTP_OK);
        }

        $info['platform'] = TrackService::PLAT_ID_IOS;
        $info['ad_channel'] = TrackService::CHANNEL_IOS_IDFA;
        $info['idfa'] = $params['idfa'];
        $info['callback'] = $params['callback'];

        $trackData = TrackService::trackEvent(TrackService::TRACK_EVENT_INIT, $info);
        if ($trackData['complete']) {
            return $response->withJson(['errno' => 0, 'error' => 'success'], StatusCode::HTTP_OK);
        }

        return $response->withJson(['errno' => 1, 'error' => 'fail'], StatusCode::HTTP_OK);
    }
}