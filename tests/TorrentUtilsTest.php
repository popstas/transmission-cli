<?php

namespace Popstas\Transmission\Console\Tests;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;

class TorrentUtilsTest extends TestCase
{
    public function testGetTorrentAgeInDays()
    {
        // without doneDate
        $this->assertEquals(1, TorrentUtils::getTorrentAgeInDays([
            'doneDate' => 0,
            'addedDate' => time() - 86400
        ]));

        // with doneDate
        $this->assertEquals(2, TorrentUtils::getTorrentAgeInDays([
            'doneDate' => time() - 86400 * 2,
            'addedDate' => time() - 86400
        ]));
    }
}
