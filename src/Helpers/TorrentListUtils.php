<?php

namespace Popstas\Transmission\Console\Helpers;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentListUtils
{
    public static function sumArrayField(array $rows, $fieldKey)
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += $row[$fieldKey];
        }
        return $sum;
    }

    public static function getArrayField(array $rows, $fieldKey)
    {
        $fields = [];
        foreach ($rows as $torrent) {
            $fields[] = $torrent[$fieldKey];
        }
        return $fields;
    }

    /**
     * @param array $torrentList
     *
     * @param array $filters
     * filters[
     * - age
     * - age_min
     * - age_max
     * ]
     *
     * @return array
     */
    public static function filterTorrents(array $torrentList, array $filters)
    {
        if (isset($filters['name'])) {
            $filters[Torrent\Get::NAME] = ['type' => 'regex', 'value' => $filters['name']];
        }
        if (isset($filters['age'])) {
            $filters['age'] = ['type' => 'numeric', 'value' => $filters['age']];
        }

        $torrentList = TableUtils::filterRows($torrentList, $filters);
        return $torrentList;
    }

    public static function buildTableData(array $torrentList)
    {
        $headers = ['Name', 'Id', 'Age', 'Size', 'Uploaded', 'Per day'];
        $rows = [];

        foreach ($torrentList as $torrent) {
            $age = TorrentUtils::getTorrentAgeInDays($torrent);
            $perDay = $age ? TorrentUtils::getSizeInGb($torrent[Torrent\Get::UPLOAD_EVER] / $age) : 0;

            $rows[] = [
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                $age,
                TorrentUtils::getSizeInGb($torrent[Torrent\Get::TOTAL_SIZE]),
                TorrentUtils::getSizeInGb($torrent[Torrent\Get::UPLOAD_EVER]),
                $perDay,
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    public static function printTorrentsTable(
        array $torrentList,
        OutputInterface $output,
        $sortColumnNumber = 1,
        $limit = 0
    ) {
        $data = self::buildTableData($torrentList);

        $data['rows'] = TableUtils::sortRowsByColumnNumber($data['rows'], $sortColumnNumber);
        $data['rows'] = TableUtils::limitRows($data['rows'], $limit);

        $data['totals'] = [
            'Total: ' . count($data['rows']),
            '',
            '',
            self::sumArrayField($data['rows'], 3),
            self::sumArrayField($data['rows'], 4),
            ''
        ];

        TableUtils::printTable($data, $output);
    }

    public static function getObsoleteTorrents(array $torrentList)
    {
        $sameNamesAndDirectory = [];
        $obsolete = [];

        foreach ($torrentList as $torrent) {
            $key = $torrent[Torrent\Get::NAME] . '_' . $torrent[Torrent\Get::DOWNLOAD_DIR];
            $sameNamesAndDirectory[$key][$torrent[Torrent\Get::ID]] = $torrent;
        }

        foreach ($sameNamesAndDirectory as $key => $torrents) {
            if (count($torrents) < 2) {
                continue;
            }

            $obsolete = array_merge($obsolete, self::detectObsoleteTorrent($torrents));
        }

        return $obsolete;
    }

    private static function detectObsoleteTorrent($torrents)
    {
        $all = [];
        $obsoleteTorrents = [];

        foreach ($torrents as $torrentNotInList) {
            foreach ($all as $torrentInList) {
                $obsolete = self::getCoveredTorrent($torrentInList, $torrentNotInList);
                if ($obsolete) {
                    $obsoleteTorrents[] = $obsolete;
                }
            }
            $all[] = $torrentNotInList;
        }

        return $obsoleteTorrents;
    }

    private static function getCoveredTorrent($torrent1, $torrent2)
    {
        $files1 = self::getFilesArray($torrent1);
        $files2 = self::getFilesArray($torrent2);

        if (self::isFilesContainsAllOtherFiles($files1, $files2)) {
            return $torrent2;
        } elseif (self::isFilesContainsAllOtherFiles($files2, $files1)) {
            return $torrent1;
        }

        return false;
    }

    private static function isFilesContainsAllOtherFiles($files, $otherFiles)
    {
        return empty(array_diff($otherFiles, $files));
    }

    private static function getFilesArray($torrent)
    {
        return array_map(function ($file) {
            return $file['length'] . '_' . $file['name'];
        }, $torrent[Torrent\Get::FILES]);
    }
}
