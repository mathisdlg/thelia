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
use Thelia\Model\Address as AddressModel;
use Thelia\Model\AddressQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;


// #doc-desc Address loop lists address addresses.

/**
 * Address loop.
 *
 * Class Address
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method bool|string getDefault()
 * @method string      getCustomer()
 * @method int[]       getExclude()
 */
class Address extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name customer
	 * #doc-arg-desc Either a customer id or the keyword `current` which search for current customer addresses.
	 * #doc-arg-example customer="current", customer="11"
	 * 
	 * #doc-arg-name default
	 * #doc-arg-desc A boolean value to return either customer default address either all the others.
	 * #doc-arg-example default="true"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of address ids to exclude.
	 * #doc-arg-example exclude="456,123"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of address ids.
	 * #doc-arg-example id="2", id="1,4,7"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            new Argument(
                'id',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(['*', 'any'])
                )
            ),
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(['current'])
                ),
                'current'
            ),
            Argument::createBooleanOrBothTypeArgument('default'),
            new Argument(
                'exclude',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(['none'])
                )
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AddressQuery::create();

        $id = $this->getId();

        if (null !== $id && !\in_array($id, ['*', 'any'])) {
            $search->filterById($id, Criteria::IN);
        }

        $customer = $this->getCustomer();

        if ($customer === 'current') {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
            }
            $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
        } else {
            $search->filterByCustomerId($customer, Criteria::EQUAL);
        }

        $default = $this->getDefault();

        if ($default === true) {
            $search->filterByIsDefault(1, Criteria::EQUAL);
        } elseif ($default === false) {
            $search->filterByIsDefault(0, Criteria::EQUAL);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude && 'none' !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ADDRESS1
	 * #doc-out-desc the first address line
	 * 
	 * #doc-out-name $ADDRESS2
	 * #doc-out-desc the second address line
	 * 
	 * #doc-out-name $ADDRESS3
	 * #doc-out-desc the third address line
	 * 
	 * #doc-out-name $CELLPHONE
	 * #doc-out-desc the address cellphone
	 * 
	 * #doc-out-name $CITY
	 * #doc-out-desc the address city
	 * 
	 * #doc-out-name $COMPANY
	 * #doc-out-desc the address company
	 * 
	 * #doc-out-name $COUNTRY
	 * #doc-out-desc the address country which might be use in country loop
	 * 
	 * #doc-out-name $CUSTOMER
	 * #doc-out-desc the customer the address is link to which might be use in customer loop
	 * 
	 * #doc-out-name $DEFAULT
	 * #doc-out-desc return if address title is by default address
	 * 
	 * #doc-out-name $FIRSTNAME
	 * #doc-out-desc the address firstname
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the address id
	 * 
	 * #doc-out-name $LABEL
	 * #doc-out-desc the address label
	 * 
	 * #doc-out-name $LASTNAME
	 * #doc-out-desc the address lastname
	 * 
	 * #doc-out-name $PHONE
	 * #doc-out-desc the address phone
	 * 
	 * #doc-out-name $STATE
	 * #doc-out-desc the ID of the associated state
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the address title which might be use in title loop
	 * 
	 * #doc-out-name $ZIPCODE
	 * #doc-out-desc the address zipcode
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var AddressModel $address */
        foreach ($loopResult->getResultDataCollection() as $address) {
            $loopResultRow = new LoopResultRow($address);
            $loopResultRow
                ->set('ID', $address->getId())
                ->set('LABEL', $address->getLabel())
                ->set('CUSTOMER', $address->getCustomerId())
                ->set('TITLE', $address->getTitleId())
                ->set('COMPANY', $address->getCompany())
                ->set('FIRSTNAME', $address->getFirstname())
                ->set('LASTNAME', $address->getLastname())
                ->set('ADDRESS1', $address->getAddress1())
                ->set('ADDRESS2', $address->getAddress2())
                ->set('ADDRESS3', $address->getAddress3())
                ->set('ZIPCODE', $address->getZipcode())
                ->set('CITY', $address->getCity())
                ->set('COUNTRY', $address->getCountryId())
                ->set('STATE', $address->getStateId())
                ->set('PHONE', $address->getPhone())
                ->set('CELLPHONE', $address->getCellphone())
                ->set('DEFAULT', $address->getIsDefault())
            ;
            $this->addOutputFields($loopResultRow, $address);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
