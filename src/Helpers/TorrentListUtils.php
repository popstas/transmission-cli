<?php

namespace Popstas\Transmission\Console\Helpers;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentListUtils
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

        $filters = self::parseFilters($filters);
        $torrentList = self::filterRows($torrentList, $filters);
        return $torrentList;
    }

    private static function parseFilters(array $filters)
    {
        foreach ($filters as $columnKey => $filter) {
            if (is_array($filter) && !isset($filter['type'])) {
                throw new \InvalidArgumentException('Unknown filter type');
            }
            if (!isset($filter) || !isset($filter['value'])) {
                unset($filters[$columnKey]);
                continue;
            }
            switch ($filter['type']) {
                case 'numeric':
                    $filters[$columnKey] = self::parseNumericFilter($filter['value'])
                        + $filters[$columnKey];
                    break;
                case 'regex':
                    $filters[$columnKey] = self::parseRegexFilter($filter['value'])
                        + $filters[$columnKey];
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown filter type');
            }
        }
        return $filters;
    }

    private static function parseNumericFilter($filterString)
    {
        $filter = [];
        preg_match_all('/([<>])\s?([\d\.]+)/', $filterString, $results, PREG_SET_ORDER);
        if ($results) {
            foreach ($results as $result) {
                $operator = $result[1];
                $value = $result[2];
                if ($operator == '<') {
                    $filter['max'] = $value;
                }
                if ($operator == '>') {
                    $filter['min'] = $value;
                }
            }
        }
        return $filter;
    }

    public static function parseRegexFilter($filterString)
    {
        $filter = [];
        $filter['regex'] = str_replace(['/', '.', '*'], ['\/', '\.', '.*?'], $filterString);
        return $filter;
    }

    public static function filterRows(array $rows, $filters)
    {
        $filters = self::parseFilters($filters);

        return array_filter($rows, function ($row) use ($filters) {
            foreach ($filters as $columnKey => $filter) {
                if (!isset($row[$columnKey])) {
                    continue;
                }
                $columnValue = $row[$columnKey];

                if ($filter['type'] == 'numeric') {
                    if ((isset($filter['min']) && $columnValue <= $filter['min']) ||
                        (isset($filter['max']) && $columnValue >= $filter['max'])
                    ) {
                        return false;
                    }
                }

                if ($filter['type'] == 'regex') {
                    if (!preg_match('/' . $filter['regex'] . '/i', $columnValue)) {
                        return false;
                    }
                }
            }
            return true;
        });
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
            TorrentUtils::getSizeInGb(self::getTorrentsSize($torrentList, Torrent\Get::TOTAL_SIZE)),
            TorrentUtils::getSizeInGb(self::getTorrentsSize($torrentList, Torrent\Get::UPLOAD_EVER)),
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
        self::printTable($data, $output, $sortColumnNumber, $limit);
    }

    public static function printTable(array $tableData, OutputInterface $output, $sortColumnNumber = 1, $limit = 0)
    {
        $tableData['rows'] = self::sortRowsByColumnNumber($tableData['rows'], $sortColumnNumber);

        if ($limit && $limit < count($tableData['rows'])) {
            $tableData['rows'] = array_slice($tableData['rows'], 0, $limit);
        }

        $table = new Table($output);
        $table->setHeaders($tableData['headers']);
        $table->setRows($tableData['rows']);
        $table->addRow(new TableSeparator());
        if (isset($tableData['totals'])) {
            $table->addRow($tableData['totals']);
        }
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

    private static function detectObsoleteTorrent($torrentInList, $torrentNotInList)
    {
        if ($torrentInList[Torrent\Get::DOWNLOAD_DIR] !== $torrentNotInList[Torrent\Get::DOWNLOAD_DIR]) {
            return false;
        }

        return $torrentInList[Torrent\Get::TOTAL_SIZE] < $torrentNotInList[Torrent\Get::TOTAL_SIZE] ?
            $torrentInList : $torrentNotInList;
    }
}
