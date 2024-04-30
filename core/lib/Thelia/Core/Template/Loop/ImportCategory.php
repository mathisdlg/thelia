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

use Thelia\Model\ImportCategoryQuery;

/**
 * Class ImportCategory.
 * 
 * #doc-usage {loop type="import_category" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Import category loop lists all defined import categories.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportCategory extends ImportExportCategory
{
    /**
     * @return ImportCategoryQuery
     */
    protected function getQueryModel()
    {
        return ImportCategoryQuery::create();
    }
}
