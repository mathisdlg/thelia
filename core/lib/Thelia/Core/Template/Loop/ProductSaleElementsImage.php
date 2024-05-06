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
use Thelia\Model\ProductSaleElementsProductImageQuery;

/**
 * Class ProductSaleElementsImage.
 * 
 * #doc-desc Product sale elements image loop to display images of product's variations.
 *
 * @author Benjamin Perche <benjamin@thelia.net>
 *
 * @method int[]    getId()
 * @method int[]    getProductSaleElementsId()
 * @method int[]    getProductImageId()
 * @method string[] getOrder()
 */
class ProductSaleElementsImage extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * @return LoopResult
    */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ProductSaleElementsProductImage $productSaleElementImage */
        foreach ($loopResult->getResultDataCollection() as $productSaleElementImage) {
            $row = new LoopResultRow($productSaleElementImage);

            $row
                // #doc-out-desc Product id
                ->set('ID', $productSaleElementImage->getId())
                // #doc-out-desc Product sale element id
                ->set('PRODUCT_SALE_ELEMENTS_ID', $productSaleElementImage->getProductSaleElementsId())
                // #doc-out-desc Product image id
                ->set('PRODUCT_IMAGE_ID', $productSaleElementImage->getProductImageId())
            ;

            $this->addOutputFields($row, $productSaleElementImage);
            $loopResult->addRow($row);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments.
     *
     * example :
     * 
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or list of product id
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single or list of product sale element id
            Argument::createIntListTypeArgument('product_sale_elements_id'),
            // #doc-arg-desc A single or list of product image id
            Argument::createIntListTypeArgument('product_image_id'),
            // #doc-arg-desc A list of values see sorting possible values
            Argument::createEnumListTypeArgument(
                'order',
                [
                    'position',
                    'position-reverse',
                ],
                'position'
            )
        );
    }

    /**
     * this method returns a Propel ModelCriteria.
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $query = ProductSaleElementsProductImageQuery::create();

        if (null !== $id = $this->getId()) {
            $query->filterById($id);
        }

        if (null !== $pseId = $this->getProductSaleElementsId()) {
            $query->filterByProductSaleElementsId($pseId);
        }

        if (null !== $productImageId = $this->getProductImageId()) {
            $query->filterByProductImageId($productImageId);
        }

        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case 'position':
                    $query
                        ->useProductImageQuery()
                            ->orderByPosition(Criteria::ASC)
                        ->endUse()
                    ;
                    break;
                case 'position-reverse':
                    $query
                        ->useProductImageQuery()
                            ->orderByPosition(Criteria::DESC)
                        ->endUse()
                    ;
                    break;
            }
        }

        return $query;
    }
}
