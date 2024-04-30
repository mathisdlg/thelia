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
 * #doc-usage {loop type="admin" name="the-loop-name" [argument="value"], [...]}
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
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of admin ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name profile
	 * #doc-arg-desc A single or a list of profile ids.
	 * #doc-arg-example profile="2", profile="1,4,7"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
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

	 /**
	 * 
	 * #doc-out-name $EMAIL
	 * #doc-out-desc the admin email
	 * 
	 * #doc-out-name $FIRSTNAME
	 * #doc-out-desc the admin firstname
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the admin id
	 * 
	 * #doc-out-name $LASTNAME
	 * #doc-out-desc the admin lastname
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc the admin locale
	 * 
	 * #doc-out-name $LOGIN
	 * #doc-out-desc the admin login
	 * 
	 * #doc-out-name $PROFILE
	 * #doc-out-desc the admin profile id
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var AdminModel $admin */
        foreach ($loopResult->getResultDataCollection() as $admin) {
            $loopResultRow = new LoopResultRow($admin);
            $loopResultRow->set('ID', $admin->getId())
                ->set('PROFILE', $admin->getProfileId())
                ->set('FIRSTNAME', $admin->getFirstname())
                ->set('LASTNAME', $admin->getLastname())
                ->set('LOGIN', $admin->getLogin())
                ->set('LOCALE', $admin->getLocale())
                ->set('EMAIL', $admin->getEmail())
            ;
            $this->addOutputFields($loopResultRow, $admin);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
