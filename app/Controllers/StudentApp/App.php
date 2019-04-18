<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace app\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use APP\Models\AppConfigModel;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class App extends ControllerBase
{
    public function guide(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'version',
                'type' => 'required',
                'error_code' => 'version_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if ($params['version'] == AppConfigModel::get('REVIEW_VERSION')) {
            $url = AppConfigModel::get('REVIEW_GUIDE_URL');
        } else {
            $url = AppConfigModel::get('GUIDE_URL');
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'url' => $url
            ]
        ], StatusCode::HTTP_OK);
    }

}