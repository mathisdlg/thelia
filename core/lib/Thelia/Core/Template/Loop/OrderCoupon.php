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

                // #doc-out-desc the coupon id
                $loopResultRow->set('ID', $orderCoupon->getId())
                    // #doc-out-desc the coupon code
                    ->set('CODE', $orderCoupon->getCode())
                    // #doc-out-desc the coupon discount amount
                    ->set('DISCOUNT_AMOUNT', $orderCoupon->getAmount())
                    // #doc-out-desc the coupon title
                    ->set('TITLE', $orderCoupon->getTitle())
                    // #doc-out-desc the coupon short description
                    ->set('SHORT_DESCRIPTION', $orderCoupon->getShortDescription())
                    // #doc-out-desc the coupon description
                    ->set('DESCRIPTION', $orderCoupon->getDescription())
                    // #doc-out-desc the coupon expiration date
                    ->set('EXPIRATION_DATE', $orderCoupon->getExpirationDate($order->getLangId()))
                    // #doc-out-desc true if the coupon is cumulative
                    ->set('IS_CUMULATIVE', $orderCoupon->getIsCumulative())
                    // #doc-out-desc true if the coupon provides free shipping
                    ->set('IS_REMOVING_POSTAGE', $orderCoupon->getIsRemovingPostage())
                    // #doc-out-desc true if the coupon applies to discounted products
                    ->set('IS_AVAILABLE_ON_SPECIAL_OFFERS', $orderCoupon->getIsAvailableOnSpecialOffers())
                    // #doc-out-desc days left before coupon expiration
                    ->set('DAY_LEFT_BEFORE_EXPIRATION', $daysLeftBeforeExpiration)
                    // #doc-out-desc comma separated list of country IDs for which the free shipping applies
                    ->set('FREE_SHIPPING_FOR_COUNTRIES_LIST', implode(',', $freeShippingForCountriesIds))
                    // #doc-out-desc comma separated list of shipping module IDs for which the free shipping applies
                    ->set('FREE_SHIPPING_FOR_MODULES_LIST', implode(',', $freeShippingForModulesIds))
                    // #doc-out-desc Get the [per_customer_usage_count] column value.
                    ->set('PER_CUSTOMER_USAGE_COUNT', $orderCoupon->getPerCustomerUsageCount())
                    // #doc-out-desc true if the usage of this coupon was canceled (probably when the related order was canceled), false otherwise
                    ->set('IS_USAGE_CANCELED', $orderCoupon->getUsageCanceled())
                ;
                $this->addOutputFields($loopResultRow, $orderCoupon);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
