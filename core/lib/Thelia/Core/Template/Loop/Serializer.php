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

use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class Serializer.
 * 
 * #doc-desc Serializer loop lists serializers.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Serializer extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A serializer
            Argument::createAnyTypeArgument('serializer'),
            // #doc-arg-desc A list of values see sorting possible values
            new Argument(
                'order',
                new TypeCollection(
                    new EnumType(['alpha', 'alpha_reverse'])
                ),
                'alpha'
            )
        );
    }

    public function buildArray()
    {
        /** @var \Thelia\Core\Serializer\SerializerManager $serializerManager */
        $serializerManager = $this->container->get(RegisterSerializerPass::MANAGER_SERVICE_ID);

        $serializerId = $this->getArgValue('serializer');
        if ($serializerId === null) {
            $serializers = $serializerManager->getSerializers();
        } else {
            $serializers = [$serializerManager->get($serializerId)];
        }

        switch ($this->getArgValue('order')) {
            case 'alpha':
                ksort($serializers);
                break;
            case 'alpha_reverse':
                krsort($serializers);
                break;
        }

        return $serializers;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Core\Serializer\SerializerInterface $serializer */
        foreach ($loopResult->getResultDataCollection() as $serializer) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                // #doc-out-desc the serializer id
                ->set('ID', $serializer->getId())
                // #doc-out-desc the serialiser name
                ->set('NAME', $serializer->getName())
                // #doc-out-desc the serializer extension
                ->set('EXTENSION', $serializer->getExtension())
                // #doc-out-desc the serializer mime type
                ->set('MIME_TYPE', $serializer->getMimeType());

            $this->addOutputFields($loopResultRow, $serializer);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
