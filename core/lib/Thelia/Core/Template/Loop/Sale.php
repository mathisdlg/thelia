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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Element\StandardI18nFieldsSearchTrait;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\SaleQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Sale loop.
 *
 * Class Sale
 * 
 * #doc-desc Sale loop provides an access to sale operations defined on your shop.
 *
 * @author Franck Allimant <thelia@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method int[]       getExclude()
 * @method bool|string getActive()
 * @method int[]       getProduct()
 * @method int         getCurrency()
 * @method string[]    getOrder()
 */
class Sale extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of sale ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single or a list of sale ids to excluded from results.
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc A boolean value, to get only active (1) or inactive sales (0) or both (
            Argument::createBooleanOrBothTypeArgument('active', 1),
            // #doc-arg-desc A single or a list of product IDs. If specified, the loop will return the sales in which these products are selected
            Argument::createIntListTypeArgument('product'),
            // #doc-arg-desc A currency id, to get the price offset defined for this currency
            Argument::createIntTypeArgument('currency', $this->getCurrentRequest()->getSession()->getCurrency()->getId()),
            // #doc-arg-desc A list of values see sorting possible values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id',
                            'id-reverse',
                            'alpha',
                            'alpha-reverse',
                            'label',
                            'label-reverse',
                            'active',
                            'active-reverse',
                            'start-date',
                            'start-date-reverse',
                            'end-date',
                            'end-date-reverse',
                            'created',
                            'created-reverse',
                            'updated',
                            'updated-reverse',
                        ]
                    )
                ),
                'start-date'
            )
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return array_merge(
            ['sale_label'],
            $this->getStandardI18nSearchFields()
        );
    }

    /**
     * @param SaleQuery $search
     * @param string    $searchTerm
     * @param array     $searchIn
     * @param string    $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }
            switch ($searchInElement) {
                case 'sale_label':
                    $this->addSearchInI18nColumn($search, 'SALE_LABEL', $searchCriteria, $searchTerm);
                    break;
            }
        }

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    public function buildModelCriteria()
    {
        $search = SaleQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'SALE_LABEL', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $active = $this->getActive();

        if ($active !== BooleanOrBothType::ANY) {
            $search->filterByActive($active ? 1 : 0);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $productIdList = $this->getProduct();

        if (null !== $productIdList) {
            $search
                ->useSaleProductQuery()
                    ->filterByProductId($productIdList, Criteria::IN)
                    ->groupByProductId()
                ->endUse()
            ;
        }

        $search
            ->leftJoinSaleOffsetCurrency('SaleOffsetCurrency')
            ->addJoinCondition('SaleOffsetCurrency', '`SaleOffsetCurrency`.`currency_id` = ?', $this->getCurrency(), null, \PDO::PARAM_INT)
        ;

        $search->withColumn('`SaleOffsetCurrency`.PRICE_OFFSET_VALUE', 'price_offset_value');

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id-reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'label':
                    $search->addAscendingOrderByColumn('i18n_SALE_LABEL');
                    break;
                case 'label-reverse':
                    $search->addDescendingOrderByColumn('i18n_SALE_LABEL');
                    break;
                case 'active':
                    $search->orderByActive(Criteria::ASC);
                    break;
                case 'active-reverse':
                    $search->orderByActive(Criteria::DESC);
                    break;
                case 'start-date':
                    $search->orderByStartDate(Criteria::ASC);
                    break;
                case 'start-date-reverse':
                    $search->orderByStartDate(Criteria::DESC);
                    break;
                case 'end-date':
                    $search->orderByEndDate(Criteria::ASC);
                    break;
                case 'end-date-reverse':
                    $search->orderByEndDate(Criteria::DESC);
                    break;
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created-reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated-reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Sale $sale */
        foreach ($loopResult->getResultDataCollection() as $sale) {
            $loopResultRow = new LoopResultRow($sale);

            switch ($sale->getPriceOffsetType()) {
                case \Thelia\Model\Sale::OFFSET_TYPE_AMOUNT:
                    $priceOffsetType = 'A';
                    $priceOffsetSymbol = $this->getCurrentRequest()->getSession()->getCurrency()->getSymbol();
                    break;
                case \Thelia\Model\Sale::OFFSET_TYPE_PERCENTAGE:
                    $priceOffsetType = 'P';
                    $priceOffsetSymbol = '%';
                    break;
                default:
                    $priceOffsetType = $priceOffsetSymbol = '?';
            }

            // #doc-out-desc the content id
            $loopResultRow->set('ID', $sale->getId())
                // #doc-out-desc check if the content is translated
                ->set('IS_TRANSLATED', $sale->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc the locale (e.g. fr_FR) of the returned data
                ->set('LOCALE', $this->locale)
                // #doc-out-desc the sale title
                ->set('TITLE', $sale->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc the sale advertising label
                ->set('SALE_LABEL', $sale->getVirtualColumn('i18n_SALE_LABEL'))
                // #doc-out-desc the sale description
                ->set('DESCRIPTION', $sale->getVirtualColumn('i18n_DESCRIPTION'))
                // #doc-out-desc the sale chapo
                ->set('CHAPO', $sale->getVirtualColumn('i18n_CHAPO'))
                ->set('POSTSCRIPTUM', $sale->getVirtualColumn('i18n_POSTSCRIPTUM'))
                // #doc-out-desc true if the sale is active, false otherwise
                ->set('ACTIVE', $sale->getActive())
                // #doc-out-desc true if the products initial price should be displayed, false otherwise
                ->set('DISPLAY_INITIAL_PRICE', $sale->getDisplayInitialPrice())
                // #doc-out-desc the sale start date
                ->set('START_DATE', $sale->getStartDate())
                // #doc-out-desc true if the sale has a start date, false otherwise
                ->set('HAS_START_DATE', $sale->hasStartDate() ? 1 : 0)
                // #doc-out-desc the sale end date
                ->set('END_DATE', $sale->getEndDate())
                // #doc-out-desc true if the sale has a end date, false otherwise
                ->set('HAS_END_DATE', $sale->hasEndDate() ? 1 : 0)
                // #doc-out-desc the price offset type, P for a percentage, A for an amount
                ->set('PRICE_OFFSET_TYPE', $priceOffsetType)
                // #doc-out-desc the offset unit symbol, % for a percentage, the currency symbol for an amount
                ->set('PRICE_OFFSET_SYMBOL', $priceOffsetSymbol)
                // #doc-out-desc the price offset value, as a percentage (0-100) or a constant amount.
                ->set('PRICE_OFFSET_VALUE', $sale->getVirtualColumn('price_offset_value'))
            ;

            $this->addOutputFields($loopResultRow, $sale);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
