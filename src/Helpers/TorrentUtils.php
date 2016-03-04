<?php

namespace Popstas\Transmission\Console\Helpers;

use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;

class TorrentUtils
{
    /**
     * @param array $torrent
     * @param string $transmissionHost
     * @param array $lastPoint
     * @return InfluxDB\Point
     */
    public static function buildPoint(array $torrent, $transmissionHost, array $lastPoint)
    {
        $age = TorrentUtils::getTorrentAge($torrent);

        $tagsData = [
            'host'             => $transmissionHost,
            'torrent_name'     => $torrent[Torrent\Get::NAME],
        ];

        $uploadedDerivative = count($lastPoint) && $torrent[Torrent\Get::UPLOAD_EVER] - $lastPoint['last'] >= 0 ?
            $torrent[Torrent\Get::UPLOAD_EVER] - $lastPoint['last'] : $torrent[Torrent\Get::UPLOAD_EVER];

        $fieldsData = [
            'uploaded_last'       => $uploadedDerivative,
            'downloaded'          => $torrent[Torrent\Get::TOTAL_SIZE],
            'age'                 => $age,
            'uploaded_per_day'    => $age ? intval($torrent[Torrent\Get::UPLOAD_EVER] / $age * 86400) : 0,
        ];

        return new InfluxDB\Point(
            'uploaded',
            $torrent[Torrent\Get::UPLOAD_EVER],
            $tagsData,
            $fieldsData,
            time()
        );
    }

    public static function getLastPoint(array $torrent, $transmissionHost, InfluxDB\Database $database)
    {
        $torrentName = $torrent[Torrent\Get::NAME];
        $queryBuilder = $database->getQueryBuilder();
        $results = $queryBuilder
            ->last('value')
            ->from('uploaded')
            ->where([
                "host='$transmissionHost'",
                "torrent_name='$torrentName'"
            ])
            ->getResultSet()
            ->getPoints();

        return count($results) ? $results[0] : [];
    }

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
        return round($sizeInBytes / 1000 / 1000 / 1000, $round);
    }
}
