<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/6
 * Time: 上午10:47
 */

namespace App\Controllers\StudentApp;

use App\Services\QuestionService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;

class Question extends ControllerBase
{
    public function list(Request $request, Response $response)
    {
        $records = QuestionService::questions();
        return HttpHelper::buildResponse($response, $records);
    }
}