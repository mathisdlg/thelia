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
use Thelia\Exception\TaxEngineException;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * Product Sale Elements loop.
 *
 * @todo : manage attribute_availability ?
 *
 * Class ProductSaleElements
 * 
 * #doc-usage {loop type="product_sale_elements" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Product sale elements loop lists product sale elements from your shop. You may need to use the attribute combination loop inside your product sale elements loop.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int         getCurrency()
 * @method int         getProduct()
 * @method bool        getPromo()
 * @method bool        getNew()
 * @method bool        getDefault()
 * @method string      getRef()
 * @method int[]       getAttributeAvailability()
 * @method string[]    getOrder()
 * @method bool|string getVisible()
 */
class ProductSaleElements extends BaseLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name attribute_availability
	 * #doc-arg-desc A single or a list of attribute availability (may not yet be managed on the back-end ?)
	 * #doc-arg-example attribute_availability="2,7"
	 * 
	 * #doc-arg-name currency
	 * #doc-arg-desc A currency id
	 * #doc-arg-example currency="1"
	 * 
	 * #doc-arg-name default
	 * #doc-arg-desc A boolean value. If true, returns only the default product sale elements. If false, the default product sale element is not returned
	 * #doc-arg-example default="yes"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A comma separated list of product sale elements id. Mandatory if the 'product' parameter is not present
	 * #doc-arg-example id="1,3,8"
	 * 
	 * #doc-arg-name new
	 * #doc-arg-desc A boolean value. If true, returns only product sale elements for which promo is on. The reverse with 'false'
	 * #doc-arg-example new="yes"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default random
	 * #doc-arg-example order="promo,min_price"
	 * 
	 * #doc-arg-name product
	 * #doc-arg-desc A single product id. Mandatory if the 'id' parameter is not present
	 * #doc-arg-example product="2"
	 * 
	 * #doc-arg-name promo
	 * #doc-arg-desc A boolean value. If true, returns only product sale elements for which new is on. The reverse with 'false'
	 * #doc-arg-example promo="yes"
	 * 
	 * #doc-arg-name ref
	 * #doc-arg-desc A product reference
	 * #doc-arg-example ref="a_ref"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc A boolean value, or * (the default) for ignoring this filter
	 * #doc-arg-default *
	 * #doc-arg-example visible="no"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('currency'),
            Argument::createIntTypeArgument('product'),
            Argument::createBooleanTypeArgument('promo'),
            Argument::createBooleanTypeArgument('new'),
            Argument::createBooleanTypeArgument('default'),
            Argument::createBooleanOrBothTypeArgument('visible', Type\BooleanOrBothType::ANY),
            Argument::createAnyTypeArgument('ref'),
            new Argument(
                'attribute_availability',
                new TypeCollection(
                    new Type\IntToCombinedIntsListType()
                )
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id', 'id_reverse',
                            'ref', 'ref_reverse',
                            'quantity', 'quantity_reverse',
                            'min_price', 'max_price',
                            'promo', 'new',
                            'weight', 'weight_reverse',
                            'created', 'created_reverse',
                            'updated', 'updated_reverse',
                            'random',
                        ]
                    )
                ),
                'random'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = ProductSaleElementsQuery::create();

        $id = $this->getId();
        $product = $this->getProduct();
        $ref = $this->getRef();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $product) {
            $search->filterByProductId($product, Criteria::EQUAL);
        }

        if (null !== $ref) {
            $search->filterByRef($ref, Criteria::EQUAL);
        }

        $promo = $this->getPromo();

        if (null !== $promo) {
            $search->filterByPromo($promo);
        }

        $new = $this->getNew();

        if (null !== $new) {
            $search->filterByNewness($new);
        }

        $visible = $this->getVisible();

        if (Type\BooleanOrBothType::ANY !== $visible) {
            $search->useProductQuery()
                ->filterByVisible($visible)
            ->endUse();
        }

        $default = $this->getDefault();

        if (null !== $default) {
            $search->filterByIsDefault($default);
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
                case 'ref':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'ref_reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;
                case 'quantity':
                    $search->orderByQuantity(Criteria::ASC);
                    break;
                case 'quantity_reverse':
                    $search->orderByQuantity(Criteria::DESC);
                    break;
                case 'min_price':
                    $search->addAscendingOrderByColumn('price_FINAL_PRICE');
                    break;
                case 'max_price':
                    $search->addDescendingOrderByColumn('price_FINAL_PRICE');
                    break;
                case 'promo':
                    $search->orderByPromo(Criteria::DESC);
                    break;
                case 'new':
                    $search->orderByNewness(Criteria::DESC);
                    break;
                case 'weight':
                    $search->orderByWeight(Criteria::ASC);
                    break;
                case 'weight_reverse':
                    $search->orderByWeight(Criteria::DESC);
                    break;
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created_reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated_reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        $currencyId = $this->getCurrency();
        if (null !== $currencyId) {
            $currency = CurrencyQuery::create()->findPk($currencyId);
            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `'.$currency.'` in product_sale_elements loop');
            }
        } else {
            $currency = $this->getCurrentRequest()->getSession()->getCurrency();
        }

        $defaultCurrency = CurrencyModel::getDefaultCurrency();
        $defaultCurrencySuffix = '_default_currency';

        $search->joinProductPrice('price', Criteria::LEFT_JOIN)
            ->addJoinCondition('price', '`price`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

        $search->joinProductPrice('price'.$defaultCurrencySuffix, Criteria::LEFT_JOIN)
            ->addJoinCondition('price_default_currency', '`price'.$defaultCurrencySuffix.'`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

        /**
         * rate value is checked as a float in overloaded getRate method.
         */
        $priceSelectorAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PRICE * '.$currency->getRate().' ELSE `price`.PRICE END';
        $promoPriceSelectorAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PROMO_PRICE  * '.$currency->getRate().' ELSE `price`.PROMO_PRICE END';
        $search->withColumn($priceSelectorAsSQL, 'price_PRICE')
            ->withColumn($promoPriceSelectorAsSQL, 'price_PROMO_PRICE')
            ->withColumn('CASE WHEN '.ProductSaleElementsTableMap::COL_PROMO.' = 1 THEN '.$promoPriceSelectorAsSQL.' ELSE '.$priceSelectorAsSQL.' END', 'price_FINAL_PRICE');

        $search->groupById();

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $EAN_CODE
	 * #doc-out-desc the product sale element EAN Code
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the product sale element id
	 * 
	 * #doc-out-name $IS_DEFAULT
	 * #doc-out-desc returns if the product sale element is the default product sale element for the product
	 * 
	 * #doc-out-name $IS_NEW
	 * #doc-out-desc returns if the product sale element is new
	 * 
	 * #doc-out-name $IS_PROMO
	 * #doc-out-desc returns if the product sale element is in promo
	 * 
	 * #doc-out-name $PRICE
	 * #doc-out-desc the product sale element price
	 * 
	 * #doc-out-name $PRICE_TAX
	 * #doc-out-desc the product sale element price tax
	 * 
	 * #doc-out-name $PRODUCT_ID
	 * #doc-out-desc the related product id
	 * 
	 * #doc-out-name $PROMO_PRICE
	 * #doc-out-desc the product sale element promo price
	 * 
	 * #doc-out-name $PROMO_PRICE_TAX
	 * #doc-out-desc the product sale element promo price tax
	 * 
	 * #doc-out-name $QUANTITY
	 * #doc-out-desc the product sale element stock quantity
	 * 
	 * #doc-out-name $REF
	 * #doc-out-desc the product sale element reference
	 * 
	 * #doc-out-name $TAXED_PRICE
	 * #doc-out-desc the product sale element taxed price
	 * 
	 * #doc-out-name $TAXED_PROMO_PRICE
	 * #doc-out-desc the product sale element taxed promo price
	 * 
	 * #doc-out-name $WEIGHT
	 * #doc-out-desc the product sale element weight
	 */
    public function parseResults(LoopResult $loopResult)
    {
        $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();
        /** @var \Thelia\Core\Security\SecurityContext $securityContext */
        $securityContext = $this->container->get('thelia.securityContext');
        $discount = 0;

        if ($securityContext->hasCustomerUser() && $securityContext->getCustomerUser()->getDiscount() > 0) {
            $discount = $securityContext->getCustomerUser()->getDiscount();
        }

        /** @var \Thelia\Model\ProductSaleElements $PSEValue */
        foreach ($loopResult->getResultDataCollection() as $PSEValue) {
            $loopResultRow = new LoopResultRow($PSEValue);

            $price = $PSEValue->getPrice('price_PRICE', $discount);
            try {
                $taxedPrice = $PSEValue->getTaxedPrice(
                    $taxCountry,
                    'price_PRICE',
                    $discount
                );
            } catch (TaxEngineException $e) {
                $taxedPrice = null;
            }

            $promoPrice = $PSEValue->getPromoPrice('price_PROMO_PRICE', $discount);
            try {
                $taxedPromoPrice = $PSEValue->getTaxedPromoPrice(
                    $taxCountry,
                    'price_PROMO_PRICE',
                    $discount
                );
            } catch (TaxEngineException $e) {
                $taxedPromoPrice = null;
            }

            $loopResultRow
                ->set('ID', $PSEValue->getId())
                ->set('QUANTITY', $PSEValue->getQuantity())
                ->set('IS_PROMO', $PSEValue->getPromo() === 1 ? 1 : 0)
                ->set('IS_NEW', $PSEValue->getNewness() === 1 ? 1 : 0)
                ->set('IS_DEFAULT', $PSEValue->getIsDefault() ? 1 : 0)
                ->set('WEIGHT', $PSEValue->getWeight())
                ->set('REF', $PSEValue->getRef())
                ->set('EAN_CODE', $PSEValue->getEanCode())
                ->set('PRODUCT_ID', $PSEValue->getProductId())
                ->set('PRICE', $price)
                ->set('PRICE_TAX', $taxedPrice - $price)
                ->set('TAXED_PRICE', $taxedPrice)
                ->set('PROMO_PRICE', $promoPrice)
                ->set('PROMO_PRICE_TAX', $taxedPromoPrice - $promoPrice)
                ->set('TAXED_PROMO_PRICE', $taxedPromoPrice);

            $this->addOutputFields($loopResultRow, $PSEValue);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return [
            'ref',
            'ean_code',
        ];
    }

    /**
     * @param ProductSaleElementsQuery $search
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
                case 'ean_code':
                    $search->filterByEanCode($searchTerm, $searchCriteria);
                    break;
            }
        }
    }
}
