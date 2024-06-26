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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Type;

/**
 * 
 * #doc-desc Template loop, to get available back-office or front-office templates.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Template extends BaseLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {

        return new ArgumentCollection(
            // #doc-arg-desc the type of the template you want
            new Argument(
                'template-type',
                new Type\TypeCollection(
                    new Type\EnumType([
                        'front-office',
                        'front',
                        'back-office',
                        'admin',
                        'pdf',
                        'email',
                        'mail',
                    ])
                )
            )
        );
    }

    public function buildArray()
    {
        $type = $this->getArg('template-type')->getValue();

        $templateType = TemplateDefinition::FRONT_OFFICE;

        if ($type == 'front-office' || $type == 'front') {
            $templateType = TemplateDefinition::FRONT_OFFICE;
        } elseif ($type == 'back-office' || $type == 'admin') {
            $templateType = TemplateDefinition::BACK_OFFICE;
        } elseif ($type == 'pdf') {
            $templateType = TemplateDefinition::PDF;
        } elseif ($type == 'email' || $type == 'mail') {
            $templateType = TemplateDefinition::EMAIL;
        }

        return $this->container->get('thelia.template_helper')->getList($templateType);
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var TemplateDefinition $template */
        foreach ($loopResult->getResultDataCollection() as $template) {
            $loopResultRow = new LoopResultRow($template);

            $loopResultRow
                // #doc-out-desc template name
                ->set('NAME', $template->getName())
                // #doc-out-desc relative template path
                ->set('RELATIVE_PATH', $template->getPath())
                // #doc-out-desc absolute template path
                ->set('ABSOLUTE_PATH', $template->getAbsolutePath())
            ;
            $this->addOutputFields($loopResultRow, $template);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
