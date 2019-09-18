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

    /**
     * 添加用户标签
     * container内的数组不能直接修改，需要覆盖整个数组
     * @param $flagId
     * @param $value
     */
    public function addFlag($flagId, $value)
    {
        $flags = $this->container['flags'];
        $flags[$flagId] = $value;
        $this->container['flags'] = $flags;
    }
}