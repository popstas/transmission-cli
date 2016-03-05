<?php

namespace Popstas\Transmission\Console\Helpers;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class TableUtils
{
    public static function parseFilters(array $filters)
    {
        foreach ($filters as $columnKey => $filter) {
            if (is_array($filter) && !isset($filter['type'])) {
                throw new \InvalidArgumentException('Unknown filter type');
            }
            if (!isset($filter) || !isset($filter['value'])) {
                unset($filters[$columnKey]);
                continue;
            }
            $filters = self::parseFilter($filter['type'], $filter['value']) + $filters[$columnKey];
        }
        return $filters;
    }

    private static function parseFilter($type, $filterString)
    {
        switch ($type) {
            case 'numeric':
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

            case 'regex':
                return ['regex' => str_replace(['/', '.', '*'], ['\/', '\.', '.*?'], $filterString)];

            default:
                throw new \InvalidArgumentException('Unknown filter type');
        }
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

                if (!self::filterRow($columnValue, $filter)) {
                    return false;
                }
            }
            return true;
        });
    }

    private static function filterRow($value, $filter)
    {
        if ($filter['type'] == 'numeric') {
            if ((isset($filter['min']) && $value <= $filter['min']) ||
                (isset($filter['max']) && $value >= $filter['max'])
            ) {
                return false;
            }
        }

        if ($filter['type'] == 'regex') {
            if (!preg_match('/' . $filter['regex'] . '/i', $value)) {
                return false;
            }
        }

        return true;
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
}