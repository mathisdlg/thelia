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
use Thelia\Model\OrderAddress as OrderAddressModel;
use Thelia\Model\OrderAddressQuery;

/**
 * OrderAddress loop.
 *
 * Class OrderAddress
 * 
 * #doc-desc Order address loop displays order addresses information.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int getId()
 */
class OrderAddress extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name id *
	 * #doc-arg-desc A single order address id
	 * #doc-arg-example id="2"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderAddressQuery::create();

        $id = $this->getId();

        $search->filterById($id, Criteria::IN);

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ADDRESS1
	 * #doc-out-desc the first order address line
	 * 
	 * #doc-out-name $ADDRESS2
	 * #doc-out-desc the second order address line
	 * 
	 * #doc-out-name $ADDRESS3
	 * #doc-out-desc the third order address line
	 * 
	 * #doc-out-name $CELLPHONE
	 * #doc-out-desc the order address cellphone
	 * 
	 * #doc-out-name $CITY
	 * #doc-out-desc the order address city
	 * 
	 * #doc-out-name $COMPANY
	 * #doc-out-desc the order address company
	 * 
	 * #doc-out-name $COUNTRY
	 * #doc-out-desc the order address country which might be use in country loop
	 * 
	 * #doc-out-name $FIRSTNAME
	 * #doc-out-desc the order address firstname
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the order address id
	 * 
	 * #doc-out-name $LASTNAME
	 * #doc-out-desc the order address lastname
	 * 
	 * #doc-out-name $PHONE
	 * #doc-out-desc the order address phone
	 * 
	 * #doc-out-name $STATE
	 * #doc-out-desc the order address state
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the order address title which might be use in title loop
	 * 
	 * #doc-out-name $ZIPCODE
	 * #doc-out-desc the order address zipcode
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderAddressModel $orderAddress */
        foreach ($loopResult->getResultDataCollection() as $orderAddress) {
            $loopResultRow = new LoopResultRow($orderAddress);
            $loopResultRow
                ->set('ID', $orderAddress->getId())
                ->set('TITLE', $orderAddress->getCustomerTitleId())
                ->set('COMPANY', $orderAddress->getCompany())
                ->set('FIRSTNAME', $orderAddress->getFirstname())
                ->set('LASTNAME', $orderAddress->getLastname())
                ->set('ADDRESS1', $orderAddress->getAddress1())
                ->set('ADDRESS2', $orderAddress->getAddress2())
                ->set('ADDRESS3', $orderAddress->getAddress3())
                ->set('ZIPCODE', $orderAddress->getZipcode())
                ->set('CITY', $orderAddress->getCity())
                ->set('COUNTRY', $orderAddress->getCountryId())
                ->set('STATE', $orderAddress->getStateId())
                ->set('PHONE', $orderAddress->getPhone())
                ->set('CELLPHONE', $orderAddress->getCellphone())
            ;
            $this->addOutputFields($loopResultRow, $orderAddress);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
