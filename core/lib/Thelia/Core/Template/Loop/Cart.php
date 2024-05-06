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

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CartItem as CartItemModel;
use Thelia\Model\ConfigQuery;
use Thelia\Type;

/**
 * Cart Loop.
 *
 * Class Cart
 * 
 * #doc-desc Cart loop displays cart information.
 *
 * @method string[] getOrder()
 */
class Cart extends BaseLoop implements ArraySearchLoopInterface
{
    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     * 
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A list of values
            new Argument(
                'order',
                new Type\TypeCollection(
                    new Type\EnumListType(['normal', 'reverse'])
                ),
                'normal'
            )
        );
    }

    public function buildArray()
    {
        /** @var CartModel $cart */
        $cart = $this->getCurrentRequest()->getSession()->getSessionCart($this->getDispatcher());

        if (null === $cart) {
            return [];
        }

        $returnArray = iterator_to_array($cart->getCartItems());

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'reverse':
                    $returnArray = array_reverse($returnArray, false);
                    break;
            }
        }

        return $returnArray;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();
        $locale = $this->getCurrentRequest()->getSession()->getLang()->getLocale();
        $checkAvailability = ConfigQuery::checkAvailableStock();
        $defaultAvailability = (int) ConfigQuery::read('default-available-stock', 100);

        /** @var CartItemModel $cartItem */
        foreach ($loopResult->getResultDataCollection() as $cartItem) {
            $product = $cartItem->getProduct(null, $locale);
            $productSaleElement = $cartItem->getProductSaleElements();

            $loopResultRow = new LoopResultRow($cartItem);

            // #doc-out-desc the cart item id
            $loopResultRow->set('ITEM_ID', $cartItem->getId());
            // #doc-out-desc the product title
            $loopResultRow->set('TITLE', $product->getTitle());
            // #doc-out-desc the product ref
            $loopResultRow->set('REF', $product->getRef());
            // #doc-out-desc the cart item quantity
            $loopResultRow->set('QUANTITY', $cartItem->getQuantity());
            // #doc-out-desc the product id
            $loopResultRow->set('PRODUCT_ID', $product->getId());
            // #doc-out-desc the product url
            $loopResultRow->set('PRODUCT_URL', $product->getUrl($this->getCurrentRequest()->getSession()->getLang()->getLocale()));
            if (!$checkAvailability || $product->getVirtual() === 1) {
                // #doc-out-desc the product sale elements available stock
                $loopResultRow->set('STOCK', $defaultAvailability);
            } else {
                // #doc-out-desc the product sale elements available stock
                $loopResultRow->set('STOCK', $productSaleElement->getQuantity());
            }
            $loopResultRow
                // #doc-out-desc the product sale elements price (unit price)
                ->set('PRICE', $cartItem->getPrice())
                // #doc-out-desc the product sale elements in promo price (unit price)
                ->set('PROMO_PRICE', $cartItem->getPromoPrice())
                // #doc-out-desc the product sale elements price including taxes (unit price)
                ->set('TAXED_PRICE', $cartItem->getTaxedPrice($taxCountry))
                // #doc-out-desc the product sale elements in promo price including taxes (unit price)
                ->set('PROMO_TAXED_PRICE', $cartItem->getTaxedPromoPrice($taxCountry))
                // #doc-out-desc if the product sale elements is in promo or not
                ->set('IS_PROMO', $cartItem->getPromo() === 1 ? 1 : 0)
            ;

            $loopResultRow
                // #doc-out-desc the product sale elements price (total price)
                ->set('TOTAL_PRICE', $cartItem->getTotalPrice())
                // #doc-out-desc the product sale elements in promo price (total price)
                ->set('TOTAL_PROMO_PRICE', $cartItem->getTotalPromoPrice())
                // #doc-out-desc the product sale elements price including taxes (total price)
                ->set('TOTAL_TAXED_PRICE', $cartItem->getTotalTaxedPrice($taxCountry))
                // #doc-out-desc the product sale elements in promo price including taxes (total price)
                ->set('TOTAL_PROMO_TAXED_PRICE', $cartItem->getTotalTaxedPromoPrice($taxCountry))
            ;

            $loopResultRow
                // #doc-out-desc the actual price of item in cart
                ->set('REAL_PRICE', $cartItem->getRealPrice())
                // #doc-out-desc the actual price of item in cart, after taxes are applied
                ->set('REAL_TAXED_PRICE', $cartItem->getRealTaxedPrice($taxCountry))
                // #doc-out-desc the actual total price of all items in cart
                ->set('REAL_TOTAL_PRICE', $cartItem->getTotalRealPrice($taxCountry))
                // #doc-out-desc the actual total price of all items in cart, after taxes are applied
                ->set('REAL_TOTAL_TAXED_PRICE', $cartItem->getTotalRealTaxedPrice($taxCountry))
            ;

            // #doc-out-desc the product sale elements id
            $loopResultRow->set('PRODUCT_SALE_ELEMENTS_ID', $productSaleElement->getId());
            // #doc-out-desc the sales item reference
            $loopResultRow->set('PRODUCT_SALE_ELEMENTS_REF', $productSaleElement->getRef());
            $this->addOutputFields($loopResultRow, $cartItem);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * Return the event dispatcher,.
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }
}
