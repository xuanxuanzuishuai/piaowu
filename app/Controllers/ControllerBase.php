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
use Slim\Http\Response;

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
     */
    public function setView(Response $response, $template = '', $data = [])
    {
        $this->ci->view->render($response, $template, $data);
    }
}