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
use Thelia\Model\Config as ConfigModel;
use Thelia\Model\ConfigQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Config loop, to access configuration variables.
 *
 * - id is the config id
 * - name is the config name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of config IDs that will be excluded from output
 * 
 * #doc-desc Config loop, to access configuration variables
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method string      getVariable()
 * @method bool|string getHidden()
 * @method bool|string getSecured()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 */
class Config extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single of config id.
            Argument::createIntTypeArgument('id'),
            // #doc-arg-desc A single or a list of config ids.
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc Name of a variable config
            Argument::createAnyTypeArgument('variable'),
            // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('hidden'),
            // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('secured'),
            // #doc-arg-desc A list of values
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'name', 'name_reverse',
                            'title', 'title_reverse',
                            'value', 'value_reverse',
                        ]
                    )
                ),
                'name'
            )
        );
    }

    public function buildModelCriteria()
    {
        $id = $this->getId();
        $name = $this->getVariable();
        $secured = $this->getSecured();
        $exclude = $this->getExclude();

        $search = ConfigQuery::create();

        $this->configureI18nProcessing($search);

        if (null !== $id) {
            $search->filterById($id);
        }

        if (null !== $name) {
            $search->filterByName($name);
        }

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if ($this->getHidden() != BooleanOrBothType::ANY) {
            $search->filterByHidden($this->getHidden() ? 1 : 0);
        }

        if (null !== $secured && $secured != BooleanOrBothType::ANY) {
            $search->filterBySecured($secured ? 1 : 0);
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
                case 'name':
                    $search->orderByName(Criteria::ASC);
                    break;
                case 'name_reverse':
                    $search->orderByName(Criteria::DESC);
                    break;
                case 'title':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'title_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'value':
                    $search->orderByValue(Criteria::ASC);
                    break;
                case 'value_reverse':
                    $search->orderByValue(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    
    public function parseResults(LoopResult $loopResult)
    {
        /** @var ConfigModel $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                // #doc-out-desc the config variable id
                ->set('ID', $result->getId())
                // #doc-out-desc The config variable name
                ->set('NAME', $result->getName())
                // #doc-out-desc The config variable value
                ->set('VALUE', $result->getValue())
                // #doc-out-desc check if the config is overridden
                ->set('IS_OVERRIDDEN_IN_ENV', $result->isOverriddenInEnv())
                // #doc-out-desc check if the config is translated
                ->set('IS_TRANSLATED', $result->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
                // #doc-out-desc The config variable title
                ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc The config variable chapo
                ->set('CHAPO', $result->getVirtualColumn('i18n_CHAPO'))
                // #doc-out-desc The config variable description
                ->set('DESCRIPTION', $result->getVirtualColumn('i18n_DESCRIPTION'))
                // #doc-out-desc The config variable postscriptum
                ->set('POSTSCRIPTUM', $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                // #doc-out-desc check if the config variable is hidden
                ->set('HIDDEN', $result->getHidden())
                // #doc-out-desc Check if the config variable is secured
                ->set('SECURED', $result->isOverriddenInEnv() ? 1 : $result->getSecured())
            ;

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
