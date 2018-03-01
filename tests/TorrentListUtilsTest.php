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
        $torrentSize = TorrentListUtils::sumArrayField($this->expectedTorrentList, Torrent\Get::TOTAL_SIZE);
        $this->assertEquals(7, $torrentSize);
    }

    public function testGetTorrentsField()
    {
        $torrentField = TorrentListUtils::getArrayField($this->expectedTorrentList, Torrent\Get::NAME);
        $this->assertEquals(['name.ext', 'name.ext', 'name2.ext', 'name.ext', 'zero sized torrent'], $torrentField);
    }

    public function filterTorrentsByAgeProvider()
    {
        return [
            'empty filter' => [[0, 1, 2, 3], []],
            'age >0'       => [[1, 2, 3],    ['age' => '>0']],
            'age < 3'      => [[0, 1, 2],    ['age' => '< 3']],
            'age >0 < 3'   => [[1, 2],       ['age' => '>0 < 3']],
            'age = 1'      => [[1],          ['age' => ' = 1 ']],
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
            ['age' => 0],
            ['age' => 1],
            ['age' => 2],
            ['age' => 3],
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

    public function testNullFilter()
    {
        $this->assertEquals(
            $this->expectedTorrentList,
            TorrentListUtils::filterTorrents($this->expectedTorrentList, ['name' => null])
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNotExistsColumnFilter()
    {
        TorrentListUtils::filterTorrents($this->expectedTorrentList, [
            'not_exists_key' => [
                'type' => 'regex',
                'value' => 'value']
        ]);
    }

    public function invalidFilterProvider()
    {
        return [
            'without type' => [[['value' => 'value']]],
            'wrong type' => [[['type' => 'unknown', 'value' => 'value']]]
        ];
    }

    /**
     * @dataProvider invalidFilterProvider
     * @expectedException \InvalidArgumentException
     * @param array $filters
     */
    public function testInvalidFilter(array $filters)
    {
        TorrentListUtils::filterTorrents([], $filters);
    }

    public function testBuildTableData()
    {
        $data = TorrentListUtils::buildTableData($this->expectedTorrentList);

        $this->assertEquals(
            count($data['headers']),
            count(end($data['rows']))
        );
    }

    // it asserts nothing
    public function testPrintTorrentsTable()
    {
        $output = new NullOutput();
        TorrentListUtils::printTorrentsTable($this->expectedTorrentList, $output, 1, 2);
        $this->assertTrue(true);
    }

    public function testGetObsoleteTorrents()
    {
        $torrentList = [
            [
                'downloadDir'  => '/',
                'id'           => 1,
                'name'         => 'duplicates-test',
                'files'        => [
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file1'
                    ],
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file2'
                    ],
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file3'
                    ],
                ]
            ],
            [
                'downloadDir'  => '/',
                'id'           => 2,
                'name'         => 'duplicates-test',
                'files'        => [
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file4'
                    ],
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file5'
                    ],
                ]
            ],
            # torrent id=3 is duplicate
            [
                'downloadDir'  => '/',
                'id'           => 3,
                'name'         => 'duplicates-test',
                'files'        => [
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file1'
                    ],
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file2'
                    ],
                ],
            ],
            [
                'downloadDir'  => '/',
                'id'           => 4,
                'name'         => 'duplicates-test',
                'files'        => [
                    [
                        'length' => 2,
                        'name'   => 'duplicates-test/file1'
                    ],
                    [
                        'length' => 4,
                        'name'   => 'duplicates-test/file2'
                    ],
                ],
            ],
        ];
        $obsolete = TorrentListUtils::getObsoleteTorrents($torrentList);
        $this->assertCount(1, $obsolete);
        $this->assertEquals(3, $obsolete[0]['id']);
    }
}
