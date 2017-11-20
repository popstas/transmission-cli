<?php

namespace Popstas\Transmission\Console\Helpers;

use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;

class TorrentUtils
{
    /**
     * @param $torrent
     * @return int seconds from torrent finish download
     */
    public static function getTorrentAge($torrent)
    {
        $date = isset($torrent[Torrent\Get::DONE_DATE]) && $torrent[Torrent\Get::DONE_DATE] ?
            $torrent[Torrent\Get::DONE_DATE] :
            (isset($torrent[Torrent\Get::ADDED_DATE]) ? $torrent[Torrent\Get::ADDED_DATE] : 0);
        return $date ? time() - $date : 0;
    }

    public static function getTorrentAgeInDays($torrent)
    {
        return round(self::getTorrentAge($torrent) / 86400);
    }

    public static function getSizeInGb($sizeInBytes, $round = 2)
    {
        // 1024 not equals transmission value
        return round($sizeInBytes / 1024 / 1024 / 1024, $round);
    }
}
