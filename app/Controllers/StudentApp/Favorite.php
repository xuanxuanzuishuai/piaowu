<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:44 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\StudentFavoriteModel;
use App\Services\StudentFavoriteService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Favorite extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 用户首页收藏夹列表
     */
    public function firstPageList(Request $request, Response $response)
    {
        $studentId = $this->ci['student']['id'];
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $this->ci['opn_pro_ver'], $this->ci['opn_auditing'], $this->ci['opn_publish']);
        $data = StudentFavoriteService::firstPageList($opn, $studentId);

        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => $data], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 收藏接口
     */
    public function add(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'type',
                'type'       => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key'        => 'object_id',
                'type'       => 'required',
                'error_code' => 'object_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $params['student_id'] = $this->ci['student']['id'];
            StudentFavoriteService::addFavoriteObject($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => []], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 取消收藏接口
     */
    public function cancel(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'type',
                'type'       => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key'        => 'object_id',
                'type'       => 'required',
                'error_code' => 'object_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $params['student_id'] = $this->ci['student']['id'];
            StudentFavoriteService::updateFavoriteStatus(StudentFavoriteModel::FAVORITE_CANCEL, $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => []], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 返回收藏的曲谱列表
     */
    public function getFavoritesLesson(Request $request, Response $response)
    {
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $this->ci['opn_pro_ver'], $this->ci['opn_auditing'], $this->ci['opn_publish']);
        $studentId = $this->ci['student']['id'];
        $data = StudentFavoriteService::getFavoritesLesson($opn, $studentId);

        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => $data], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 返回收藏的教材列表
     */
    public function getFavoriteCollection(Request $request, Response $response)
    {
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $this->ci['opn_pro_ver'], $this->ci['opn_auditing'], $this->ci['opn_publish']);
        $studentId = $this->ci['student']['id'];
        $data = StudentFavoriteService::getFavoritesCollection($opn, $studentId);

        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => $data], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 返回曲谱收藏状态
     */
    public function favoriteStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'object_id',
                'type'       => 'required',
                'error_code' => 'object_id_is_required'
            ],
            [
                'key'        => 'type',
                'type'       => 'required',
                'error_code' => 'type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['student_id'] = $this->ci['student']['id'];
        $data = StudentFavoriteService::lessonFavoriteStatus($params);

        return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => $data], StatusCode::HTTP_OK);
    }
}