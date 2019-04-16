<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 13:42
 */

namespace App\Controllers\Teacher;


use App\Controllers\ControllerBase;

use App\Libs\Valid;
use App\Services\TeacherTagsService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class TeacherTag extends ControllerBase
{
    public function list(Request $request, Response $response, $args){
    $type = $request->getParam('type');
    $data = TeacherTagsService::getTagData($type);

    return $response->withJson([
        'code' => Valid::CODE_SUCCESS,
        'data' => $data
    ], StatusCode::HTTP_OK);
}
}