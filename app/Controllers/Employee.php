<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/25
 * Time: 7:13 PM
 */

namespace App\Controllers;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\EmployeeService;
use App\Services\TestService;
use Slim\Http\Request;
use Slim\Http\Response;
class Employee extends ControllerBase
{

    public function login(Request $request, Response $response, Array $args) {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'login_name_is_required'
            ],
            [
                'key' => 'password',
                'type' => 'required',
                'error_code' => 'password_is_required'
            ]
        ];
        $params = $request->getParams();
        if($ret = Valid::appValidate($params,$rules)) {
            return $this->setResult([],Valid::CODE_PARAMS_ERROR,$ret);
        }
        $result = EmployeeService::login($params['name'],$params['password']);
        if(!empty($result['err_no'])) {
            return $this->setResult($result);
        }
        return $this->setResult(['token'=>$result[0],'employee'=>$result[1]]);

    }

    public function method2($request, $response, $args) {

        return $this->setResult(['list'=>'mmmmmmm']);
        $this->ci['result'] = ['data'=>'mmmmmm'];
        SimpleLogger::error('ceshi',['ceshi']);
        $this->setView($response,'index.phtml',['name'=>'hemu']);
        //your code
        //to access items in the container... $this->ci->get('');
    }

    public function method3($request, $response, $args) {
        //your code
        //to access items in the container... $this->ci->get('');
    }
}