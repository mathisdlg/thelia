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

// #doc-desc Address loop lists.

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
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of address ids.
            new Argument(
                'id',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(['*', 'any'])
                )
            ),
            // #doc-arg-desc Either a customer id or the keyword `current` which search for current customer addresses.
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(['current'])
                ),
                'current'
            ),
            // #doc-arg-desc A boolean value to return either customer default address either all the others.
            Argument::createBooleanOrBothTypeArgument('default'),
            // #doc-arg-desc A single or a list of address ids to exclude.
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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AddressModel $address */
        foreach ($loopResult->getResultDataCollection() as $address) {
            $loopResultRow = new LoopResultRow($address);
            $loopResultRow
                // #doc-out-desc the address id
                ->set('ID', $address->getId())
                // #doc-out-desc the address label
                ->set('LABEL', $address->getLabel())
                // #doc-out-desc the customer the address is link to which might be use in customer loop
                ->set('CUSTOMER', $address->getCustomerId())
                // #doc-out-desc the address title which might be use in title loop
                ->set('TITLE', $address->getTitleId())
                // #doc-out-desc the address company
                ->set('COMPANY', $address->getCompany())
                // #doc-out-desc the address firstname
                ->set('FIRSTNAME', $address->getFirstname())
                // #doc-out-desc the address lastname
                ->set('LASTNAME', $address->getLastname())
                // #doc-out-desc The address first line
                ->set('ADDRESS1', $address->getAddress1())
                // #doc-out-desc The address second line
                ->set('ADDRESS2', $address->getAddress2())
                // #doc-out-desc The address third line
                ->set('ADDRESS3', $address->getAddress3())
                // #doc-out-desc the address zipcode
                ->set('ZIPCODE', $address->getZipcode())
                // #doc-out-desc the address city
                ->set('CITY', $address->getCity())
                // #doc-out-desc the address country which might be use in country loop
                ->set('COUNTRY', $address->getCountryId())
                // #doc-out-desc the ID of the associated state
                ->set('STATE', $address->getStateId())
                // #doc-out-desc the address phone
                ->set('PHONE', $address->getPhone())
                // #doc-out-desc the address cellphone
                ->set('CELLPHONE', $address->getCellphone())
                // #doc-out-desc return if address title is by default address
                ->set('DEFAULT', $address->getIsDefault())
            ;
            $this->addOutputFields($loopResultRow, $address);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
