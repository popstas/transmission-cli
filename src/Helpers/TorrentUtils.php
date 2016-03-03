<?php

namespace Popstas\Transmission\Console\Helpers;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentUtils
{
    public static function getTorrentsSize(array $torrentList, $fieldName = Torrent\Get::TOTAL_SIZE)
    {
        $torrentSize = 0;
        foreach ($torrentList as $torrent) {
            $torrentSize += $torrent[$fieldName];
        }
        return $torrentSize;
    }

    public static function getTorrentsField(array $torrentList, $fieldName)
    {
        $fields = [];
        foreach ($torrentList as $torrent) {
            $fields[] = $torrent[$fieldName];
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
        $filters += ['age_min' => 0, 'age_max' => 99999, 'name' => ''];
        $filters['name'] = str_replace(['/', '.', '*'], ['\/', '\.', '.*?'], $filters['name']);

        if (isset($filters['age'])) {
            $filters = self::parseAgeFilter($filters['age']) + $filters;
        }

        return array_filter($torrentList, function ($torrent) use ($filters) {
            $age = self::getTorrentAgeInDays($torrent);
            if ($age < $filters['age_min'] || $age > $filters['age_max']) {
                return false;
            }
            if (isset($torrent[Torrent\Get::NAME])
                && !preg_match('/' . $filters['name'] . '/i', $torrent[Torrent\Get::NAME])
            ) {
                return false;
            }
            return true;
        });
    }

    private static function parseAgeFilter($age)
    {
        $filters = [];
        preg_match_all('/([<>])\s?(\d+)/', $age, $results, PREG_SET_ORDER);
        if ($results) {
            foreach ($results as $result) {
                $ageOperator = $result[1];
                $ageValue = $result[2];
                if ($ageOperator == '<') {
                    $filters['age_max'] = $ageValue - 1;
                }
                if ($ageOperator == '>') {
                    $filters['age_min'] = $ageValue + 1;
                }
            }
        }
        return $filters;
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

    public static function sortRowsByColumnNumber(array $rows, $columnNumber)
    {
        $rowsSorted = $rows;
        $columnsTotal = count(end($rows));

        $sortOrder = $columnNumber > 0 ? 1 : -1;

        $columnIndex = max(1, min(
            $columnsTotal,
            abs($columnNumber)
        )) - 1;

        usort($rowsSorted, function ($first, $second) use ($columnIndex, $sortOrder) {
            return $first[$columnIndex] > $second[$columnIndex] ? $sortOrder : $sortOrder * -1;
        });

        return $rowsSorted;
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
            TorrentUtils::getSizeInGb(TorrentUtils::getTorrentsSize($torrentList)),
            TorrentUtils::getSizeInGb(TorrentUtils::getTorrentsSize($torrentList, Torrent\Get::UPLOAD_EVER)),
            ''
        ];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'totals' => $totals
        ];
    }

    public static function printTorrentsTable(array $torrentList, OutputInterface $output, $sortColumnNumber = 1)
    {
        $data = self::buildTableData($torrentList);
        $data['rows'] = self::sortRowsByColumnNumber($data['rows'], $sortColumnNumber);

        $table = new Table($output);
        $table->setHeaders($data['headers']);
        $table->setRows($data['rows']);
        $table->addRow(new TableSeparator());
        $table->addRow($data['totals']);
        $table->render();
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

    public static function getSizeInGb($sizeInBytes, $round = 2)
    {
        return round($sizeInBytes / 1024 / 1024 / 1024, $round);
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
