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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderAddressModel $orderAddress */
        foreach ($loopResult->getResultDataCollection() as $orderAddress) {
            $loopResultRow = new LoopResultRow($orderAddress);
            $loopResultRow
                // #doc-out-desc the order address id
                ->set('ID', $orderAddress->getId())
                // #doc-out-desc the order address title which might be use in title loop
                ->set('TITLE', $orderAddress->getCustomerTitleId())
                // #doc-out-desc the order address company
                ->set('COMPANY', $orderAddress->getCompany())
                // #doc-out-desc the order address firstname
                ->set('FIRSTNAME', $orderAddress->getFirstname())
                // #doc-out-desc the order address lastname
                ->set('LASTNAME', $orderAddress->getLastname())
                // #doc-out-desc the first order address line
                ->set('ADDRESS1', $orderAddress->getAddress1())
                // #doc-out-desc the second order address line
                ->set('ADDRESS2', $orderAddress->getAddress2())
                // #doc-out-desc the third order address line
                ->set('ADDRESS3', $orderAddress->getAddress3())
                // #doc-out-desc the order address zipcode
                ->set('ZIPCODE', $orderAddress->getZipcode())
                // #doc-out-desc the order address city
                ->set('CITY', $orderAddress->getCity())
                // #doc-out-desc the order address country which might be use in country loop
                ->set('COUNTRY', $orderAddress->getCountryId())
                // #doc-out-desc the order address state
                ->set('STATE', $orderAddress->getStateId())
                // #doc-out-desc the order address phone
                ->set('PHONE', $orderAddress->getPhone())
                // #doc-out-desc the order address cellphone
                ->set('CELLPHONE', $orderAddress->getCellphone())
            ;
            $this->addOutputFields($loopResultRow, $orderAddress);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
