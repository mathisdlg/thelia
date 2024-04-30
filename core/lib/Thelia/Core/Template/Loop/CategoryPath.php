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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CategoryQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Category path loop, to get the path to a given category.
 *
 * - category is the category id
 * - depth is the maximum depth to go, default unlimited
 * - level is the exact level to return. Example: if level = 2 and the path is c1 -> c2 -> c3 -> c4, the loop will return c2
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 *
 * Class CategoryPath
 * 
 * #doc-usage {loop type="category_path" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Category path loop provides the path through the catalog to a given category. For example if we have an "alpha" category standing in an "alpha_father" category which itseflf belong to "root" category. Category path loop for category "alpha" will return "root" then "alpha_father" then "alpha".
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int         getCategory()
 * @method int         getDepth()
 * @method bool|string getVisible()
 */
class CategoryPath extends BaseI18nLoop implements ArraySearchLoopInterface
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
	 * #doc-arg-name visible
	 * #doc-arg-desc Whatever we consider hidden category or not.
	 * #doc-arg-default true
	 * #doc-arg-example visible="false"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category', null, true),
            Argument::createIntTypeArgument('depth', \PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false)
        );
    }

    public function buildArray()
    {
        $originalId = $currentId = $this->getCategory();
        $visible = $this->getVisible();
        $depth = $this->getDepth();

        $results = [];

        $ids = [];

        do {
            $search = CategoryQuery::create();

            $this->configureI18nProcessing($search, ['TITLE']);

            $search->filterById($currentId);

            if ($visible !== BooleanOrBothType::ANY) {
                $search->filterByVisible($visible);
            }

            $category = $search->findOne();

            if ($category != null) {
                $results[] = [
                    'ID' => $category->getId(),
                    'TITLE' => $category->getVirtualColumn('i18n_TITLE'),
                    'PARENT' => $category->getParent(),
                    'URL' => $category->getUrl($this->locale),
                    'LOCALE' => $this->locale,
                ];

                $currentId = $category->getParent();

                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (\in_array($currentId, $ids)) {
                        throw new \LogicException(
                            sprintf(
                                'Circular reference detected in category ID=%d hierarchy (category ID=%d appears more than one times in path)',
                                $originalId,
                                $currentId
                            )
                        );
                    }

                    $ids[] = $currentId;
                }
            }
        } while ($category != null && $currentId > 0 && --$depth > 0);

        // Reverse list and build the final result
        return array_reverse($results);
    }

	 /**
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the category id
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc the language in which the information will be displayed
	 * 
	 * #doc-out-name $PARENT
	 * #doc-out-desc the parent category
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the category title
	 * 
	 * #doc-out-name $URL
	 * #doc-out-desc the category URL
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
}
