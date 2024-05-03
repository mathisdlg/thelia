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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\NewsletterTableMap;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * Customer loop.
 *
 * Class Customer
 * 
 * #doc-desc Customer loop displays customers information.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method bool        getCurrent()
 * @method string      getRef()
 * @method bool        getReseller()
 * @method int         getSponsor()
 * @method bool|string getNewsletter()
 * @method string[]    getOrder()
 * @method bool        getWithPrevNextInfo()
 */
class Customer extends BaseLoop implements SearchLoopInterface, PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name current
	 * #doc-arg-desc A boolean value which must be set to false if you need to display not authenticated customers information, typically if `sponsor` parameter is set.
	 * #doc-arg-example current="false"
	 * 
	 * #doc-arg-name Newsletter
	 * #doc-arg-desc A boolean that represents whether the customer is subscribed to the newsletter
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-example order="firstname, lastname"
	 * 
	 * #doc-arg-name ref
	 * #doc-arg-desc A single or a list of customer references.
	 * #doc-arg-example ref="1231231241", ref="123123,789789"
	 * 
	 * #doc-arg-name reseller
	 * #doc-arg-desc A boolean value.
	 * #doc-arg-example reseller="yes"
	 * 
	 * #doc-arg-name sponsor
	 * #doc-arg-desc The sponsor ID which you want the list of affiliated customers
	 * #doc-arg-example sponsor="1"
	 * 
	 * #doc-arg-name with_prev_next_info
	 * #doc-arg-desc A boolean. If set to true, $HAS_PREVIOUS, $HAS_NEXT, $PREVIOUS, and $NEXT output variables are available.
	 * #doc-arg-example with_prev_next_info="yes"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('current', 1),
            Argument::createIntListTypeArgument('id'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            new Argument(
                'ref',
                new TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
            Argument::createBooleanTypeArgument('reseller'),
            Argument::createIntTypeArgument('sponsor'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id',
                            'id_reverse',
                            'reference',
                            'reference_reverse',
                            'firstname',
                            'firstname_reverse',
                            'lastname',
                            'lastname_reverse',
                            'last_order',
                            'last_order_reverse',
                            'order_amount',
                            'order_amount_reverse',
                            'registration_date',
                            'registration_date_reverse',
                        ]
                    )
                ),
                'lastname'
            ),
            Argument::createBooleanOrBothTypeArgument('newsletter', Type\BooleanOrBothType::ANY)
        );
    }

    public function getSearchIn()
    {
        return [
            'ref',
            'firstname',
            'lastname',
            'email',
        ];
    }

    /**
     * @param CustomerQuery $search
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();
        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }
            switch ($searchInElement) {
                case 'ref':
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case 'firstname':
                    $search->filterByFirstname($searchTerm, $searchCriteria);
                    break;
                case 'lastname':
                    $search->filterByLastname($searchTerm, $searchCriteria);
                    break;
                case 'email':
                    $search->filterByEmail($searchTerm, $searchCriteria);
                    break;
            }
        }
    }

    public function buildModelCriteria()
    {
        $search = CustomerQuery::create();

        // Join newsletter
        $newsletter = $this->getNewsletter();

        // if newsletter === "*" or false, it'll be a left join
        $join = new Join(
            CustomerTableMap::COL_EMAIL,
            NewsletterTableMap::COL_EMAIL,
            true === $newsletter ? Criteria::INNER_JOIN : Criteria::LEFT_JOIN
        );

        $search
            ->addJoinObject($join, 'newsletter_join')
            ->addJoinCondition('newsletter_join', NewsletterTableMap::COL_UNSUBSCRIBED.' = ?', false, null, \PDO::PARAM_BOOL)
            ->withColumn('IF(ISNULL('.NewsletterTableMap::COL_EMAIL.'), 0, 1)', 'is_registered_to_newsletter');

        // If "*" === $newsletter, no filter will be applied, so it won't change anything
        if (false === $newsletter) {
            $search->having('is_registered_to_newsletter = 0');
        }

        $current = $this->getCurrent();

        if ($current === true) {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
            }
            $search->filterById($currentCustomer->getId(), Criteria::EQUAL);
        }

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $ref = $this->getRef();

        if (null !== $ref) {
            $search->filterByRef($ref, Criteria::IN);
        }

        $reseller = $this->getReseller();

        if ($reseller === true) {
            $search->filterByReseller(1, Criteria::EQUAL);
        } elseif ($reseller === false) {
            $search->filterByReseller(0, Criteria::EQUAL);
        }

        $sponsor = $this->getSponsor();

        if ($sponsor !== null) {
            $search->filterBySponsor($sponsor, Criteria::EQUAL);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'reference':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'reference_reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;
                case 'lastname':
                    $search->orderByLastname(Criteria::ASC);
                    break;
                case 'lastname_reverse':
                    $search->orderByLastname(Criteria::DESC);
                    break;
                case 'firstname':
                    $search->orderByFirstname(Criteria::ASC);
                    break;
                case 'firstname_reverse':
                    $search->orderByFirstname(Criteria::DESC);
                    break;
                case 'registration_date':
                    $search->orderByCreatedAt(Criteria::ASC);
                    break;
                case 'registration_date_reverse':
                    $search->orderByCreatedAt(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $CONFIRMATION_TOKEN
	 * #doc-out-desc the customer registration confirmation token, used when email confirmation of registration is enabled (see <strong>customer_email_confirmation</strong> configuration variable)
	 * 
	 * #doc-out-name $DISCOUNT
	 * #doc-out-desc the customer discount
	 * 
	 * #doc-out-name $EMAIL
	 * #doc-out-desc the customer email
	 * 
	 * #doc-out-name $FIRSTNAME
	 * #doc-out-desc the customer firstname
	 * 
	 * #doc-out-name $HAS_NEXT
	 * #doc-out-desc true if a customer exists after the current one, regarding the curent order. Only available if <strong>with_prev_next_info</strong> parameter is set to true
	 * 
	 * #doc-out-name $HAS_PREVIOUS
	 * #doc-out-desc true if a customer exists before the current one, regarding the curent order. Only available if <strong>with_prev_next_info</strong> parameter is set to true
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the customer id
	 * 
	 * #doc-out-name $LASTNAME
	 * #doc-out-desc the customer lastname
	 * 
	 * #doc-out-name $NEWSLETTER
	 * #doc-out-desc true if the customer is registered in the newsletter table, false otherwise
	 * 
	 * #doc-out-name $NEXT
	 * #doc-out-desc ID of the next customer, or null if non exists. Only available if <strong>with_prev_next_info</strong> parameter is set to true
	 * 
	 * #doc-out-name $PREVIOUS
	 * #doc-out-desc ID of the previous customer, or null if non exists. Only available if <strong>with_prev_next_info</strong> parameter is set to true
	 * 
	 * #doc-out-name $REF
	 * #doc-out-desc the customer reference
	 * 
	 * #doc-out-name $RESELLER
	 * #doc-out-desc return if the customer is a reseller
	 * 
	 * #doc-out-name $SPONSOR
	 * #doc-out-desc the customer sponsor which might be use in another   customer loop
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the customer title which might be use in title loop
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Customer $customer */
        foreach ($loopResult->getResultDataCollection() as $customer) {
            $loopResultRow = new LoopResultRow($customer);

            $loopResultRow
                ->set('ID', $customer->getId())
                ->set('REF', $customer->getRef())
                ->set('TITLE', $customer->getTitleId())
                ->set('FIRSTNAME', $customer->getFirstname())
                ->set('LASTNAME', $customer->getLastname())
                ->set('EMAIL', $customer->getEmail())
                ->set('RESELLER', $customer->getReseller())
                ->set('SPONSOR', $customer->getSponsor())
                ->set('DISCOUNT', $customer->getDiscount())
                ->set('NEWSLETTER', $customer->getVirtualColumn('is_registered_to_newsletter'))
                ->set('CONFIRMATION_TOKEN', $customer->getConfirmationToken())
            ;

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = CustomerQuery::create()
                    ->filterById($customer->getId(), Criteria::LESS_THAN);
                $previous = $previousQuery
                    ->orderById(Criteria::DESC)
                    ->findOne();
                $nextQuery = CustomerQuery::create()
                    ->filterById($customer->getId(), Criteria::GREATER_THAN);
                $next = $nextQuery
                    ->orderById(Criteria::ASC)
                    ->findOne();
                $loopResultRow
                    ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
                    ->set('HAS_NEXT', $next != null ? 1 : 0)
                    ->set('PREVIOUS', $previous != null ? $previous->getId() : -1)
                    ->set('NEXT', $next != null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $customer);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
