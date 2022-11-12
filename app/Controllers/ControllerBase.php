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

    public function getEmployeeId()
    {
        return $this->ci['employee']['employee_id'];
    }
}