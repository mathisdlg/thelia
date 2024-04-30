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
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CategoryQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Category tree loop, to get a category tree from a given category to a given depth.
 *
 * - category is the category id
 * - depth is the maximum depth to go, default unlimited
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 * 
 * #doc-usage {loop type="category_tree" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Category tree loop, to get a category tree from a given category to a given depth.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int         getCategory()
 * @method int         getDepth()
 * @method bool        getNeedCountChild()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 */
class CategoryTree extends BaseI18nLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name category *
	 * #doc-arg-desc A single category id.
	 * #doc-arg-example category="2"
	 * 
	 * #doc-arg-name depth
	 * #doc-arg-desc The max depth
	 * #doc-arg-example depth="5"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of category ids to exclude for result.
	 * #doc-arg-example exclude="5,72"
	 * 
	 * #doc-arg-name need_count_child
	 * #doc-arg-desc A boolean which indicates whether the number of children in each category should be taken into account
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values <br/> Expected values
	 * #doc-arg-default position
	 * #doc-arg-example order="random"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc Whatever we consider hidden category or not.
	 * #doc-arg-default true
	 * #doc-arg-example 
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category', null, true),
            Argument::createIntTypeArgument('depth', \PHP_INT_MAX),
            Argument::createBooleanTypeArgument('need_count_child', false),
            Argument::createBooleanOrBothTypeArgument('visible', true, false),
            Argument::createIntListTypeArgument('exclude', []),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['position', 'position_reverse', 'id', 'id_reverse', 'alpha', 'alpha_reverse'])
                ),
                'position'
            )
        );
    }

    // changement de rubrique
    protected function buildCategoryTree($parent, $visible, $level, $previousLevel, $maxLevel, $exclude, &$resultsList): void
    {
        if ($level > $maxLevel) {
            return;
        }

        $search = CategoryQuery::create();
        $this->configureI18nProcessing($search, ['TITLE']);

        $search->filterByParent($parent);

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible);
        }

        if ($exclude != null) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'position':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'position_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
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
            }
        }

        $results = $search->find();

        $needCountChild = $this->getNeedCountChild();

        foreach ($results as $result) {
            $row = [
                'ID' => $result->getId(),
                'TITLE' => $result->getVirtualColumn('i18n_TITLE'),
                'PARENT' => $result->getParent(),
                'URL' => $this->getReturnUrl() ? $result->getUrl($this->locale) : null,
                'VISIBLE' => $result->getVisible() ? '1' : '0',
                'LEVEL' => $level,
                'PREV_LEVEL' => $previousLevel,
            ];

            if ($needCountChild) {
                $row['CHILD_COUNT'] = $result->countChild();
            }

            $resultsList[] = $row;

            $this->buildCategoryTree($result->getId(), $visible, 1 + $level, $level, $maxLevel, $exclude, $resultsList);
        }
    }

	 /**
	 * 
	 * #doc-out-name $CHILD_COUNT
	 * #doc-out-desc The number of direct children of a category in the tree
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the category id
	 * 
	 * #doc-out-name $LEVEL
	 * #doc-out-desc The depth of the category in the tree
	 * 
	 * #doc-out-name $PARENT
	 * #doc-out-desc the parent category
	 * 
	 * #doc-out-name $PREV_LEVEL
	 * #doc-out-desc The depth of the direct parent category in the tree.
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the category title
	 * 
	 * #doc-out-name $URL
	 * #doc-out-desc the category URL
	 * 
	 * #doc-out-name $VISIBLE
	 * #doc-out-desc whatever the category is visible or not
	 */
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);
            foreach ($result as $output => $outputValue) {
                $loopResultRow->set($output, $outputValue);
            }

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    public function buildArray()
    {
        $id = $this->getCategory();
        $depth = $this->getDepth();
        $visible = $this->getVisible();
        $exclude = $this->getExclude();

        $resultsList = [];

        $this->buildCategoryTree($id, $visible, 0, 0, $depth, $exclude, $resultsList);

        return $resultsList;
    }
}
