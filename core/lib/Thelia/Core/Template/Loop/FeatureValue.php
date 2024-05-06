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
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('feature', null, true),
		    // #doc-arg-desc A single product id.
            Argument::createIntTypeArgument('product'),
		    // #doc-arg-desc A single or a list of feature availability ids.
            Argument::createIntListTypeArgument('feature_availability'),
		    // #doc-arg-desc A single or a list of strings.
            Argument::createAnyListTypeArgument('free_text'),
		    // #doc-arg-desc A boolean value to return only features with feature availability (no personal value).
            Argument::createBooleanTypeArgument('exclude_feature_availability', 0),
		    // #doc-arg-desc A boolean value to return only features with free text value (no feature availability).
            Argument::createBooleanTypeArgument('exclude_free_text', 0),
            // #doc-arg-desc A list of values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['alpha', 'alpha_reverse', 'manual', 'manual_reverse'])
                ),
                'manual'
            ),
		    // #doc-arg-desc A boolean value to return all features, even if they are not available.
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


    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\FeatureProduct $featureValue */
        foreach ($loopResult->getResultDataCollection() as $featureValue) {
            $loopResultRow = new LoopResultRow($featureValue);

            $loopResultRow
		        // #doc-out-desc the feature value id
                ->set('ID', $featureValue->getId())
		        // #doc-out-desc the id of the product. Deprecated, please use $PRODUCT_ID instead
                ->set('PRODUCT', $featureValue->getProductId())
		        // #doc-out-desc (2.2) the id of the product
                ->set('PRODUCT_ID', $featureValue->getProductId())
		        // #doc-out-desc the feature av. ID. Null if the feature ha no feature av. Use FREE_TEXT_VALUE in this case.
                ->set('FEATURE_AV_ID', $featureValue->getFeatureAvId())
		        // #doc-out-desc 1 if this feature is free text, 0 otherwise. Deprecated in 2.4
                ->set('FREE_TEXT_VALUE', $featureValue->getFreeTextValue())
		        // #doc-out-desc 1 if this feature is free text, 0 otherwise.
                ->set('IS_FREE_TEXT', null === $featureValue->getFeatureAvId() ? 1 : 0)
		        // #doc-out-desc 1 if this feature is feature av., 0 otherwise.
                ->set('IS_FEATURE_AV', null === $featureValue->getFeatureAvId() ? 0 : 1)
		        // #doc-out-desc the locale of returned results
                ->set('LOCALE', $this->locale)
		        // #doc-out-desc the feature availability title, or the feature value text for free text features.
                ->set('TITLE', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_TITLE'))
		        // #doc-out-desc the feature value chapo
                ->set('CHAPO', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_CHAPO'))
		        // #doc-out-desc the feature value description
                ->set('DESCRIPTION', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_DESCRIPTION'))
		        // #doc-out-desc the feature availability postscriptum
                ->set('POSTSCRIPTUM', $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME.'_i18n_POSTSCRIPTUM'))
		        // #doc-out-desc the feature value position
                ->set('POSITION', $featureValue->getPosition())
            ;
            $this->addOutputFields($loopResultRow, $featureValue);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
