<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\KeyErrorRC4Exception;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Services\ActivityService;
use App\Services\PosterTemplateService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Poster extends ControllerBase
{

    /**
     * 海报模板列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function templatePosterList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'template_type',
                'type' => 'required',
                'error_code' => 'template_type_is_required'
            ],
            [
                'key' => 'template_type',
                'type' => 'integer',
                'error_code' => 'template_type_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //获取数据
        try {
            $studentId = $this->ci['user_info']['user_id'];
            $activityData = PosterTemplateService::templatePosterList($studentId, $params['template_type'], !empty($params['activity_id']) ? $params['activity_id'] : NULL);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, $activityData);
    }

    public function getTemplateWord(Request $request, Response $response)
    {
        //接收数据
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        //获取数据
        $activityData = PosterTemplateService::templatePosterWordList($params);
        //返回数据
        return HttpHelper::buildResponse($response, $activityData);
    }

    /**
     * 海报列表
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws KeyErrorRC4Exception
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $userInfo = $this->ci['user_info'];
            $params['from_type'] = ActivityService::FROM_TYPE_APP;
            $data = PosterTemplateService::getPosterList($userInfo['user_id'], $params['type'], $params['activity_id'] ?? 0, $params);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}