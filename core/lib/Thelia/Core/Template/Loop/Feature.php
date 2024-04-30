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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Feature as FeatureModel;
use Thelia\Model\FeatureI18nQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\Map\FeatureTemplateTableMap;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Feature loop.
 *
 * Class Feature
 * 
 * #doc-usage {loop type="feature" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Feature loop lists features.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int[]       getProduct()
 * @method int[]       getTemplate()
 * @method int[]       getExcludeTemplate()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method string      getTitle()
 * @method string[]    getOrder()
 */
class Feature extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $useFeaturePosition;

    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of feature ids to exclude.
	 * #doc-arg-example exclude="456,123"
	 * 
	 * #doc-arg-name exclude_template
	 * #doc-arg-desc A single or a list of template ids. Only features NOT attached to these templates will be returned.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of feature ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name lang
	 * #doc-arg-desc A lang id
	 * #doc-arg-example lang="1"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default manual
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name product
	 * #doc-arg-desc A single or a list of product ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name template
	 * #doc-arg-desc A single or a list of template ids. Only features attached to these templates will be returned.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name title
	 * #doc-arg-desc A a string title
	 * #doc-arg-example title="foo"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc A boolean value.
	 * #doc-arg-default yes
	 * #doc-arg-example visible="no"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('product'),
            Argument::createIntListTypeArgument('template'),
            Argument::createIntListTypeArgument('exclude_template'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'alpha', 'alpha-reverse', 'manual', 'manual_reverse'])
                ),
                'manual'
            ),
            Argument::createAnyTypeArgument('title')
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria|FeatureQuery
     */
    public function buildModelCriteria()
    {
        $search = FeatureQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible != BooleanOrBothType::ANY) {
            $search->filterByVisible($visible);
        }

        $product = $this->getProduct();

        $template = $this->getTemplate() ?? [];
        $excludeTemplate = $this->getExcludeTemplate();

        $this->useFeaturePosition = true;

        if (null !== $product) {
            // As we join with freature_value, we may get multiple times the same line
            $search->distinct();

            // Find all template assigned to the products.
            $products = ProductQuery::create()->filterById($product, Criteria::IN)->find();

            /** @var ProductModel $product */
            foreach ($products as $product) {
                if (!$this->getBackendContext()) {
                    $search
                        ->useFeatureProductQuery()
                        ->filterByProduct($product)
                        ->endUse()
                    ;
                }

                $tplId = $product->getTemplateId();

                if (!empty($tplId)) {
                    $template[] = $tplId;
                }
            }

            // franck@cqfdev.fr - 05/12/2013 : if the given product has no template
            // or if the product cannot be found, do not return anything.
            if (empty($template)) {
                return null;
            }
        }

        if (\count($template) > 0) {
            // Join with feature_template table to get position, if a manual order position is required
            if (\count(array_diff(['manual_reverse', 'manual'], $this->getOrder())) < 2) {
                $search
                    ->useFeatureTemplateQuery()
                        ->filterByTemplate(
                            TemplateQuery::create()->filterById($template, Criteria::IN)->find(),
                            Criteria::IN
                        )
                    ->endUse()
                    ->withColumn(FeatureTemplateTableMap::COL_POSITION, 'position');

                $this->useFeaturePosition = false;
            }
        }

        if (null !== $excludeTemplate) {
            $search
                ->filterById(
                    FeatureTemplateQuery::create()->filterByTemplateId($excludeTemplate)->select('feature_id')->find(),
                    Criteria::NOT_IN
                )
            ;
        }

        $title = $this->getTitle();

        if (null !== $title) {
            // find all feature that match exactly this title and find with all locales.
            $features = FeatureI18nQuery::create()
                ->filterByTitle($title, Criteria::LIKE)
                ->select('id')
                ->find();

            if ($features) {
                $search->filterById(
                    $features,
                    Criteria::IN
                );
            }
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
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual':
                    if ($this->useFeaturePosition) {
                        $search->orderByPosition(Criteria::ASC);
                    } else {
                        $search->addAscendingOrderByColumn(FeatureTemplateTableMap::COL_POSITION);
                    }
                    break;
                case 'manual_reverse':
                    if ($this->useFeaturePosition) {
                        $search->orderByPosition(Criteria::DESC);
                    } else {
                        $search->addDescendingOrderByColumn(FeatureTemplateTableMap::COL_POSITION);
                    }
                    break;
            }
        }

        return $search;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return LoopResult
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the feature chapo
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the feature description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the feature id
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc check if the feature is translated
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc The locale used for this research
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc If none of the product, template or exclude_template parameter is present, $POSITION contains the feature position. Otherwise, it contains the feature position in the product template context.
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the feature postscriptum
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the feature title
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var FeatureModel $feature */
        foreach ($loopResult->getResultDataCollection() as $feature) {
            $loopResultRow = new LoopResultRow($feature);
            $loopResultRow->set('ID', $feature->getId())
                ->set('IS_TRANSLATED', $feature->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $feature->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $feature->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $feature->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $feature->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('POSITION', $this->useFeaturePosition ? $feature->getPosition() : $feature->getVirtualColumn('position'))
            ;
            $this->addOutputFields($loopResultRow, $feature);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
