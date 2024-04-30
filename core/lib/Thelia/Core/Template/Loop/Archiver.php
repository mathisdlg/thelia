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

/**
 * Class ArchiveBuilder.
 * 
 * #doc-usage {loop type="archiver" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Retrieves a list of archivers, sorts them and returns its information.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * 
 */
class Archiver extends BaseLoop implements ArraySearchLoopInterface
{
	 /**
	 * 
	 * #doc-arg-name archiver
	 * #doc-arg-desc generic type which represents the ID of the archiver
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name availble
	 * #doc-arg-desc specifies whether the archiver should be available
	 * #doc-arg-example available ='true'
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc specifies the sort order of archivers (alphabetical)
	 * #doc-arg-default alpha
	 * #doc-arg-example enum sort{ case alpha; case alpha_reverse; }
	 */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('available'),
            Argument::createAnyTypeArgument('archiver'),
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

	 /**
	 * 
	 * #doc-out-name $EXTENSION
	 * #doc-out-desc the type of file extension associated with the archive
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc ID of the archive
	 * 
	 * #doc-out-name $MIME
	 * #doc-out-desc the type MIME type associated with the archive
	 * 
	 * #doc-out-name $NAME
	 * #doc-out-desc Name of the archive
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Core\Archiver\ArchiverInterface $archiver */
        foreach ($loopResult->getResultDataCollection() as $archiver) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set('ID', $archiver->getId())
                ->set('NAME', $archiver->getName())
                ->set('EXTENSION', $archiver->getExtension())
                ->set('MIME_TYPE', $archiver->getMimeType());

            $this->addOutputFields($loopResultRow, $archiver);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
