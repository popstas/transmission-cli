<?php

namespace Popstas\Transmission\Console\Tests;

use Popstas\Transmission\Console\Helpers\TableUtils;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;

class TableUtilsTest extends TestCase
{
    public function testSortRowsByColumnNumber()
    {
        $data = TorrentListUtils::buildTableData($this->expectedTorrentList);
        $rows = $data['rows'];

        $sortedRows = TableUtils::sortRowsByColumnNumber($rows, 2);
        $sortedIds = TorrentListUtils::getArrayField($sortedRows, 1);
        $this->assertEquals([1, 2, 3, 4], $sortedIds);

        $sortedRows = TableUtils::sortRowsByColumnNumber($rows, -2);
        $sortedIds = TorrentListUtils::getArrayField($sortedRows, 1);
        $this->assertEquals([4, 3, 2, 1], $sortedIds);
    }
}
