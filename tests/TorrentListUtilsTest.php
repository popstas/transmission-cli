<?php

namespace Popstas\Transmission\Console\Tests;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class TorrentListUtilsTest extends TestCase
{
    public function testGetTorrentsSize()
    {
        $torrentSize = TorrentListUtils::getTorrentsSize($this->expectedTorrentList);
        $this->assertEquals(7, $torrentSize);
    }

    public function testGetTorrentsField()
    {
        $torrentField = TorrentListUtils::getTorrentsField($this->expectedTorrentList, Torrent\Get::NAME);
        $this->assertEquals(['name.ext', 'name.ext', 'name2.ext', 'name.ext'], $torrentField);
    }

    public function filterTorrentsByAgeProvider()
    {
        return [
            'empty filter' => [[0, 1, 2, 3], []],
            'age >0'       => [[1, 2, 3],    ['age' => '>0']],
            'age < 3'      => [[0, 1, 2],    ['age' => '< 3']],
            'age_min=1'    => [[1, 2, 3],    ['age_min' => '1']],
            'age_max=2'    => [[0, 1, 2],    ['age_max' => '2']],
            'age >0 < 3'   => [[1, 2],       ['age' => '>0 < 3']],
        ];
    }

    /**
     * @dataProvider filterTorrentsByAgeProvider
     * @param $expectedKeys
     * @param $ageFilter
     */
    public function testFilterTorrentsByAge($expectedKeys, $ageFilter)
    {
        $torrentList = [
            ['doneDate' => time() - 86400 * 0],
            ['doneDate' => time() - 86400 * 1],
            ['doneDate' => time() - 86400 * 2],
            ['doneDate' => time() - 86400 * 3],
        ];

        $this->assertEquals($expectedKeys, array_keys(TorrentListUtils::filterTorrents($torrentList, $ageFilter)));
    }

    public function testFilterTorrentsByName()
    {
        $torrentList = [
            ['name' => 'file'],
            ['name' => 'other file'],
            ['name' => 'movie.mkv'],
            ['name' => 'Movie Season_1080p'],
        ];

        $this->assertEquals(
            [0, 1],
            array_keys(TorrentListUtils::filterTorrents($torrentList, ['name' => 'file']))
        );
        $this->assertEquals(
            [0, 1, 2, 3],
            array_keys(TorrentListUtils::filterTorrents($torrentList, ['name' => 'fil|mov']))
        );
        $this->assertEquals(
            [3],
            array_keys(TorrentListUtils::filterTorrents($torrentList, ['name' => 'season*1080']))
        );
    }

    public function testBuildTableData()
    {
        $data = TorrentListUtils::buildTableData($this->expectedTorrentList);

        $this->assertEquals(
            count($data['headers']),
            count(end($data['rows']))
        );

        $this->assertEquals(
            count($data['totals']),
            count(end($data['rows']))
        );
    }

    public function testSortRowsByColumnNumber()
    {
        $data = TorrentListUtils::buildTableData($this->expectedTorrentList);
        $rows = $data['rows'];

        $sortedRows = TorrentListUtils::sortRowsByColumnNumber($rows, 2);
        $sortedIds = TorrentListUtils::getTorrentsField($sortedRows, 1);
        $this->assertEquals([1, 2, 3, 4], $sortedIds);

        $sortedRows = TorrentListUtils::sortRowsByColumnNumber($rows, -2);
        $sortedIds = TorrentListUtils::getTorrentsField($sortedRows, 1);
        $this->assertEquals([4, 3, 2, 1], $sortedIds);
    }

    // it asserts nothing
    public function testPrintTorrentsTable()
    {
        $output = new NullOutput();
        TorrentListUtils::printTorrentsTable($this->expectedTorrentList, $output);
    }

    public function testGetObsoleteTorrents()
    {
        $obsolete = TorrentListUtils::getObsoleteTorrents($this->expectedTorrentList);
        $this->assertCount(1, $obsolete);
        $this->assertEquals(1, $obsolete[0]['id']);
    }
}
