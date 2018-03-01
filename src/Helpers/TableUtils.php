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
            $filters[$columnKey] = self::parseFilter($filter['type'], $filter['value']) + $filters[$columnKey];
        }
        return $filters;
    }

    private static function parseFilter($type, $filterString)
    {
        switch ($type) {
            case 'numeric':
                $filter = [];
                preg_match_all('/([<>=])\s?([\d\.]+)/', $filterString, $results, PREG_SET_ORDER);
                if ($results) {
                    foreach ($results as $result) {
                        $operator = $result[1];
                        $value = $result[2];
                        $operatorMap = [
                            '<' => 'max',
                            '>' => 'min',
                            '=' => 'equals',
                        ];
                        $filterCondition = $operatorMap[$operator];
                        $filter[$filterCondition] = $value;
                    }
                }
                return $filter;

            case 'regex':
                return ['regex' => str_replace(['/', '.', '*'], ['\/', '\.', '.*?'], $filterString)];
        }
        throw new \InvalidArgumentException('Unknown filter type');
    }

    /**
     * @param array $rows
     * @param array $filters
     * @return bool
     * @throws \RuntimeException
     */
    public static function filterRows(array $rows, array $filters)
    {
        $filters = self::parseFilters($filters);

        return array_filter($rows, function ($row) use ($filters) {
            foreach ($filters as $columnKey => $filter) {
                if (!isset($row[$columnKey])) {
                    throw new \RuntimeException('Column ' . $columnKey . ' not exists, cannot filter');
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
        if ((isset($filter['min']) && $value <= $filter['min']) ||
            (isset($filter['max']) && $value >= $filter['max']) ||
            (isset($filter['equals']) && $value != $filter['equals']) ||
            (isset($filter['regex']) && !preg_match('/' . $filter['regex'] . '/i', $value))
        ) {
            return false;
        }

        return true;
    }

    public static function sortRowsByColumnNumber(array $rows, $columnNumber)
    {
        if (count($rows) == 0) {
            return $rows;
        }
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

    public static function limitRows(array $rows, $limit)
    {
        if ($limit && $limit < count($rows)) {
            $rows = array_slice($rows, 0, $limit);
        }
        return $rows;
    }

    public static function printTable(array $tableData, OutputInterface $output)
    {
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
