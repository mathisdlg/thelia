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

use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

// #doc-desc Retrieves a list of archivers, sorts them and returns its information.
/**
 * Class ArchiveBuilder.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Archiver extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            // #doc-arg-desc specifies whether the archiver should be available
            Argument::createBooleanTypeArgument('available'),
            // #doc-arg-desc generic type which represents the ID of the archiver
            Argument::createAnyTypeArgument('archiver'),
            // #doc-arg-desc specifies the sort order of archivers (alphabetical)
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
        /** @var \Thelia\Core\Archiver\ArchiverManager $archiverManager */
        $archiverManager = $this->container->get(RegisterArchiverPass::MANAGER_SERVICE_ID);

        $availability = $this->getArgValue('available');

        $archiverId = $this->getArgValue('archiver');
        if ($archiverId === null) {
            $archivers = $archiverManager->getArchivers($availability);
        } else {
            $archivers = [];
            $archiver = $archiverManager->get($archiverId, $availability);
            if ($archiver !== null) {
                $archivers[] = $archiver;
            }
        }

        switch ($this->getArgValue('order')) {
            case 'alpha':
                ksort($archivers);
                break;
            case 'alpha_reverse':
                krsort($archivers);
                break;
        }

        return $archivers;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Core\Archiver\ArchiverInterface $archiver */
        foreach ($loopResult->getResultDataCollection() as $archiver) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                // #doc-out-desc ID of the archive
                ->set('ID', $archiver->getId())
                // #doc-out-desc Name of the archive
                ->set('NAME', $archiver->getName())
                // #doc-out-desc the type of file extension associated with the archive
                ->set('EXTENSION', $archiver->getExtension())
                // #doc-out-desc the type MIME type associated with the archive
                ->set('MIME_TYPE', $archiver->getMimeType());

            $this->addOutputFields($loopResultRow, $archiver);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
