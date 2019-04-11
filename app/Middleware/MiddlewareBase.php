<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 3:18 PM
 */

namespace App\Middleware;


use Psr\Container\ContainerInterface;

class MiddlewareBase
{
    protected $container;
    //Constructor
    public function __construct(ContainerInterface $ci) {
        $this->container = $ci;
    }
}