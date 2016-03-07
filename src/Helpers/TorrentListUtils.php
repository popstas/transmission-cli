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
                // TODO: it wrong if sort and limit applied, see https://github.com/popstas/transmission-cli/issues/21
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
            'Total',
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
