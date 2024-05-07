<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\AlphaNumStringListType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 *
 * #doc-desc The Auth loop perform authorisation checks against the current user. This loop returns nothing if the authorization fails, or the loop contents if it succeeds.You may check in the front office if an administrator is logged in, and perform specific functions in your front-office template (such as direct editing, for example).
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method string[] getRole()
 * @method string[] getResource()
 * @method int[]    getModule()
 * @method string[] getAccess()
 */
class Auth extends BaseLoop implements ArraySearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A comma separated list of user roles
            new Argument(
                'role',
                new TypeCollection(
                    new AlphaNumStringListType()
                ),
                null,
                true
            ),
            // #doc-arg-desc A comma separated list of resources
            new Argument(
                'resource',
                new TypeCollection(
                    new AlphaNumStringListType()
                )
            ),
            // #doc-arg-desc A comma separated list of modules
            new Argument(
                'module',
                new TypeCollection(
                    new AlphaNumStringListType()
                )
            ),
            // #doc-arg-desc A comma separated list of access, . If empty or missing, the authorization is checked against the roles only
            new Argument(
                'access',
                new TypeCollection(
                    new EnumListType([AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE])
                )
            )
        );
    }

    public function buildArray()
    {
        return [];
    }


    public function parseResults(LoopResult $loopResult)
    {
        $roles = $this->getRole();
        $resource = $this->getResource();
        $module = $this->getModule();
        $access = $this->getAccess();

        try {
            if (true === $this->securityContext->isGranted(
                $roles,
                $resource === null ? [] : $resource,
                $module === null ? [] : $module,
                $access === null ? [] : $access
            )
            ) {
                // Create an empty row: loop is no longer empty :)
                $loopResult->addRow(new LoopResultRow());
            }
        } catch (\Exception $ex) {
            // Not granted, loop is empty
        }

        return $loopResult;
    }
}
