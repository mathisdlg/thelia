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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;

/**
 * Brand loop.
 *
 * Class ModuleConfig
 * 
 * #doc-usage {loop type="module_config" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc The module config loop retrieve module config informations
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method string getModule()
 * @method string getVariable()
 * @method string getDefaultValue()
 * @method string getLocale()
 */
class ModuleConfig extends BaseLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name default_value
	 * #doc-arg-desc The default value
	 * #doc-arg-default null
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name locale
	 * #doc-arg-desc The locale
	 * #doc-arg-default null
	 * #doc-arg-example locale="en_US"
	 * 
	 * #doc-arg-name module*
	 * #doc-arg-desc The module
	 * #doc-arg-default null
	 * #doc-arg-example module="bestseller"
	 * 
	 * #doc-arg-name variable*
	 * #doc-arg-desc The variable
	 * #doc-arg-default null
	 * #doc-arg-example variable="rewriting_enable"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('module', null, true),
            Argument::createAnyTypeArgument('variable', null, true),
            Argument::createAnyTypeArgument('default_value', null),
            Argument::createAnyTypeArgument('locale', null)
        );
    }

    /**
     * @return LoopResult
	 * 
	 * #doc-out-name $VALUE
	 * #doc-out-desc The value of the variable
	 * 
	 * #doc-out-name $VARIABLE
	 * #doc-out-desc The variable name
     */
    public function parseResults(LoopResult $loopResult)
    {
        $moduleCode = $this->getModule();

        if (null === $module = ModuleQuery::create()->filterByCode($moduleCode, Criteria::LIKE)->findOne()) {
            throw new \InvalidArgumentException("Module with code '$moduleCode' does not exists.");
        }

        $configValue = ModuleConfigQuery::create()->getConfigValue(
            $module->getId(),
            $this->getVariable(),
            $this->getDefaultValue(),
            $this->getLocale()
        );

        $loopResultRow = new LoopResultRow();

        $loopResultRow
            ->set('VARIABLE', $this->getVariable())
            ->set('VALUE', $configValue)
        ;

        $loopResult->addRow($loopResultRow);

        return $loopResult;
    }

    /**
     * this method returns an array.
     *
     * @return array
     */
    public function buildArray()
    {
        // Return an array containing one element, so that parseResults() will be called one time.
        return ['dummy-element'];
    }
}
