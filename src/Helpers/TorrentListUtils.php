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
            // TODO: move to getTorrentData()
            $torrentList = array_map(function ($torrent) {
                $torrent['age'] = TorrentUtils::getTorrentAgeInDays($torrent);
                return $torrent;
            }, $torrentList);
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

        $totals = [
            'Total',
            '',
            '',
            TorrentUtils::getSizeInGb(self::sumArrayField($torrentList, Torrent\Get::TOTAL_SIZE)),
            TorrentUtils::getSizeInGb(self::sumArrayField($torrentList, Torrent\Get::UPLOAD_EVER)),
            ''
        ];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'totals' => $totals
        ];
    }

    public static function printTorrentsTable(
        array $torrentList,
        OutputInterface $output,
        $sortColumnNumber = 1,
        $limit = 0
    ) {
        $data = self::buildTableData($torrentList);
        TableUtils::printTable($data, $output, $sortColumnNumber, $limit);
    }

    public static function getObsoleteTorrents(array $torrentList)
    {
        $all = [];
        $obsolete = [];

        foreach ($torrentList as $torrent) {
            $name = $torrent[Torrent\Get::NAME];
            if (!isset($all[$name])) {
                $all[$name] = $torrent;
                continue;
            }

            $obs = self::detectObsoleteTorrent($all[$name], $torrent);
            if ($obs) {
                $obsolete[] = $obs;
                if ($obs[Torrent\Get::ID] !== $torrent[Torrent\Get::ID]) {
                    $all[$name] = $torrent;
                }
            }
        }

        return $obsolete;
    }

    private static function detectObsoleteTorrent($torrentInList, $torrentNotInList)
    {
        if ($torrentInList[Torrent\Get::DOWNLOAD_DIR] !== $torrentNotInList[Torrent\Get::DOWNLOAD_DIR]) {
            return false;
        }

        return $torrentInList[Torrent\Get::TOTAL_SIZE] < $torrentNotInList[Torrent\Get::TOTAL_SIZE] ?
            $torrentInList : $torrentNotInList;
    }
}
