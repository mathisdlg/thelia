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
use Thelia\Model\BrandQuery;
use Thelia\Model\ProductQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Brand loop.
 *
 * Class Brand
 * 
 * #doc-desc Brand loop lists brands defined in your shop.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method int         getProduct()
 * @method bool|string getVisible()
 * @method string      getTitle()
 * @method bool        getCurrent()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 * @method bool        getWithPrevNextInfo()
 */
class Brand extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of brand ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single product id.
            Argument::createIntTypeArgument('product'),
            // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            // #doc-arg-desc A title string
            Argument::createAnyTypeArgument('title'),
            // #doc-arg-desc A boolean value which allows either to exclude current brand from results, or match only this brand
            Argument::createBooleanTypeArgument('current'),
            // #doc-arg-desc A boolean. If set to true, $PREVIOUS and $NEXT output arguments are available.
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            // A list of values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id',
                            'id-reverse',
                            'alpha',
                            'alpha-reverse',
                            'manual',
                            'manual-reverse',
                            'random',
                            'created',
                            'created-reverse',
                            'updated',
                            'updated-reverse',
                            'visible',
                            'visible-reverse',
                        ]
                    )
                ),
                'alpha'
            ),
            // #doc-arg-desc A list of brand IDs to exclude from selection when running the loop
            Argument::createIntListTypeArgument('exclude')
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return $this->getStandardI18nSearchFields();
    }

    /**
     * @param BrandQuery $search
     * @param string     $searchTerm
     * @param array      $searchIn
     * @param string     $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    public function buildModelCriteria()
    {
        $search = BrandQuery::create();

        /* manage translations */
        $this->configureI18nProcessing(
            $search,
            [
                'TITLE',
                'CHAPO',
                'DESCRIPTION',
                'POSTSCRIPTUM',
                'META_TITLE',
                'META_DESCRIPTION',
                'META_KEYWORDS',
            ]
        );

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $product = $this->getProduct();

        if (null !== $product && null !== $productObj = ProductQuery::create()->findPk($product)) {
            $search->filterByProduct($productObj);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $title = $this->getTitle();

        if (null !== $title) {
            $this->addSearchInI18nColumn($search, 'TITLE', Criteria::LIKE, '%'.$title.'%');
        }

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->getCurrentRequest()->get('brand_id'));
        } elseif ($current === false) {
            $search->filterById($this->getCurrentRequest()->get('brand_id'), Criteria::NOT_IN);
        }

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
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
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
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible-reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
            }
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        return $search;
    }


    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Brand $brand */
        foreach ($loopResult->getResultDataCollection() as $brand) {
            $loopResultRow = new LoopResultRow($brand);

            // #doc-out-desc the brand id
            $loopResultRow->set('ID', $brand->getId())
                // #doc-out-desc check if the brand is translated
                ->set('IS_TRANSLATED', $brand->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
                // #doc-out-desc the brand title
                ->set('TITLE', $brand->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc the brand chapo
                ->set('CHAPO', $brand->getVirtualColumn('i18n_CHAPO'))
                // #doc-out-desc the brand description
                ->set('DESCRIPTION', $brand->getVirtualColumn('i18n_DESCRIPTION'))
                // #doc-out-desc the brand postscriptum
                ->set('POSTSCRIPTUM', $brand->getVirtualColumn('i18n_POSTSCRIPTUM'))
                // #doc-out-desc the brand URL
                ->set('URL', $this->getReturnUrl() ? $brand->getUrl($this->locale) : null)
                // #doc-out-desc the brand meta title
                ->set('META_TITLE', $brand->getVirtualColumn('i18n_META_TITLE'))
                // #doc-out-desc the brand meta description
                ->set('META_DESCRIPTION', $brand->getVirtualColumn('i18n_META_DESCRIPTION'))
                // #doc-out-desc the brand meta keywords
                ->set('META_KEYWORDS', $brand->getVirtualColumn('i18n_META_KEYWORDS'))
                // #doc-out-desc the brand position
                ->set('POSITION', $brand->getPosition())
                // #doc-out-desc true if the product is visible or not, false otherwise
                ->set('VISIBLE', $brand->getVisible())
                // #doc-out-desc ID of the brand logo image, among the brand images
                ->set('LOGO_IMAGE_ID', $brand->getLogoImageId() ?: 0);

            $isBackendContext = $this->getBackendContext();

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = BrandQuery::create()
                    ->filterByPosition($brand->getPosition(), Criteria::LESS_THAN);

                if (!$isBackendContext) {
                    $previousQuery->filterByVisible(true);
                }

                $previous = $previousQuery
                    ->orderByPosition(Criteria::DESC)
                    ->findOne();

                $nextQuery = BrandQuery::create()
                    ->filterByPosition($brand->getPosition(), Criteria::GREATER_THAN);

                if (!$isBackendContext) {
                    $nextQuery->filterByVisible(true);
                }

                $next = $nextQuery
                    ->orderByPosition(Criteria::ASC)
                    ->findOne();

                $loopResultRow
                    // #doc-out-desc true if a brand exists before this one following brands positions
                    ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
                    // #doc-out-desc true if a brand exists after this one, following brands positions.
                    ->set('HAS_NEXT', $next != null ? 1 : 0)
                    // #doc-out-desc The ID of brand before this one, following brands positions, or null if none exists.
                    ->set('PREVIOUS', $previous != null ? $previous->getId() : -1)
                    // #doc-out-desc The ID of brand after this one, following brands positions, or null if none exists
                    ->set('NEXT', $next != null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $brand);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
