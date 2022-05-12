<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/6/22
 * Time: 7:48 PM
 */

namespace App\Controllers\AIPlayMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\OpernCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\OpernService;
use App\Services\ShangYinSheService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Opn extends ControllerBase
{

    /**
     * 获取上音社曲谱列表小程序的url_scheme
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function urlScheme(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $urlScheme = ShangYinSheService::getMiniUrlScheme($params['collection_id']);

        return HttpHelper::buildResponse($response, [
            'url_scheme' => $urlScheme
        ]);
    }

    /**
     * 曲谱列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function lessons(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ],
            [
                'key' => 'collection_id',
                'type' => 'numeric',
                'error_code' => 'collection_id_must_be_numeric'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($pageId, $pageLimit) = Util::formatPageCount($params);
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $this->ci['opn_pro_ver']);
        $collection = $opn->collectionsByIds([$params['collection_id']]);
        $collection = $collection['data'][0] ?? [];
        $resourceTypes = 'mp4';
        $switch = DictConstants::get(DictConstants::AI_PLAY_MINI_APP_CONFIG, 'verify_switch');
        if (!empty($switch)) {
            $resourceTypes = '';
        }
        $result = $opn->lessons($params['collection_id'], $pageId, $pageLimit, 1, $resourceTypes);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];

        $copyrightCode = $params['copyright_code'] ?? '';
        $list = OpernService::appFormatLessons($data['list'], $copyrightCode);

        return HttpHelper::buildResponse($response, [
            'collection' => $collection,
            'lessons' => $list,
            'lesson_count' => $data['total_count']
        ]);
    }

    /**
     * 曲谱详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function lesson(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'numeric',
                'error_code' => 'lesson_id_must_be_numeric'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $this->ci['opn_pro_ver']);
        // 示范视频和曲谱图片
        $withResources = 1;
        $resourceTypes = 'mp4,png';
        $switch = DictConstants::get(DictConstants::AI_PLAY_MINI_APP_CONFIG, 'verify_switch');
        if (!empty($switch)) {
            $resourceTypes = 'png';
        }
        $result = $opn->lessonsByIds($params['lesson_id'], $withResources, $resourceTypes);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];
        $lesson = OpernService::appFormatLessonByIds($data)[0] ?? [];

        return HttpHelper::buildResponse($response, $lesson);
    }
}
