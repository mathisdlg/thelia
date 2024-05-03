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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\ProductAssociatedContentQuery;

/**
 * AssociatedContent loop.
 *
 * Class AssociatedContent
 * 
 * #doc-desc Associated content loop lists associated contents of a product or a category. It behaves like a content loop therefore you might use all content loop arguments and outputs.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int   getProduct()
 * @method int   getCategory()
 * @method int[] getExcludeProduct()
 * @method int[] getExcludeCategory()
 */
class AssociatedContent extends Content
{
    protected $contentId;
    protected $contentPosition;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name all content loop arguments
	 * #doc-arg-desc 
	 * #doc-arg-example exclude_folder="1,2,9"
	 * 
	 * #doc-arg-name category \*\*
	 * #doc-arg-desc A single category id.
	 * #doc-arg-example category="5"
	 * 
	 * #doc-arg-name exclude_category
	 * #doc-arg-desc A single or a list of category ids. If a content is in multiple categories which are not all excluded it will not be excluded.
	 * #doc-arg-example exclude_category="5"
	 * 
	 * #doc-arg-name exclude_product
	 * #doc-arg-desc A single or a list of product ids. If a content is in multiple products which are not all excluded it will not be excluded.
	 * #doc-arg-example exclude_product="5"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values <br/> Expected values
	 * #doc-arg-example order="associated_content"
	 * 
	 * #doc-arg-name product \*\*
	 * #doc-arg-desc A single product id.
	 * #doc-arg-example product="2"
     */
    protected function getArgDefinitions()
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection
            ->addArgument(Argument::createIntTypeArgument('product'))
            ->addArgument(Argument::createIntTypeArgument('category'))
            ->addArgument(Argument::createIntListTypeArgument('exclude_product'))
            ->addArgument(Argument::createIntListTypeArgument('exclude_category'))
        ;

        $argumentCollection->get('order')->default = 'associated_content';

        $argumentCollection->get('order')->type->getKey(0)->addValue('associated_content');
        $argumentCollection->get('order')->type->getKey(0)->addValue('associated_content_reverse');

        return $argumentCollection;
    }

    public function buildModelCriteria()
    {
        $product = $this->getProduct();
        $category = $this->getCategory();

        if ($product === null && $category === null) {
            throw new \InvalidArgumentException('You have to provide either `product` or `category` argument in associated_content loop');
        }

        if ($product !== null) {
            /** @var ProductAssociatedContentQuery $search */
            $search = ProductAssociatedContentQuery::create();

            $search->filterByProductId($product, Criteria::EQUAL);
        } elseif ($category !== null) {
            /** @var CategoryAssociatedContentQuery $search */
            $search = CategoryAssociatedContentQuery::create();

            $search->filterByCategoryId($category, Criteria::EQUAL);
        }

        $excludeProduct = $this->getExcludeProduct();

        // If we have to filter by product, find all products assigned to this product, and filter by found IDs
        if (null !== $excludeProduct) {
            // Exclude all contents related to the given product
            $search->filterById(
                ProductAssociatedContentQuery::create()->filterByProductId($excludeProduct)->select('product_id')->find(),
                Criteria::NOT_IN
            );
        }

        $excludeCategory = $this->getExcludeCategory();

        // If we have to filter by category, find all contents assigned to this category, and filter by found IDs
        if (null !== $excludeCategory) {
            // Exclure tous les attribut qui sont attachés aux templates indiqués
            $search->filterById(
                CategoryAssociatedContentQuery::create()->filterByProductId($excludeCategory)->select('category_id')->find(),
                Criteria::NOT_IN
            );
        }

        $order = $this->getOrder();
        $orderByAssociatedContent = array_search('associated_content', $order);
        $orderByAssociatedContentReverse = array_search('associated_content_reverse', $order);

        if ($orderByAssociatedContent !== false) {
            $search->orderByPosition(Criteria::ASC);
            $order[$orderByAssociatedContent] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
        }
        if ($orderByAssociatedContentReverse !== false) {
            $search->orderByPosition(Criteria::DESC);
            $order[$orderByAssociatedContentReverse] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
        }

        $associatedContents = $this->search($search);

        $associatedContentIdList = [0];

        $this->contentPosition = $this->contentId = [];

        foreach ($associatedContents as $associatedContent) {
            $associatedContentId = $associatedContent->getContentId();

            $associatedContentIdList[] = $associatedContentId;
            $this->contentPosition[$associatedContentId] = $associatedContent->getPosition();
            $this->contentId[$associatedContentId] = $associatedContent->getId();
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue(implode(',', $associatedContentIdList));
        } else {
            $this->args->get('id')->setValue(implode(',', array_intersect($receivedIdList, $associatedContentIdList)));
        }

        return parent::buildModelCriteria();
    }

	 /**
	 * 
	 * #doc-out-name $CONTENT_ID
	 * #doc-out-desc the associated content id
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the ID of each content
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc the postition of each contents
	 * 
	 * #doc-out-name all content loop outputs, except ID, which is the ID of the relation.
	 * #doc-out-desc 
	 */
    public function parseResults(LoopResult $results)
    {
        $results = parent::parseResults($results);

        foreach ($results as $loopResultRow) {
            $relatedContentId = $loopResultRow->get('ID');

            $loopResultRow
                ->set('ID', $this->contentId[$relatedContentId])
                ->set('CONTENT_ID', $relatedContentId)
                ->set('POSITION', $this->contentPosition[$relatedContentId])

            ;
        }

        return $results;
    }
}
