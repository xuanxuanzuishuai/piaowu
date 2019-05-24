<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 11:32 AM
 */

namespace App\Controllers;


use App\Models\OrganizationModel;
use Psr\Container\ContainerInterface;

class ControllerBaseForOrg extends ControllerBase
{
    /**
     * ControllerBaseForOrg constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci)
    {
        parent::__construct($ci);
        global $orgId;
        $orgId = OrganizationModel::ORG_ID_DIRECT;
    }
}