<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 11:32 AM
 */

namespace App\Controllers;


use App\Libs\Valid;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

class ControllerBase
{
    /**
     * @var ContainerInterface
     */
    protected $ci;

    /**
     * ControllerBase constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci)
    {
        $this->ci = $ci;
        $this->ci['result'] = ['data' => [], 'err_no' => Valid::CODE_SUCCESS, 'err_msg' => []];
    }

    /**
     * @param array $data
     * @param int $errNo
     * @param array $errMsg
     */
    public function setResult($data = [], $errNo = Valid::CODE_SUCCESS, $errMsg = [])
    {
        $this->ci['result'] = ['data' => $data, 'err_no' => $errNo, 'err_msg' => $errMsg];
    }

    /**
     * @param Response $response
     * @param string $template
     * @param array $data
     * @return ResponseInterface
     */
    public function render(Response $response, $template = '', $data = [])
    {
        return $this->getView()->render($response, $template, $data);
    }

    /**
     * @return PhpRenderer
     */
    public function getView()
    {
        return $this->ci['view'];
    }

    public function success(Response $response, $data = [])
    {
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ]);
    }

    public function fail(Response $response, $key, $errorCode, $result = [])
    {
        return $response->withJson(Valid::addErrors($result, $key, $errorCode));
    }

    public function getEmployeeId()
    {
        return $this->ci['employee']['id'];
    }

    public function getEmployeeOrgId()
    {
        return $this->ci['employee']['org_id'];
    }

    public function getRoleId()
    {
        return $this->ci['employee']['role_id'];
    }

    public function isOrgCC($roleId)
    {
        return $this->ci['employee']['role_id'] == $roleId;
    }
}