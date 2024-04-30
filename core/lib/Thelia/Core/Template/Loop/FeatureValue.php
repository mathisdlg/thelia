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
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Map\FeatureAvTableMap;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * FeatureValue loop.
 *
 * Class FeatureValue
 * 
 * #doc-usage {loop type="feature_value" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Feature value loop lists feature availabilities.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int      getFeature()
 * @method int      getProduct()
 * @method string[] getFreeText()
 * @method int[]    getFeatureAvailability()
 * @method bool     getExcludeFeatureAvailability()
 * @method bool     getExcludeFreeText()
 * @method string[] getOrder()
 */
class FeatureValue extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name exclude_feature_availability
	 * #doc-arg-desc A boolean value to return only features with feature availability (no personal value).
	 * #doc-arg-default 0
	 * #doc-arg-example feature_availability="true"
	 * 
	 * #doc-arg-name exclude_free_text
	 * #doc-arg-desc A boolean value to return only features with free text value (no feature availability).
	 * #doc-arg-default 0
	 * #doc-arg-example exclude_free_text="1" or exclude_free_text="true"
	 * 
	 * #doc-arg-name feature *
	 * #doc-arg-desc A single feature id.
	 * #doc-arg-default null
	 * #doc-arg-example feature="2"
	 * 
	 * #doc-arg-name feature_availability
	 * #doc-arg-desc A single or a list of feature availability ids.
	 * #doc-arg-example feature_availability="2,5"
	 * 
	 * #doc-arg-name force_return
	 * #doc-arg-desc A boolean value to return all features, even if they are not available.
	 * #doc-arg-default true
	 * #doc-arg-example force_return="1"
	 * 
	 * #doc-arg-name free_text
	 * #doc-arg-desc A single or a list of strings.
	 * #doc-arg-example free_text="some text,some other text"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default manual
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name product
	 * #doc-arg-desc A single product id.
	 * #doc-arg-example product="9"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('feature', null, true),
            Argument::createIntTypeArgument('product'),
            Argument::createIntListTypeArgument('feature_availability'),
            Argument::createAnyListTypeArgument('free_text'),
            Argument::createBooleanTypeArgument('exclude_feature_availability', 0),
            Argument::createBooleanTypeArgument('exclude_free_text', 0),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['alpha', 'alpha_reverse', 'manual', 'manual_reverse'])
                ),
                'manual'
            ),
            Argument::createBooleanTypeArgument('force_return', true)
        );
    }

    public function buildModelCriteria()
    {
        $search = FeatureProductQuery::create();

        // manage featureAv translations
        $this->configureI18nProcessing(
            $search,
            ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
            FeatureAvTableMap::TABLE_NAME,
            'FEATURE_AV_ID',
            true
        );

        $search
            ->useFeatureAvQuery('feature_av')
                ->withColumn(FeatureAvTableMap::COL_POSITION, 'feature_av_position')
            ->endUse();

        $feature = $this->getFeature();

        $search->filterByFeatureId($feature, Criteria::EQUAL);

        if (null !== $product = $this->getProduct()) {
            $search->filterByProductId($product, Criteria::EQUAL);
        }

        if (null !== $featureAvailability = $this->getFeatureAvailability()) {
            $search->filterByFeatureAvId($featureAvailability, Criteria::IN);
        }

        if (null !== $freeText = $this->getFreeText()) {
            $search->filterByFreeTextValue($freeText);
        }

        if (true === $excludeFeatureAvailability = $this->getExcludeFeatureAvailability()) {
            $search->filterByFeatureAvId(null, Criteria::ISNULL);
        }

        if (true === $excludeFreeText = $this->getExcludeFreeText()) {
            $search->filterByIsFreeText(false);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_TITLE');
                    break;
                case 'manual':
                    $search->orderBy('feature_av_position', Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderBy('feature_av_position', Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the feature value chapo
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the feature value description
	 * 
	 * #doc-out-name $FEATURE_AV_ID
	 * #doc-out-desc the feature av. ID. Null if the feature ha no feature av. Use FREE_TEXT_VALUE in this case.
	 * 
	 * #doc-out-name $FREE_TEXT_VALUE
	 * #doc-out-desc 1 if this feature is free text, 0 otherwise. Deprecated in 2.4
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the feature value id
	 * 
	 * #doc-out-name $IS_FEATURE_AV
	 * #doc-out-desc 1 if this feature is feature av., 0 otherwise.
	 * 
	 * #doc-out-name $IS_FREE_TEXT
	 * #doc-out-desc 1 if this feature is free text, 0 otherwise.
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc the locale of returned results
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc the feature value position
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the feature availability postscriptum
	 * 
	 * #doc-out-name $PRODUCT
	 * #doc-out-desc the id of the product. Deprecated, please use $PRODUCT_ID instead
	 * 
	 * #doc-out-name $PRODUCT_ID
	 * #doc-out-desc (2.2) the id of the product
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the feature availability title, or the feature value text for free text features.
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\FeatureProduct $featureValue */
        foreach ($loopResult->getResultDataCollection() as $featureValue) {
            $loopResultRow = new LoopResultRow($featureValue);

            $loopResultRow
                ->set('ID', $featureValue->getId())
                ->set('PRODUCT', $featureValue->getProductId())
                ->set('PRODUCT_ID', $featureValue->getProductId())
                ->set('FEATURE_AV_ID', $featureValue->getFeatureAvId())
                ->set('FREE_TEXT_VALUE', $featureValue->getFreeTextValue())
                ->set('IS_FREE_TEXT', null === $featureValue->getFeatureAvId() ? 1 : 0)
                ->set('IS_FEATURE_AV', null === $featureValue->getFeatureAvId() ? 0 : 1)
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_TITLE'))
                ->set('CHAPO', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_CHAPO'))
                ->set('DESCRIPTION', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_POSTSCRIPTUM'))
                ->set('POSITION', $featureValue->getPosition())
            ;
            $this->addOutputFields($loopResultRow, $featureValue);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
