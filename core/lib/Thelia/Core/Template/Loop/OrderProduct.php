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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * OrderProduct loop.
 *
 * Class OrderProduct
 * 
 * 
 * #doc-desc Order product loop displays Order products information.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int         getOrder()
 * @method int[]       getId()
 * @method bool|string getVirtual()
 */
class OrderProduct extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single order id.
            Argument::createIntTypeArgument('order', null, true),
            // #doc-arg-desc A single or a list of order product ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('virtual', BooleanOrBothType::ANY)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductQuery::create();

        $search->joinOrderProductTax('opt', Criteria::LEFT_JOIN)
            ->withColumn('SUM(`opt`.AMOUNT)', 'TOTAL_TAX')
            ->withColumn('SUM(`opt`.PROMO_AMOUNT)', 'TOTAL_PROMO_TAX')
            ->groupById();

        // new join to get the product id if it exists
        $pseJoin = new Join(
            OrderProductTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
            ProductSaleElementsTableMap::COL_ID,
            Criteria::LEFT_JOIN
        );
        $search
            ->addJoinObject($pseJoin)
            ->addAsColumn(
                'product_id',
                ProductSaleElementsTableMap::COL_PRODUCT_ID
            )
        ;

        $order = $this->getOrder();

        $search->filterByOrderId($order, Criteria::EQUAL);

        $virtual = $this->getVirtual();
        if ($virtual !== BooleanOrBothType::ANY) {
            if ($virtual) {
                $search
                    ->filterByVirtual(1, Criteria::EQUAL)
                    ->filterByVirtualDocument(null, Criteria::NOT_EQUAL);
            } else {
                $search
                    ->filterByVirtual(0);
            }
        }

        if (null !== $this->getId()) {
            $search->filterById($this->getId(), Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        return $search;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        $lastLegacyRoundingOrderId = ConfigQuery::read('last_legacy_rounding_order_id', 0);

        /** @var \Thelia\Model\OrderProduct $orderProduct */
        foreach ($loopResult->getResultDataCollection() as $orderProduct) {
            $loopResultRow = new LoopResultRow($orderProduct);

            $tax = $orderProduct->getVirtualColumn('TOTAL_TAX');
            $promoTax = $orderProduct->getVirtualColumn('TOTAL_PROMO_TAX');

            // To prevent price changes in pre-2.4 orders, use the legacy calculation method
            if ($orderProduct->getOrderId() <= $lastLegacyRoundingOrderId) {
                $totalTax = round($tax * $orderProduct->getQuantity(), 2);
                $totalPromoTax = round($promoTax * $orderProduct->getQuantity(), 2);

                $taxedPrice = (float) $orderProduct->getPrice() + (float) $orderProduct->getVirtualColumn('TOTAL_TAX');
                $taxedPromoPrice = (float) $orderProduct->getPromoPrice() + (float) $orderProduct->getVirtualColumn('TOTAL_PROMO_TAX');

                $totalPrice = $orderProduct->getPrice() * $orderProduct->getQuantity();
                $totalPromoPrice = $orderProduct->getPromoPrice() * $orderProduct->getQuantity();

                $totalTaxedPrice = round($taxedPrice, 2) * $orderProduct->getQuantity();
                $totalTaxedPromoPrice = round($taxedPromoPrice, 2) * $orderProduct->getQuantity();
            } else {
                $tax = round($tax, 2);
                $promoTax = round($promoTax, 2);

                $totalTax = $tax * $orderProduct->getQuantity();
                $totalPromoTax = $promoTax * $orderProduct->getQuantity();

                $taxedPrice = round((float) $orderProduct->getPrice() + $tax, 2);
                $taxedPromoPrice = round((float) $orderProduct->getPromoPrice() + $promoTax, 2);

                // Price calculation should use the same rounding method as in CartItem::getTotalTaxedPromoPrice()
                // For each order line, we first round the taxed price, then we multiply by the quantity.
                $totalPrice = round($orderProduct->getPrice(), 2) * $orderProduct->getQuantity();
                $totalPromoPrice = round($orderProduct->getPromoPrice(), 2) * $orderProduct->getQuantity();

                $totalTaxedPrice = $taxedPrice * $orderProduct->getQuantity();
                $totalTaxedPromoPrice = $taxedPromoPrice * $orderProduct->getQuantity();
            }

            // #doc-out-desc the order product id
            $loopResultRow->set('ID', $orderProduct->getId())
                // #doc-out-desc the order product reference
                ->set('REF', $orderProduct->getProductRef())
                // #doc-out-desc the product id
                ->set('PRODUCT_ID', $orderProduct->getVirtualColumn('product_id'))
                // #doc-out-desc the order product sale elements id
                ->set('PRODUCT_SALE_ELEMENTS_ID', $orderProduct->getProductSaleElementsId())
                // #doc-out-desc the order product sale elements reference
                ->set('PRODUCT_SALE_ELEMENTS_REF', $orderProduct->getProductSaleElementsRef())
                // #doc-out-desc whatever the order product sale elements was new or not
                ->set('WAS_NEW', $orderProduct->getWasNew() === 1 ? 1 : 0)
                // #doc-out-desc whatever the order product sale elements was in promo or not
                ->set('WAS_IN_PROMO', $orderProduct->getWasInPromo() === 1 ? 1 : 0)
                // #doc-out-desc the order product sale elements weight
                ->set('WEIGHT', $orderProduct->getWeight())
                // #doc-out-desc the order product title
                ->set('TITLE', $orderProduct->getTitle())
                // #doc-out-desc the order product short description
                ->set('CHAPO', $orderProduct->getChapo())
                // #doc-out-desc the order product description
                ->set('DESCRIPTION', $orderProduct->getDescription())
                // #doc-out-desc the order product postscriptum
                ->set('POSTSCRIPTUM', $orderProduct->getPostscriptum())
                // #doc-out-desc whatever the order product is a virtual product or not
                ->set('VIRTUAL', $orderProduct->getVirtual())
                // #doc-out-desc the name of the file if the product is virtual.
                ->set('VIRTUAL_DOCUMENT', $orderProduct->getVirtualDocument())
                // #doc-out-desc the order product ordered quantity
                ->set('QUANTITY', $orderProduct->getQuantity())

                // #doc-out-desc the order product price (unit price)
                ->set('PRICE', $orderProduct->getPrice())
                // #doc-out-desc the order product taxes (unit price)
                ->set('PRICE_TAX', $tax)
                // #doc-out-desc the order product price including taxes (unit price)
                ->set('TAXED_PRICE', $taxedPrice)
                // #doc-out-desc the order product in promo price (unit price)
                ->set('PROMO_PRICE', $orderProduct->getPromoPrice())
                // #doc-out-desc the order product in promo price taxes (unit price)
                ->set('PROMO_PRICE_TAX', $promoTax)
                // #doc-out-desc the order product in promo price including taxes (unit price)
                ->set('TAXED_PROMO_PRICE', $taxedPromoPrice)
                // #doc-out-desc the order product price (total price)
                ->set('TOTAL_PRICE', $totalPrice)
                // #doc-out-desc the order product price including taxes (total price)
                ->set('TOTAL_TAXED_PRICE', $totalTaxedPrice)
                // #doc-out-desc the order product in promo price (total price)
                ->set('TOTAL_PROMO_PRICE', $totalPromoPrice)
                // #doc-out-desc the order product in promo price including taxes (total price)
                ->set('TOTAL_TAXED_PROMO_PRICE', $totalTaxedPromoPrice)

                // #doc-out-desc the tax rule title for this item
                ->set('TAX_RULE_TITLE', $orderProduct->getTaxRuleTitle())
                // #doc-out-desc the tax rule description for this item
                ->set('TAX_RULE_DESCRIPTION', $orderProduct->getTaxRuledescription())
                // #doc-out-desc the parent product in the cart, if the current product has one
                ->set('PARENT', $orderProduct->getParent())
                // #doc-out-desc the product ean code
                ->set('EAN_CODE', $orderProduct->getEanCode())
                // #doc-out-desc The related Cart Item ID of this order product
                ->set('CART_ITEM_ID', $orderProduct->getCartItemId())

                // #doc-out-desc the real price of the product
                ->set('REAL_PRICE', $orderProduct->getWasInPromo() ? $orderProduct->getPromoPrice() : $orderProduct->getPrice())
                // #doc-out-desc the real price of the product including taxes
                ->set('REAL_TAXED_PRICE', $orderProduct->getWasInPromo() ? $taxedPromoPrice : $taxedPrice)
                // #doc-out-desc the real price of the taxe for the product
                ->set('REAL_PRICE_TAX', $orderProduct->getWasInPromo() ? $promoTax : $tax)

                // #doc-out-desc the real total price of the product
                ->set('REAL_TOTAL_PRICE', $orderProduct->getWasInPromo() ? $totalPromoPrice : $totalPrice)
                // #doc-out-desc the real total price of the product including taxes
                ->set('REAL_TOTAL_TAXED_PRICE', $orderProduct->getWasInPromo() ? $totalTaxedPromoPrice : $totalTaxedPrice)
                // #doc-out-desc the real total price of the taxe for the product
                ->set('REAL_TOTAL_PRICE_TAX', $orderProduct->getWasInPromo() ? $totalPromoTax : $totalTax)

            ;
            $this->addOutputFields($loopResultRow, $orderProduct);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
