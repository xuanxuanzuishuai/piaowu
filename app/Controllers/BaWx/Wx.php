<?php


namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;

class Wx extends ControllerBase
{

    public function login(Request $request, Response $response)
    {
        echo 11;
        die();
        return HttpHelper::buildResponse($response, []);
    }

}