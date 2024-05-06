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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Admin as AdminModel;
use Thelia\Model\AdminQuery;

/**
 * Admin loop.
 *
 * Class Admin
 *
 * #doc-desc Admin loop displays admins information.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 * @method int[] getProfile()
 */
class Admin extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     *
     *
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
		// #doc-arg-desc A single or a list of admin ids.
            Argument::createIntListTypeArgument('id'),
		// #doc-arg-desc A single or a list of profile ids.
            Argument::createIntListTypeArgument('profile')
        );
    }

    public function buildModelCriteria()
    {
        $search = AdminQuery::create();

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $profile = $this->getProfile();

        if (null !== $profile) {
            $search->filterByProfileId($profile, Criteria::IN);
        }

        $search->orderByFirstname(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AdminModel $admin */
        foreach ($loopResult->getResultDataCollection() as $admin) {
            $loopResultRow = new LoopResultRow($admin);
            // #doc-out-desc the admin id
            $loopResultRow->set('ID', $admin->getId())
                // #doc-out-desc the admin profile id
                ->set('PROFILE', $admin->getProfileId())
                // #doc-out-desc the admin firstname
                ->set('FIRSTNAME', $admin->getFirstname())
                // #doc-out-desc the admin lastname
                ->set('LASTNAME', $admin->getLastname())
                // #doc-out-desc the admin login
                ->set('LOGIN', $admin->getLogin())
                // #doc-out-desc the admin locale
                ->set('LOCALE', $admin->getLocale())
                // #doc-out-desc the admin email
                ->set('EMAIL', $admin->getEmail())
            ;
            $this->addOutputFields($loopResultRow, $admin);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
