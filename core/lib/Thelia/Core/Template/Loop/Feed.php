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

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * 
 * #doc-desc Get data from an Atom or RSS feed.
 * 
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method string getUrl()
 * @method int    getTimeout()
 */
class Feed extends BaseLoop implements ArraySearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc An Atom or RSS feed URL.
            Argument::createAnyTypeArgument('url', null, true),
            // #doc-arg-desc Delay in seconds after which the loop closes the connection with the remote server
            Argument::createIntTypeArgument('timeout', 10)
        );
    }

    public function buildArray()
    {
        /** @var AdapterInterface $cacheAdapter */
        $cacheAdapter = $this->container->get('thelia.cache');

        $cacheItem = $cacheAdapter->getItem('feed_'.md5($this->getUrl()));

        if (!$cacheItem->isHit()) {
            $feed = new \SimplePie();
            $feed->set_feed_url($this->getUrl());

            $feed->init();

            $feed->handle_content_type();

            $cacheItem->expiresAfter($this->getTimeout() * 60);
            $cacheItem->set($feed->get_items());
            $cacheAdapter->save($cacheItem);
        }

        return $cacheItem->get();
    }


    public function parseResults(LoopResult $loopResult)
    {
        /** @var \SimplePie_Item $item */
        foreach ($loopResult->getResultDataCollection() as $item) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                // #doc-out-desc the feed item URL
                ->set('URL', $item->get_permalink())
                // #doc-out-desc The feed item title
                ->set('TITLE', $item->get_title())
                // #doc-out-desc The feed item author
                ->set('AUTHOR', $item->get_author())
                // #doc-out-desc the feed item description
                ->set('DESCRIPTION', $item->get_description())
                // #doc-out-desc the feed item date, as a Unix timestamp
                ->set('DATE', $item->get_date('U'))
            ;
            $this->addOutputFields($loopResultRow, $item);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
