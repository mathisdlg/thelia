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
use Thelia\Model\Category as CategoryModel;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Category loop, all params available :.
 *
 * - id : can be an id (eq : 3) or a "string list" (eg: 3, 4, 5)
 * - parent : categories having this parent id
 * - current : current id is used if you are on a category page
 * - not_empty : if value is 1, category and subcategories must have at least 1 product
 * - visible : default 1, if you want category not visible put 0
 * - order : all value available :  'alpha', 'alpha_reverse', 'manual' (default), 'manual_reverse', 'random'
 * - exclude : all category id you want to exclude (as for id, an integer or a "string list" can be used)
 *
 * Class Category
 * 
 * #doc-desc Category loop lists categories from your shop.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int[]       getParent()
 * @method int[]       getExcludeParent()
 * @method int[]       getProduct()
 * @method int[]       getExcludeProduct()
 * @method int[]       getContent()
 * @method bool        getCurrent()
 * @method bool        getNotEmpty()
 * @method bool        getWithPrevNextInfo()
 * @method bool        getNeedCountChild()
 * @method bool        getNeedProductCount()
 * @method bool        getProductCountVisibleOnly()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 * @method int[]       getTemplateId()
 */
class Category extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;
    protected $versionable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name content
	 * #doc-arg-desc One or more content ID. When this parameter is set, the loop returns the categories related to the specified content IDs.
	 * #doc-arg-example content="3"
	 * 
	 * #doc-arg-name current
	 * #doc-arg-desc A boolean value which allows either to exclude current category from results either to match only this category
	 * #doc-arg-example current="yes"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of category ids.
	 * #doc-arg-example exclude="2", exclude="1,4,7"
	 * 
	 * #doc-arg-name exclude_parent
	 * #doc-arg-desc A single or list of categories id to exclude.
	 * #doc-arg-example exclude_parent="12,22"
	 * 
	 * #doc-arg-name exclude_product
	 * #doc-arg-desc A single or list product id to exclude.
	 * #doc-arg-example exclude_product="3"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of category ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name need_count_child
	 * #doc-arg-desc A boolean. If set to true, count how many subcategories contains the current category
	 * #doc-arg-example need_count_child="yes"
	 * 
	 * #doc-arg-name need_product_count
	 * #doc-arg-desc A boolean. If set to true, count how many products contains the current category
	 * #doc-arg-example need_product_count="yes"
	 * 
	 * #doc-arg-name not_empty
	 * #doc-arg-desc (**not implemented yet**) A boolean value. If true, only the categories which contains at least a visible product (either directly or through a subcategory) are returned
	 * #doc-arg-example not_empty="yes"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values <br/> Expected values
	 * #doc-arg-example order="random"
	 * 
	 * #doc-arg-name parent
	 * #doc-arg-desc A single or a list of category ids.
	 * #doc-arg-example parent="3", parent="2,5,8"
	 * 
	 * #doc-arg-name product
	 * #doc-arg-desc A single or list of product IDs.
	 * #doc-arg-example product="3"
	 * 
	 * #doc-arg-name ProductCountVisibleOnly
	 * #doc-arg-desc A boolean that specifies whether product counting should be performed only for visible products
	 * #doc-arg-example ProductCountVisibleOnly=true
	 * 
	 * #doc-arg-name TemplateId
	 * #doc-arg-desc IDs of template models used to filter categories
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc A boolean value.
	 * #doc-arg-example visible="no"
	 * 
	 * #doc-arg-name with_prev_next_info
	 * #doc-arg-desc A boolean. If set to true, $PREVIOUS and $NEXT output arguments are available.
	 * #doc-arg-example with_prev_next_info="yes"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('parent'),
            Argument::createIntListTypeArgument('exclude_parent'),
            Argument::createIntListTypeArgument('product'),
            Argument::createIntListTypeArgument('exclude_product'),
            Argument::createIntListTypeArgument('content'),
            Argument::createBooleanTypeArgument('current'),
            Argument::createBooleanTypeArgument('not_empty', 0),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createBooleanTypeArgument('need_count_child', false),
            Argument::createBooleanTypeArgument('need_product_count', false),
            Argument::createBooleanTypeArgument('product_count_visible_only', false),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntListTypeArgument('template_id'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType([
                        'id', 'id_reverse',
                        'alpha', 'alpha_reverse',
                        'manual', 'manual_reverse',
                        'visible', 'visible_reverse',
                        'created', 'created_reverse',
                        'updated', 'updated_reverse',
                        'random',
                    ])
                ),
                'manual'
            ),
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
     * @param CategoryQuery $search
     * @param string        $searchTerm
     * @param array         $searchIn
     * @param string        $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    public function buildModelCriteria()
    {
        $search = CategoryQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $parent = $this->getParent();

        if (null !== $parent) {
            $search->filterByParent($parent, Criteria::IN);
            $positionOrderAllowed = true;
        } else {
            $positionOrderAllowed = false;
        }

        $excludeParent = $this->getExcludeParent();

        if (null !== $excludeParent) {
            $search->filterByParent($excludeParent, Criteria::NOT_IN);
        }

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->getCurrentRequest()->get('category_id'));
        } elseif ($current === false) {
            $search->filterById($this->getCurrentRequest()->get('category_id'), Criteria::NOT_IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $products = $this->getProduct();

        if ($products != null) {
            $obj = ProductQuery::create()->findPks($products);

            if ($obj != null) {
                $search->filterByProduct($obj, Criteria::IN);
            }
        }

        $excludeProducts = $this->getExcludeProduct();

        if ($excludeProducts != null) {
            $obj = ProductQuery::create()->findPks($excludeProducts);

            if ($obj != null) {
                $search->filterByProduct($obj, Criteria::NOT_IN);
            }
        }

        $contentId = $this->getContent();

        if ($contentId != null) {
            $search->useCategoryAssociatedContentQuery()
                ->filterByContentId($contentId, Criteria::IN)
                ->endUse()
            ;
        }
        $templateIdList = $this->getTemplateId();

        if (null !== $templateIdList) {
            $search->filterByDefaultTemplateId($templateIdList, Criteria::IN);
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
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible_reverse':
                    $search->orderByVisible(Criteria::DESC);
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
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc The category chapo
	 * 
	 * #doc-out-name $CHILD_COUNT
	 * #doc-out-desc Number of subcategories contained by the current category.<br/> **Only available if "need_count_child" is set to true**
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc The category description
	 * 
	 * #doc-out-name $HAS_NEXT
	 * #doc-out-desc True if a category exists after this one in the current parent category, following categories positions.<br/> **Only available if "with_prev_next_info" is set to true**
	 * 
	 * #doc-out-name $HAS_PREVIOUS
	 * #doc-out-desc True if a category exists before this one in the current parent category, following categories positions.<br/> **Only available if "with_prev_next_info" is set to true**
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc The category id
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc Check if the category is translated or not
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc The locale used for this loop
	 * 
	 * #doc-out-name $META_DESCRIPTION
	 * #doc-out-desc The category meta description
	 * 
	 * #doc-out-name $META_KEYWORD
	 * #doc-out-desc The category meta keyword
	 * 
	 * #doc-out-name $META_TITLE
	 * #doc-out-desc The category meta title
	 * 
	 * #doc-out-name $NEXT
	 * #doc-out-desc The ID of category after this one in the current parent category, following categories positions, or null if none exists.<br/> **Only available if "with_prev_next_info" is set to true**
	 * 
	 * #doc-out-name $PARENT
	 * #doc-out-desc The parent category
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc The category position
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc The category postscriptum
	 * 
	 * #doc-out-name $PRODUCT_COUNT
	 * #doc-out-desc Number of visible products contained by the current category. <br/> **Only available if "need_product_child" is set to true**
	 * 
	 * #doc-out-name $PREVIOUS
	 * #doc-out-desc The ID of category before this one in the current parent category, following categories positions, or null if none exists.<br/> **Only available if "with_prev_next_info" is set to true**
	 * 
	 * #doc-out-name $ROOT
	 * #doc-out-desc ID of the root category to which a category belongs
	 * 
	 * #doc-out-name $TEMPLATE
	 * #doc-out-desc The template id associated to this category
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc The category title
	 * 
	 * #doc-out-name $URL
	 * #doc-out-desc The category URL
	 * 
	 * #doc-out-name $VISIBLE
	 * #doc-out-desc Return if the category is visible or not
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var CategoryModel $category */
        foreach ($loopResult->getResultDataCollection() as $category) {
            /*
             * no cause pagination lost :
             * if ($this->getNotEmpty() && $category->countAllProducts() == 0) continue;
             */

            $loopResultRow = new LoopResultRow($category);

            $loopResultRow
                ->set('ID', $category->getId())
                ->set('IS_TRANSLATED', $category->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $category->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $category->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $category->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $category->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('PARENT', $category->getParent())
                ->set('ROOT', $category->getRoot($category->getId()))
                ->set('URL', $this->getReturnUrl() ? $category->getUrl($this->locale) : null)
                ->set('META_TITLE', $category->getVirtualColumn('i18n_META_TITLE'))
                ->set('META_DESCRIPTION', $category->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set('META_KEYWORDS', $category->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set('VISIBLE', $category->getVisible() ? '1' : '0')
                ->set('POSITION', $category->getPosition())
                ->set('TEMPLATE', $category->getDefaultTemplateId());

            if ($this->getNeedCountChild()) {
                $loopResultRow->set('CHILD_COUNT', $category->countChild());
            }

            if ($this->getNeedProductCount()) {
                if ($this->getProductCountVisibleOnly()) {
                    $loopResultRow->set('PRODUCT_COUNT', $category->countAllProductsVisibleOnly());
                } else {
                    $loopResultRow->set('PRODUCT_COUNT', $category->countAllProducts());
                }
            }

            $isBackendContext = $this->getBackendContext();

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next category
                $previousQuery = CategoryQuery::create()
                    ->filterByParent($category->getParent())
                    ->filterByPosition($category->getPosition(), Criteria::LESS_THAN);

                if (!$isBackendContext) {
                    $previousQuery->filterByVisible(true);
                }

                $previous = $previousQuery
                    ->orderByPosition(Criteria::DESC)
                    ->findOne();

                $nextQuery = CategoryQuery::create()
                    ->filterByParent($category->getParent())
                    ->filterByPosition($category->getPosition(), Criteria::GREATER_THAN);

                if (!$isBackendContext) {
                    $nextQuery->filterByVisible(true);
                }

                $next = $nextQuery
                    ->orderByPosition(Criteria::ASC)
                    ->findOne();

                $loopResultRow
                    ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
                    ->set('HAS_NEXT', $next != null ? 1 : 0)
                    ->set('PREVIOUS', $previous != null ? $previous->getId() : -1)
                    ->set('NEXT', $next != null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $category);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
