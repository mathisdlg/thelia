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

    public function getArgDefinitions()
    {
        return new ArgumentCollection(
		    // #doc-arg-desc A single or a list of order ids.
            Argument::createIntListTypeArgument('id'),
		    // #doc-arg-desc A single or a list of references.
            Argument::createAnyListTypeArgument('ref'),
		    // #doc-arg-desc A single or a list of invoice references.
            Argument::createAnyListTypeArgument('invoice_ref'),
		    // #doc-arg-desc A single or a list of delivery references.
            Argument::createAnyListTypeArgument('delivery_ref'),
		    // #doc-arg-desc A single or a list of transaction references.
            Argument::createAnyListTypeArgument('transaction_ref'),
		    // #doc-arg-desc A boolean. If set to true, $PREVIOUS and $NEXT output arguments are available.
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            // #doc-arg-desc A single customer id or `current` keyword to get logged in user or `*` keyword to match all users.
            new Argument(
                'customer',
                new TypeCollection(
                    new Type\IntType(),
                    new Type\EnumType(['current', '*'])
                ),
                'current'
            ),
            // #doc-arg-desc A single or a list of order status ID or `*` keyword to match all
            new Argument(
                'status',
                new TypeCollection(
                    new Type\IntListType(),
                    new Type\EnumType(['*'])
                )
            ),
		    // #doc-arg-desc A single or a list of order status ID which are to be excluded from the results
            Argument::createIntListTypeArgument('exclude_status'),
            new Argument(
                'status_code',
                new TypeCollection(
                    new Type\AnyListType(),
                    new Type\EnumType(['*'])
                )
            ),
		    // #doc-arg-desc A single or a list of order status codes which are to be excluded from the results. The valid status codes are not_paid, paid, processing, sent, canceled, or any custom status that may be defined
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
		        // #doc-out-desc the order id
                ->set('ID', $order->getId())
		        // #doc-out-desc the order reference
                ->set('REF', $order->getRef())
		        // #doc-out-desc the order customer id ; you can use it in a customer loop
                ->set('CUSTOMER', $order->getCustomerId())
		        // #doc-out-desc the order delivery address id ; you can use it in a order address loop
                ->set('DELIVERY_ADDRESS', $order->getDeliveryOrderAddressId())
		        // #doc-out-desc the order the order invoice address id ; you can use it in a order address loop
                ->set('INVOICE_ADDRESS', $order->getInvoiceOrderAddressId())
		        // #doc-out-desc the order invoice date
                ->set('INVOICE_DATE', $order->getInvoiceDate())
		        // #doc-out-desc the order currency id ; you can use it in a currency loop
                ->set('CURRENCY', $order->getCurrencyId())
		        // #doc-out-desc the order currency rate
                ->set('CURRENCY_RATE', $order->getCurrencyRate())
		        // #doc-out-desc the order transaction reference. It's usually the unique identifier shared between the e-shop and it's bank
                ->set('TRANSACTION_REF', $order->getTransactionRef())
		        // #doc-out-desc the order delivery reference. It's usually use for tracking package
                ->set('DELIVERY_REF', $order->getDeliveryRef())
		        // #doc-out-desc the order invoice reference
                ->set('INVOICE_REF', $order->getInvoiceRef())
		        // #doc-out-desc the order has at least one product which is a virtual product
                ->set('VIRTUAL', $hasVirtualDownload)
		        // #doc-out-desc the order postage
                ->set('POSTAGE', $order->getPostage())
		        // #doc-out-desc the order postage tax
                ->set('POSTAGE_TAX', $order->getPostageTax())
		        // #doc-out-desc the order postage amount without tax
                ->set('POSTAGE_UNTAXED', $order->getUntaxedPostage())
		        // #doc-out-desc the tax rule used to get the postage tax amount
                ->set('POSTAGE_TAX_RULE_TITLE', $order->getPostageTaxRuleTitle())
		        // #doc-out-desc the order payment module id ; you can use it in a module loop
                ->set('PAYMENT_MODULE', $order->getPaymentModuleId())
		        // #doc-out-desc the order delivery module id ; you can use it in a module loop
                ->set('DELIVERY_MODULE', $order->getDeliveryModuleId())
		        // #doc-out-desc the order status ; you can use it in a order status loop
                ->set('STATUS', $order->getStatusId())
		        // #doc-out-desc the order status code
                ->set('STATUS_CODE', $order->getOrderStatus()->getCode())
		        // #doc-out-desc the order language id
                ->set('LANG', $order->getLangId())
		        // #doc-out-desc the order discount
                ->set('DISCOUNT', $order->getDiscount())
		        // #doc-out-desc the order discount without tax
                ->set('DISCOUNT_WITHOUT_TAX', $discountWithoutTax)
		        // #doc-out-desc the tax amount applied to the order discount
                ->set('DISCOUNT_TAX', $order->getDiscount() - $discountWithoutTax)
		        // #doc-out-desc the total tax amount for of the ordered items only, without postage tax
                ->set('TOTAL_ITEMS_TAX', $itemsTax)
		        // #doc-out-desc the total amount for ordered items, excluding taxes
                ->set('TOTAL_ITEMS_AMOUNT', $itemsAmount - $itemsTax)
		        // #doc-out-desc the total amount for ordered items, including taxes
                ->set('TOTAL_TAXED_ITEMS_AMOUNT', $itemsAmount)
		        // #doc-out-desc the order taxes amount
                ->set('TOTAL_TAX', $tax)
		        // #doc-out-desc the order amount without taxes
                ->set('TOTAL_AMOUNT', $amount - $tax)
		        // #doc-out-desc the order amount including taxes
                ->set('TOTAL_TAXED_AMOUNT', $amount)
		        // #doc-out-desc The total weight of the order
                ->set('WEIGHT', $order->getWeight())
		        // #doc-out-desc True is the order has the 'paid' status, false otherwise
                ->set('HAS_PAID_STATUS', $order->isPaid())
		        // #doc-out-desc True is the order has been paid (whatever current status is), false otherwise
                ->set('IS_PAID', $order->isPaid(false))
		        // #doc-out-desc True is the order has the 'canceled' status, false otherwise
                ->set('IS_CANCELED', $order->isCancelled())
		        // #doc-out-desc True is the order has the 'not paid' status, false otherwise
                ->set('IS_NOT_PAID', $order->isNotPaid())
		        // #doc-out-desc True is the order has the 'sent' status, false otherwise
                ->set('IS_SENT', $order->isSent())
		        // #doc-out-desc True is the order has the 'processing' status, false otherwise
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
		            // #doc-out-desc true if a order exists before this one following orders id.
                    ->set('HAS_PREVIOUS', $previous !== null ? 1 : 0)
		            // #doc-out-desc true if a order exists after this one, following orders id.
                    ->set('HAS_NEXT', $next !== null ? 1 : 0)
		            // #doc-out-desc The ID of order before this one, following orders id, or null if none exists.
                    ->set('PREVIOUS', $previous !== null ? $previous->getId() : -1)
		            // #doc-out-desc The ID of order after this one, following orders id, or null if none exists.
                    ->set('NEXT', $next !== null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $order);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
