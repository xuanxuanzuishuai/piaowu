<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use Slim\Http\Response;
use Slim\Http\Request;

class ScheduleTask extends  ControllerBase
{
    public function add(Request $request,Response $response,$args) {

    }

    public function modify(Request $request,Response $response,$args) {

    }

    public function list(Request $request,Response $response,$args) {
        $params = $request->getParams();

    }

    public function detail(Request $request,Response $response,$args) {

    }
}