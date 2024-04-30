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
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * 
 * #doc-usage {loop type="order" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Order loop displays orders information.
 * 
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]|null    getId()
 * @method int[]|null    getRef()
 * @method int[]|null    getInvoiceRef()
 * @method int[]|null    getDeliveryRef()
 * @method int[]|null    getTransactionRef()
 * @method string|null   getCustomer()
 * @method string[]|null getStatus()
 * @method int[]|null    getExcludeStatus()
 * @method string[]|null getStatusCode()
 * @method string[]|null getExcludeStatusCode()
 * @method string[]|null getOrder()
 * @method bool|null     getWithPrevNextInfo()
 */
class Order extends BaseLoop implements SearchLoopInterface, PropelSearchLoopInterface
{
    protected $countable = true;
    protected $timestampable = true;
    protected $versionable = false;

	 /**
	 * 
	 * #doc-arg-name customer
	 * #doc-arg-desc A single customer id or `current` keyword to get logged in user or `*` keyword to match all users.
	 * #doc-arg-default current
	 * #doc-arg-example customer="2", customer="current"
	 * 
	 * #doc-arg-name exclude_status
	 * #doc-arg-desc A single or a list of order status ID which are to be excluded from the results
	 * #doc-arg-example status="*", exclude_status="1,4,7"
	 * 
	 * #doc-arg-name exclude_status_code
	 * #doc-arg-desc A single or a list of order status codes which are to be excluded from the results. The valid status codes are not_paid, paid, processing, sent, canceled, or any custom status that may be defined
	 * #doc-arg-example exclude_status_code="paid,processing"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of order ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default create-date-reverse
	 * #doc-arg-example order="create-date-reverse"
	 * 
	 * #doc-arg-name status
	 * #doc-arg-desc A single or a list of order status ID or `*` keyword to match all
	 * #doc-arg-example status="*", status="1,4,7"
	 * 
	 * #doc-arg-name status_code
	 * #doc-arg-desc A single or a list of order status codes or `*` keyword to match all. The valid status codes are not_paid, paid, processing, sent, canceled, or any custom status that may be defined
	 * #doc-arg-example status="*", status="not_paid,canceled"
	 * 
	 * #doc-arg-name with_prev_next_info
	 * #doc-arg-desc A boolean. If set to true, $PREVIOUS and $NEXT output arguments are available.
	 * #doc-arg-default false
	 * #doc-arg-example with_prev_next_info="yes"
	 * 
	 * #doc-arg-name ref
	 * #doc-arg-desc A single or a list of references.
	 * #doc-arg-example ref="ref,ref2"
	 * 
	 * #doc-arg-name invoice_ref
	 * #doc-arg-desc A single or a list of invoice references.
	 * #doc-arg-example invoice_ref="foo,bar"
	 * 
	 * #doc-arg-name delivery_ref
	 * #doc-arg-desc A single or a list of delivery references.
	 * #doc-arg-example delivery_ref="delivery_ref"
	 * 
	 * #doc-arg-name transaction_ref
	 * #doc-arg-desc A single or a list of transaction references.
	 * #doc-arg-example transaction_ref="transaction_ref"
	 */
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyListTypeArgument('ref'),
            Argument::createAnyListTypeArgument('invoice_ref'),
            Argument::createAnyListTypeArgument('delivery_ref'),
            Argument::createAnyListTypeArgument('transaction_ref'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(['current', '*'])
                ),
                'current'
            ),
            new Argument(
                'status',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(['*'])
                )
            ),
            Argument::createIntListTypeArgument('exclude_status'),
            new Argument(
                'status_code',
                new TypeCollection(
                    new Type\AnyListType(),
                    new Type\EnumType(['*'])
                )
            ),
            Argument::createAnyListTypeArgument('exclude_status_code'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id', 'id-reverse',
                            'reference', 'reference-reverse',
                            'create-date', 'create-date-reverse',
                            'invoice-date', 'invoice-date-reverse',
                            'company', 'company-reverse',
                            'customer-name', 'customer-name-reverse',
                            'status', 'status-reverse',
                        ]
                    )
                ),
                'create-date-reverse'
            )
        );
    }

    public function getSearchIn()
    {
        return [
            'ref',
            'invoice_ref',
            'delivery_ref',
            'transaction_ref',
            'customer_ref',
            'customer_firstname',
            'customer_lastname',
            'customer_email',
        ];
    }

    /**
     * @param OrderQuery $search
     *
     * @throws \Propel\Runtime\Exception\PropelException
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
                case 'invoice_ref':
                    $search->filterByInvoiceRef($searchTerm, $searchCriteria);
                    break;
                case 'delivery_ref':
                    $search->filterByDeliveryRef($searchTerm, $searchCriteria);
                    break;
                case 'transaction_ref':
                    $search->filterByTransactionRef($searchTerm, $searchCriteria);
                    break;
                case 'customer_ref':
                    $search->filterByCustomer(
                        CustomerQuery::create()->filterByRef($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case 'customer_firstname':
                    $search->filterByOrderAddressRelatedByInvoiceOrderAddressId(
                        OrderAddressQuery::create()->filterByFirstname($searchTerm, $searchCriteria)->find()
                    );
                    $search->_or();
                    $search->filterByOrderAddressRelatedByDeliveryOrderAddressId(
                        OrderAddressQuery::create()->filterByFirstname($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case 'customer_lastname':
                    $search->filterByOrderAddressRelatedByInvoiceOrderAddressId(
                        OrderAddressQuery::create()->filterByLastname($searchTerm, $searchCriteria)->find()
                    );
                    $search->_or();
                    $search->filterByOrderAddressRelatedByDeliveryOrderAddressId(
                        OrderAddressQuery::create()->filterByLastname($searchTerm, $searchCriteria)->find()
                    );
                    break;
                case 'customer_email':
                    $search->filterByCustomer(
                        CustomerQuery::create()->filterByEmail($searchTerm, $searchCriteria)->find()
                    );
                    break;
            }
        }
    }

    public function buildModelCriteria()
    {
        $search = OrderQuery::create();

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $ref = $this->getRef()) {
            $search->filterByRef($ref, Criteria::IN);
        }

        if (null !== $ref = $this->getDeliveryRef()) {
            $search->filterByDeliveryRef($ref, Criteria::IN);
        }

        if (null !== $ref = $this->getInvoiceRef()) {
            $search->filterByInvoiceRef($ref, Criteria::IN);
        }

        if (null !== $ref = $this->getTransactionRef()) {
            $search->filterByTransactionRef($ref, Criteria::IN);
        }

        $customer = $this->getCustomer();

        if ($customer === 'current') {
            $currentCustomer = $this->securityContext->getCustomerUser();
            if ($currentCustomer === null) {
                return null;
            }

            $search->filterByCustomerId($currentCustomer->getId(), Criteria::EQUAL);
        } elseif ($customer !== '*') {
            $search->filterByCustomerId($customer, Criteria::EQUAL);
        }

        $status = $this->getStatus();

        if (null !== $status && $status !== '*') {
            $search->filterByStatusId($status, Criteria::IN);
        }

        if (null !== $excludeStatus = $this->getExcludeStatus()) {
            $search->filterByStatusId($excludeStatus, Criteria::NOT_IN);
        }

        $statusCode = $this->getStatusCode();

        if (null !== $statusCode && $statusCode !== '*') {
            $search
                ->useOrderStatusQuery()
                ->filterByCode($statusCode, Criteria::IN)
                ->endUse();
        }

        if (null !== $excludeStatusCode = $this->getExcludeStatusCode()) {
            $search
                ->useOrderStatusQuery()
                ->filterByCode($excludeStatusCode, Criteria::NOT_IN)
                ->endUse();
        }

        $orderers = $this->getOrder();

        foreach ($orderers as $orderer) {
            switch ($orderer) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'reference':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'reference-reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;
                case 'create-date':
                    $search->orderByCreatedAt(Criteria::ASC);
                    break;
                case 'create-date-reverse':
                    $search->orderByCreatedAt(Criteria::DESC);
                    break;
                case 'invoice-date':
                    $search->orderByInvoiceDate(Criteria::ASC);
                    break;
                case 'invoice-date-reverse':
                    $search->orderByInvoiceDate(Criteria::DESC);
                    break;
                case 'status':
                    $search->orderByStatusId(Criteria::ASC);
                    break;
                case 'status-reverse':
                    $search->orderByStatusId(Criteria::DESC);
                    break;
                case 'company':
                    $search
                        ->joinOrderAddressRelatedByDeliveryOrderAddressId()
                        ->withColumn(OrderAddressTableMap::COL_COMPANY, 'company')
                        ->orderBy('company', Criteria::ASC);
                    break;
                case 'company-reverse':
                    $search
                        ->joinOrderAddressRelatedByDeliveryOrderAddressId()
                        ->withColumn(OrderAddressTableMap::COL_COMPANY, 'company')
                        ->orderBy('company', Criteria::DESC);
                    break;
                case 'customer-name':
                    $search
                        ->joinCustomer()
                        ->withColumn(CustomerTableMap::COL_FIRSTNAME, 'firstname')
                        ->withColumn(CustomerTableMap::COL_LASTNAME, 'lastname')
                        ->orderBy('lastname', Criteria::ASC)
                        ->orderBy('firstname', Criteria::ASC);
                    break;
                case 'customer-name-reverse':
                    $search
                        ->joinCustomer()
                        ->withColumn(CustomerTableMap::COL_FIRSTNAME, 'firstname')
                        ->withColumn(CustomerTableMap::COL_LASTNAME, 'lastname')
                        ->orderBy('lastname', Criteria::DESC)
                        ->orderBy('firstname', Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return LoopResult
	 * 
	 * #doc-out-name $CURRENCY
	 * #doc-out-desc the order currency id ; you can use it in a currency loop
	 * 
	 * #doc-out-name $CURRENCY_RATE
	 * #doc-out-desc the order currency rate
	 * 
	 * #doc-out-name $CUSTOMER
	 * #doc-out-desc the order customer id ; you can use it in a customer loop
	 * 
	 * #doc-out-name $DELIVERY_ADDRESS
	 * #doc-out-desc the order delivery address id ; you can use it in a order address loop
	 * 
	 * #doc-out-name $DELIVERY_MODULE
	 * #doc-out-desc the order delivery module id ; you can use it in a module loop
	 * 
	 * #doc-out-name $DELIVERY_REF
	 * #doc-out-desc the order delivery reference. It's usually use for tracking package
	 * 
	 * #doc-out-name $DISCOUNT
	 * #doc-out-desc the order discount
	 * 
	 * #doc-out-name $HAS_NEXT
	 * #doc-out-desc true if a order exists after this one, following orders id.
	 * 
	 * #doc-out-name $HAS_PAID_STATUS
	 * #doc-out-desc True is the order has the 'paid' status, false otherwise
	 * 
	 * #doc-out-name $HAS_PREVIOUS
	 * #doc-out-desc true if a order exists before this one following orders id.
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the order id
	 * 
	 * #doc-out-name $INVOICE_ADDRESS
	 * #doc-out-desc the order the order invoice address id ; you can use it in a order address loop
	 * 
	 * #doc-out-name $INVOICE_REF
	 * #doc-out-desc the order invoice reference
	 * 
	 * #doc-out-name $IS_CANCELED
	 * #doc-out-desc True is the order has the 'canceled' status, false otherwise
	 * 
	 * #doc-out-name $IS_NOT_PAID
	 * #doc-out-desc True is the order has the 'not paid' status, false otherwise
	 * 
	 * #doc-out-name $IS_PAID
	 * #doc-out-desc True is the order has been paid (whatever current status is), false otherwise
	 * 
	 * #doc-out-name $IS_PROCESSING
	 * #doc-out-desc True is the order has the 'processing' status, false otherwise
	 * 
	 * #doc-out-name $IS_SENT
	 * #doc-out-desc True is the order has the 'sent' status, false otherwise
	 * 
	 * #doc-out-name $LANG
	 * #doc-out-desc the order language id
	 * 
	 * #doc-out-name $NEXT
	 * #doc-out-desc The ID of order after this one, following orders id, or null if none exists.
	 * 
	 * #doc-out-name $PAYMENT_MODULE
	 * #doc-out-desc the order payment module id ; you can use it in a module loop
	 * 
	 * #doc-out-name $POSTAGE
	 * #doc-out-desc the order postage
	 * 
	 * #doc-out-name $POSTAGE_TAX
	 * #doc-out-desc the order postage tax
	 * 
	 * #doc-out-name $POSTAGE_TAX_RULE_TITLE
	 * #doc-out-desc the tax rule used to get the postage tax amount
	 * 
	 * #doc-out-name $POSTAGE_UNTAXED
	 * #doc-out-desc the order postage amount without tax
	 * 
	 * #doc-out-name $PREVIOUS
	 * #doc-out-desc The ID of order before this one, following orders id, or null if none exists.
	 * 
	 * #doc-out-name $REF
	 * #doc-out-desc the order reference
	 * 
	 * #doc-out-name $STATUS
	 * #doc-out-desc the order status ; you can use it in a order status loop
	 * 
	 * #doc-out-name $STATUS_CODE
	 * #doc-out-desc the order status code
	 * 
	 * #doc-out-name $TOTAL_AMOUNT
	 * #doc-out-desc the order amount without taxes
	 * 
	 * #doc-out-name $TOTAL_ITEMS_AMOUNT
	 * #doc-out-desc the total amount for ordered items, excluding taxes
	 * 
	 * #doc-out-name $TOTAL_ITEMS_TAX
	 * #doc-out-desc the total tax amount for of the ordered items only, without postage tax
	 * 
	 * #doc-out-name $TOTAL_TAX
	 * #doc-out-desc the order taxes amount
	 * 
	 * #doc-out-name $TOTAL_TAXED_AMOUNT
	 * #doc-out-desc the order amount including taxes
	 * 
	 * #doc-out-name $TOTAL_TAXED_ITEMS_AMOUNT
	 * #doc-out-desc the total amount for ordered items, including taxes
	 * 
	 * #doc-out-name $TRANSACTION_REF
	 * #doc-out-desc the order transaction reference. It's usually the unique identifier shared between the e-shop and it's bank
	 * 
	 * #doc-out-name $VIRTUAL
	 * #doc-out-desc the order has at least one product which is a virtual product
	 * 
	 * #doc-out-name $WEIGHT
	 * #doc-out-desc The total weight of the order
	 * 
	 * #doc-out-name $INVOICE_DATE
	 * #doc-out-desc the order invoice date
	 * 
	 * #doc-out-name $DISCOUNT_WITHOUT_TAX
	 * #doc-out-desc the order discount without tax
	 * 
	 * #doc-out-name $DISCOUNT_TAX
	 * #doc-out-desc the tax amount applied to the order discount
     */
    public function parseResults(LoopResult $loopResult)
    {
        $lastLegacyOrderId = ConfigQuery::read('last_legacy_rounding_order_id', 0);

        /** @var \Thelia\Model\Order $order */
        foreach ($loopResult->getResultDataCollection() as $order) {
            $tax = $itemsTax = 0;

            $amount = $order->getTotalAmount($tax);
            $itemsAmount = $order->getTotalAmount($itemsTax, false, false);

            // Legacy orders have no discount tax calculation
            if ($order->getId() <= $lastLegacyOrderId) {
                $discountWithoutTax = $order->getDiscount();
            } else {
                $discountWithoutTax = Calculator::getUntaxedOrderDiscount($order);
            }

            $hasVirtualDownload = $order->hasVirtualProduct();

            $loopResultRow = new LoopResultRow($order);
            $loopResultRow
                ->set('ID', $order->getId())
                ->set('REF', $order->getRef())
                ->set('CUSTOMER', $order->getCustomerId())
                ->set('DELIVERY_ADDRESS', $order->getDeliveryOrderAddressId())
                ->set('INVOICE_ADDRESS', $order->getInvoiceOrderAddressId())
                ->set('INVOICE_DATE', $order->getInvoiceDate())
                ->set('CURRENCY', $order->getCurrencyId())
                ->set('CURRENCY_RATE', $order->getCurrencyRate())
                ->set('TRANSACTION_REF', $order->getTransactionRef())
                ->set('DELIVERY_REF', $order->getDeliveryRef())
                ->set('INVOICE_REF', $order->getInvoiceRef())
                ->set('VIRTUAL', $hasVirtualDownload)
                ->set('POSTAGE', $order->getPostage())
                ->set('POSTAGE_TAX', $order->getPostageTax())
                ->set('POSTAGE_UNTAXED', $order->getUntaxedPostage())
                ->set('POSTAGE_TAX_RULE_TITLE', $order->getPostageTaxRuleTitle())
                ->set('PAYMENT_MODULE', $order->getPaymentModuleId())
                ->set('DELIVERY_MODULE', $order->getDeliveryModuleId())
                ->set('STATUS', $order->getStatusId())
                ->set('STATUS_CODE', $order->getOrderStatus()->getCode())
                ->set('LANG', $order->getLangId())
                ->set('DISCOUNT', $order->getDiscount())
                ->set('DISCOUNT_WITHOUT_TAX', $discountWithoutTax)
                ->set('DISCOUNT_TAX', $order->getDiscount() - $discountWithoutTax)
                ->set('TOTAL_ITEMS_TAX', $itemsTax)
                ->set('TOTAL_ITEMS_AMOUNT', $itemsAmount - $itemsTax)
                ->set('TOTAL_TAXED_ITEMS_AMOUNT', $itemsAmount)
                ->set('TOTAL_TAX', $tax)
                ->set('TOTAL_AMOUNT', $amount - $tax)
                ->set('TOTAL_TAXED_AMOUNT', $amount)
                ->set('WEIGHT', $order->getWeight())
                ->set('HAS_PAID_STATUS', $order->isPaid())
                ->set('IS_PAID', $order->isPaid(false))
                ->set('IS_CANCELED', $order->isCancelled())
                ->set('IS_NOT_PAID', $order->isNotPaid())
                ->set('IS_SENT', $order->isSent())
                ->set('IS_PROCESSING', $order->isProcessing());

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = OrderQuery::create()
                    ->filterById($order->getId(), Criteria::LESS_THAN)
                    ->filterByStatusId($order->getStatusId(), Criteria::EQUAL);

                $previous = $previousQuery
                    ->orderById(Criteria::DESC)
                    ->findOne();

                $nextQuery = OrderQuery::create()
                    ->filterById($order->getId(), Criteria::GREATER_THAN)
                    ->filterByStatusId($order->getStatusId(), Criteria::EQUAL);

                $next = $nextQuery
                    ->orderById(Criteria::ASC)
                    ->findOne();

                $loopResultRow
                    ->set('HAS_PREVIOUS', $previous !== null ? 1 : 0)
                    ->set('HAS_NEXT', $next !== null ? 1 : 0)
                    ->set('PREVIOUS', $previous !== null ? $previous->getId() : -1)
                    ->set('NEXT', $next !== null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $order);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
