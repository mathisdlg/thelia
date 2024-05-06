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
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc The hook ID
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc The hook name
            Argument::createIntTypeArgument('hook'),
            // #doc-arg-desc The module name
            Argument::createIntTypeArgument('module'),
            // #doc-arg-desc a list of values see sorting possible values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'hook', 'hook_reverse', 'manual', 'manual_reverse', 'enabled', 'enabled_reverse'])
                ),
                'manual'
            ),
            // #doc-arg-desc A single or a list of hook IDs to exclude
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc Check if the hook is active
            Argument::createBooleanOrBothTypeArgument('active', Type\BooleanOrBothType::ANY),
            // #doc-arg-desc Check if the hook is active
            Argument::createBooleanOrBothTypeArgument('hook_active', Type\BooleanOrBothType::ANY),
            // #doc-arg-desc Check if the module is active
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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ModuleHook $moduleHook */
        foreach ($loopResult->getResultDataCollection() as $moduleHook) {
            if ($this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($moduleHook);

                $loopResultRow
                    // #doc-out-desc The hook module id
                    ->set('ID', $moduleHook->getId())
                    // #doc-out-desc The hook ID
                    ->set('HOOK_ID', $moduleHook->getHookId())
                    // #doc-out-desc The module ID
                    ->set('MODULE_ID', $moduleHook->getModuleId())
                    // #doc-out-desc The module title
                    ->set('MODULE_TITLE', $moduleHook->getModule()->setLocale($this->locale)->getTitle())
                    // #doc-out-desc The module code
                    ->set('MODULE_CODE', $moduleHook->getModule()->getCode())
                    // #doc-out-desc The hook class name
                    ->set('CLASSNAME', $moduleHook->getClassname())
                    // #doc-out-desc The hook method
                    ->set('METHOD', $moduleHook->getMethod())
                    // #doc-out-desc The hook status
                    ->set('ACTIVE', $moduleHook->getActive())
                    // #doc-out-desc The hook status
                    ->set('HOOK_ACTIVE', $moduleHook->getHookActive())
                    // #doc-out-desc The module status
                    ->set('MODULE_ACTIVE', $moduleHook->getModuleActive())
                    // #doc-out-desc The hook position
                    ->set('POSITION', $moduleHook->getPosition())
                    // #doc-out-desc The hook templates
                    ->set('TEMPLATES', $moduleHook->getTemplates())
                ;

                $this->addOutputFields($loopResultRow, $moduleHook);
                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
