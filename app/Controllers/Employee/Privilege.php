<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\EmployeeService;
use App\Services\PrivilegeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Privilege extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function employeeMenu(Request $request, Response $response, $args)
    {
        $menus = PrivilegeService::getEmployeeMenuService($this->ci['employee']['employee_id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'menus' => $menus
            ]
        ], StatusCode::HTTP_OK);
    }
}