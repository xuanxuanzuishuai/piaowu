<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 5:21 PM
 */

namespace App\Controllers\Admin;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\AdminService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Menu extends ControllerBase
{
    public function main(Request $request, Response $response)
    {
        list($template, $data) = AdminService::getMainMenu();
        return $this->render($response, $template, $data);
    }

    public function page(Request $request, Response $response)
    {
        $key = $request->getParam('key');
        list($template, $data) = AdminService::getPage($key);
        if (empty($template)) {
            return 'invalid page!';
        }
        return $this->render($response, $template, $data);
    }

    public function process(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($error, $result) = AdminService::processPage($params['key'], $params);

        $data = ['result' => $result ?? []];
        if (!empty($error)) {
            $data['error'] = $error;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data,
        ], StatusCode::HTTP_OK);
    }
}