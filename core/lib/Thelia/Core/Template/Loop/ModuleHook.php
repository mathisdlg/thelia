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
use Thelia\Model\ModuleHookQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * Class ModuleHook.
 * 
 * #doc-desc Module hook loop lists all defined module hooks.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int         getHook()
 * @method int         getModule()
 * @method int[]       getExclude()
 * @method bool|string getModuleActive()
 * @method bool|string getHookActive()
 * @method bool|string getActive()
 * @method string[]    getOrder()
 */
class ModuleHook extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = false;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name active
	 * #doc-arg-desc Check if the hook is active
	 * #doc-arg-example active="1"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of hook IDs to exclude
	 * #doc-arg-example exclude="1,2,3"
	 * 
	 * #doc-arg-name hook
	 * #doc-arg-desc The hook name
	 * #doc-arg-example hook="displayHeader"
	 * 
	 * #doc-arg-name hook_active
	 * #doc-arg-desc Check if the hook is active
	 * #doc-arg-example hook_active="1"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc The hook ID
	 * #doc-arg-example id="2"
	 * 
	 * #doc-arg-name module
	 * #doc-arg-desc The module name
	 * #doc-arg-example module="blockcart"
	 * 
	 * #doc-arg-name module_active
	 * #doc-arg-desc Check if the module is active
	 * #doc-arg-example module_active="1"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc a list of values see sorting possible values
	 * #doc-arg-example order="id"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('hook'),
            Argument::createIntTypeArgument('module'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'hook', 'hook_reverse', 'manual', 'manual_reverse', 'enabled', 'enabled_reverse'])
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', Type\BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('hook_active', Type\BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('module_active', Type\BooleanOrBothType::ANY)
        );
    }

    public function buildModelCriteria()
    {
        $search = ModuleHookQuery::create();

        $this->configureI18nProcessing($search, []);

        $id = $this->getId();
        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $hook = $this->getHook();
        if (null !== $hook) {
            $search->filterByHookId($hook, Criteria::EQUAL);
        }

        $module = $this->getModule();
        if (null !== $module) {
            $search->filterByModuleId($module, Criteria::EQUAL);
        }

        $exclude = $this->getExclude();
        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $active = $this->getActive();
        if ($active !== Type\BooleanOrBothType::ANY) {
            $search->filterByActive($active, Criteria::EQUAL);
        }

        $hookActive = $this->getHookActive();
        if ($hookActive !== Type\BooleanOrBothType::ANY) {
            $search->filterByHookActive($hookActive, Criteria::EQUAL);
        }

        $moduleActive = $this->getModuleActive();
        if ($moduleActive !== Type\BooleanOrBothType::ANY) {
            $search->filterByModuleActive($moduleActive, Criteria::EQUAL);
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
                case 'hook':
                    $search->orderByHookId(Criteria::ASC);
                    break;
                case 'hook_reverse':
                    $search->orderByHookId(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'enabled':
                    $search->orderByActive(Criteria::ASC);
                    break;
                case 'enabled_reverse':
                    $search->orderByActive(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ACTIVE
	 * #doc-out-desc The hook status
	 * 
	 * #doc-out-name $CLASSNAME
	 * #doc-out-desc The hook class name
	 * 
	 * #doc-out-name $HOOK_ACTIVE
	 * #doc-out-desc The hook status
	 * 
	 * #doc-out-name $HOOK_ID
	 * #doc-out-desc The hook ID
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc The hook module id
	 * 
	 * #doc-out-name $METHOD
	 * #doc-out-desc The hook method
	 * 
	 * #doc-out-name $MODULE_ACTIVE
	 * #doc-out-desc The module status
	 * 
	 * #doc-out-name $MODULE_CODE
	 * #doc-out-desc The module code
	 * 
	 * #doc-out-name $MODULE_ID
	 * #doc-out-desc The module ID
	 * 
	 * #doc-out-name $MODULE_TITLE
	 * #doc-out-desc The module title
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc The hook position
	 * 
	 * #doc-out-name $TEMPLATES
	 * #doc-out-desc The hook templates
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ModuleHook $moduleHook */
        foreach ($loopResult->getResultDataCollection() as $moduleHook) {
            if ($this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($moduleHook);

                $loopResultRow
                    ->set('ID', $moduleHook->getId())
                    ->set('HOOK_ID', $moduleHook->getHookId())
                    ->set('MODULE_ID', $moduleHook->getModuleId())
                    ->set('MODULE_TITLE', $moduleHook->getModule()->setLocale($this->locale)->getTitle())
                    ->set('MODULE_CODE', $moduleHook->getModule()->getCode())
                    ->set('CLASSNAME', $moduleHook->getClassname())
                    ->set('METHOD', $moduleHook->getMethod())
                    ->set('ACTIVE', $moduleHook->getActive())
                    ->set('HOOK_ACTIVE', $moduleHook->getHookActive())
                    ->set('MODULE_ACTIVE', $moduleHook->getModuleActive())
                    ->set('POSITION', $moduleHook->getPosition())
                    ->set('TEMPLATES', $moduleHook->getTemplates())
                ;

                $this->addOutputFields($loopResultRow, $moduleHook);
                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
