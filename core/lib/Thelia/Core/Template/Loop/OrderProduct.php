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
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of order product ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name order *
	 * #doc-arg-desc A single order id.
	 * #doc-arg-example order="2"
	 * 
	 * #doc-arg-name virtual
	 * #doc-arg-desc A boolean value.
	 * #doc-arg-example new="yes"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order', null, true),
            Argument::createIntListTypeArgument('id'),
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
	 * 
	 * #doc-out-name $CART_ITEM_ID
	 * #doc-out-desc The related Cart Item ID of this order product
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the order product short description
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the order product description
	 * 
	 * #doc-out-name $EAN_CODE
	 * #doc-out-desc the product ean code
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the order product id
	 * 
	 * #doc-out-name $PRODUCT_ID
	 * #doc-out-desc the product id
	 * 
	 * #doc-out-name $PARENT
	 * #doc-out-desc the parent product in the cart, if the current product has one
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the order product postscriptum
	 * 
	 * #doc-out-name $PRICE
	 * #doc-out-desc the order product price (unit price)
	 * 
	 * #doc-out-name $PRICE_TAX
	 * #doc-out-desc the order product taxes (unit price)
	 * 
	 * #doc-out-name $PRODUCT_SALE_ELEMENTS_ID
	 * #doc-out-desc the order product sale elements id
	 * 
	 * #doc-out-name $PRODUCT_SALE_ELEMENTS_REF
	 * #doc-out-desc the order product sale elements reference
	 * 
	 * #doc-out-name $PROMO_PRICE
	 * #doc-out-desc the order product in promo price (unit price)
	 * 
	 * #doc-out-name $PROMO_PRICE_TAX
	 * #doc-out-desc the order product in promo price taxes (unit price)
	 * 
	 * #doc-out-name $QUANTITY
	 * #doc-out-desc the order product ordered quantity
	 * 
	 * #doc-out-name $REF
	 * #doc-out-desc the order product reference
	 * 
	 * #doc-out-name $TAXED_PRICE
	 * #doc-out-desc the order product price including taxes (unit price)
	 * 
	 * #doc-out-name $TAXED_PROMO_PRICE
	 * #doc-out-desc the order product in promo price including taxes (unit price)
	 * 
	 * #doc-out-name $TAX_RULE_DESCRIPTION
	 * #doc-out-desc the tax rule description for this item
	 * 
	 * #doc-out-name $TAX_RULE_TITLE
	 * #doc-out-desc the tax rule title for this item
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the order product title
	 * 
	 * #doc-out-name $TOTAL_PRICE
	 * #doc-out-desc the order product price (total price)
	 * 
	 * #doc-out-name $TOTAL_PROMO_PRICE
	 * #doc-out-desc the order product in promo price (total price)
	 * 
	 * #doc-out-name $TOTAL_TAXED_PRICE
	 * #doc-out-desc the order product price including taxes (total price)
	 * 
	 * #doc-out-name $TOTAL_TAXED_PROMO_PRICE
	 * #doc-out-desc the order product in promo price including taxes (total price)
	 * 
	 * #doc-out-name $VIRTUAL
	 * #doc-out-desc whatever the order product is a virtual product or not
	 * 
	 * #doc-out-name $VIRTUAL_DOCUMENT
	 * #doc-out-desc the name of the file if the product is virtual.
	 * 
	 * #doc-out-name $WAS_IN_PROMO
	 * #doc-out-desc whatever the order product sale elements was in promo or not
	 * 
	 * #doc-out-name $WAS_NEW
	 * #doc-out-desc whatever the order product sale elements was new or not
	 * 
	 * #doc-out-name $WEIGHT
	 * #doc-out-desc the order product sale elements weight
	 * 
	 * #doc-out-name $REAL_PRICE
	 * #doc-out-desc the real price of the product
	 * 
	 * #doc-out-name $REAL_TAXED_PRICE
	 * #doc-out-desc the real price of the product including taxes
	 * 
	 * #doc-out-name $REAL_PRICE_TAX
	 * #doc-out-desc the real price of the taxe for the product
	 * 
	 * #doc-out-name $REAL_TOTAL_PRICE
	 * #doc-out-desc the real total price of the product
	 * 
	 * #doc-out-name $REAL_TOTAL_TAXED_PRICE
	 * #doc-out-desc the real total price of the product including taxes
	 * 
	 * #doc-out-name $REAL_TOTAL_PRICE_TAX
	 * #doc-out-desc the real total price of the taxe for the product
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

            $loopResultRow->set('ID', $orderProduct->getId())
                ->set('REF', $orderProduct->getProductRef())
                ->set('PRODUCT_ID', $orderProduct->getVirtualColumn('product_id'))
                ->set('PRODUCT_SALE_ELEMENTS_ID', $orderProduct->getProductSaleElementsId())
                ->set('PRODUCT_SALE_ELEMENTS_REF', $orderProduct->getProductSaleElementsRef())
                ->set('WAS_NEW', $orderProduct->getWasNew() === 1 ? 1 : 0)
                ->set('WAS_IN_PROMO', $orderProduct->getWasInPromo() === 1 ? 1 : 0)
                ->set('WEIGHT', $orderProduct->getWeight())
                ->set('TITLE', $orderProduct->getTitle())
                ->set('CHAPO', $orderProduct->getChapo())
                ->set('DESCRIPTION', $orderProduct->getDescription())
                ->set('POSTSCRIPTUM', $orderProduct->getPostscriptum())
                ->set('VIRTUAL', $orderProduct->getVirtual())
                ->set('VIRTUAL_DOCUMENT', $orderProduct->getVirtualDocument())
                ->set('QUANTITY', $orderProduct->getQuantity())

                ->set('PRICE', $orderProduct->getPrice())
                ->set('PRICE_TAX', $tax)
                ->set('TAXED_PRICE', $taxedPrice)
                ->set('PROMO_PRICE', $orderProduct->getPromoPrice())
                ->set('PROMO_PRICE_TAX', $promoTax)
                ->set('TAXED_PROMO_PRICE', $taxedPromoPrice)
                ->set('TOTAL_PRICE', $totalPrice)
                ->set('TOTAL_TAXED_PRICE', $totalTaxedPrice)
                ->set('TOTAL_PROMO_PRICE', $totalPromoPrice)
                ->set('TOTAL_TAXED_PROMO_PRICE', $totalTaxedPromoPrice)

                ->set('TAX_RULE_TITLE', $orderProduct->getTaxRuleTitle())
                ->set('TAX_RULE_DESCRIPTION', $orderProduct->getTaxRuledescription())
                ->set('PARENT', $orderProduct->getParent())
                ->set('EAN_CODE', $orderProduct->getEanCode())
                ->set('CART_ITEM_ID', $orderProduct->getCartItemId())

                ->set('REAL_PRICE', $orderProduct->getWasInPromo() ? $orderProduct->getPromoPrice() : $orderProduct->getPrice())
                ->set('REAL_TAXED_PRICE', $orderProduct->getWasInPromo() ? $taxedPromoPrice : $taxedPrice)
                ->set('REAL_PRICE_TAX', $orderProduct->getWasInPromo() ? $promoTax : $tax)

                ->set('REAL_TOTAL_PRICE', $orderProduct->getWasInPromo() ? $totalPromoPrice : $totalPrice)
                ->set('REAL_TOTAL_TAXED_PRICE', $orderProduct->getWasInPromo() ? $totalTaxedPromoPrice : $totalTaxedPrice)
                ->set('REAL_TOTAL_PRICE_TAX', $orderProduct->getWasInPromo() ? $totalPromoTax : $totalTax)

            ;
            $this->addOutputFields($loopResultRow, $orderProduct);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
