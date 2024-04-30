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
use Thelia\Model\OrderCouponCountry;
use Thelia\Model\OrderCouponModule;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderQuery;

/**
 * OrderCoupon loop.
 *
 * Class OrderCoupon
 * 
 * #doc-usage {loop type="order_coupon" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Retrieve order coupons information for a given order
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method string[] getOrder()
 */
class OrderCoupon extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * Define all args used in your loop.
     *
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name order *
	 * #doc-arg-desc A single order id.
	 * #doc-arg-default null
	 * #doc-arg-example order="2"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderCouponQuery::create();

        $order = $this->getOrder();

        $search
            ->filterByOrderId($order, Criteria::EQUAL)
            ->orderById(Criteria::ASC)
        ;

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $CODE
	 * #doc-out-desc the coupon code
	 * 
	 * #doc-out-name $DAY_LEFT_BEFORE_EXPIRATION
	 * #doc-out-desc days left before coupon expiration
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the coupon description
	 * 
	 * #doc-out-name $DISCOUNT_AMOUNT
	 * #doc-out-desc the coupon discount amount
	 * 
	 * #doc-out-name $EXPIRATION_DATE
	 * #doc-out-desc the coupon expiration date
	 * 
	 * #doc-out-name $FREE_SHIPPING_FOR_COUNTRIES_LIST
	 * #doc-out-desc comma separated list of country IDs for which the free shipping applies
	 * 
	 * #doc-out-name $FREE_SHIPPING_FOR_MODULES_LIST
	 * #doc-out-desc comma separated list of shipping module IDs for which the free shipping applies
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the coupon id
	 * 
	 * #doc-out-name $IS_AVAILABLE_ON_SPECIAL_OFFERS
	 * #doc-out-desc true if the coupon applies to discounted products
	 * 
	 * #doc-out-name $IS_CUMULATIVE
	 * #doc-out-desc true if the coupon is cumulative
	 * 
	 * #doc-out-name $IS_REMOVING_POSTAGE
	 * #doc-out-desc true if the coupon provides free shipping
	 * 
	 * #doc-out-name $IS_USAGE_CANCELED
	 * #doc-out-desc true if the usage of this coupon was canceled (probably when the related order was canceled), false otherwise
	 * 
	 * #doc-out-name $PER_CUSTOMER_USAGE_COUNT
	 * #doc-out-desc Get the [per_customer_usage_count] column value.
	 * 
	 * #doc-out-name $SHORT_DESCRIPTION
	 * #doc-out-desc the coupon short description
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the coupon title
	 */
    public function parseResults(LoopResult $loopResult)
    {
        $this->container->get('thelia.condition.factory');

        if (null !== $order = OrderQuery::create()->findPk($this->getOrder())) {
            $oneDayInSeconds = 86400;

            /** @var \Thelia\Model\OrderCoupon $orderCoupon */
            foreach ($loopResult->getResultDataCollection() as $orderCoupon) {
                $loopResultRow = new LoopResultRow($orderCoupon);

                $now = time();
                $datediff = $orderCoupon->getExpirationDate()->getTimestamp() - $now;
                $daysLeftBeforeExpiration = floor($datediff / $oneDayInSeconds);

                $freeShippingForCountriesIds = [];
                /** @var OrderCouponCountry $couponCountry */
                foreach ($orderCoupon->getFreeShippingForCountries() as $couponCountry) {
                    $freeShippingForCountriesIds[] = $couponCountry->getCountryId();
                }

                $freeShippingForModulesIds = [];
                /** @var OrderCouponModule $couponModule */
                foreach ($orderCoupon->getFreeShippingForModules() as $couponModule) {
                    $freeShippingForModulesIds[] = $couponModule->getModuleId();
                }

                $loopResultRow->set('ID', $orderCoupon->getId())
                    ->set('CODE', $orderCoupon->getCode())
                    ->set('DISCOUNT_AMOUNT', $orderCoupon->getAmount())
                    ->set('TITLE', $orderCoupon->getTitle())
                    ->set('SHORT_DESCRIPTION', $orderCoupon->getShortDescription())
                    ->set('DESCRIPTION', $orderCoupon->getDescription())
                    ->set('EXPIRATION_DATE', $orderCoupon->getExpirationDate($order->getLangId()))
                    ->set('IS_CUMULATIVE', $orderCoupon->getIsCumulative())
                    ->set('IS_REMOVING_POSTAGE', $orderCoupon->getIsRemovingPostage())
                    ->set('IS_AVAILABLE_ON_SPECIAL_OFFERS', $orderCoupon->getIsAvailableOnSpecialOffers())
                    ->set('DAY_LEFT_BEFORE_EXPIRATION', $daysLeftBeforeExpiration)
                    ->set('FREE_SHIPPING_FOR_COUNTRIES_LIST', implode(',', $freeShippingForCountriesIds))
                    ->set('FREE_SHIPPING_FOR_MODULES_LIST', implode(',', $freeShippingForModulesIds))
                    ->set('PER_CUSTOMER_USAGE_COUNT', $orderCoupon->getPerCustomerUsageCount())
                    ->set('IS_USAGE_CANCELED', $orderCoupon->getUsageCanceled())
                ;
                $this->addOutputFields($loopResultRow, $orderCoupon);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
