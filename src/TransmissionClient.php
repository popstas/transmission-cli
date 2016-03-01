<?php

namespace Popstas\Transmission\Console;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class TransmissionClient
{
    private $api;
    private $sessionId;

    public function __construct(API\TransmissionAPI $api)
    {
        $this->api = $api;
    }

    public function createSession()
    {
        $this->sessionId = $this->getSessionId($this->sessionId);
    }

    private function getSessionId($sessionId)
    {
        try {
            $this->api->sessionGet($sessionId);
        } catch (API\CSRFException $e) {
            // The session has been reinitialized. Fetch the new session ID with the method getSessionId().
            $sessionId = $e->getSessionId();
        }

        return $sessionId;
    }

    public function getTorrentData(array $ids = [], $fields = [])
    {
        if (empty($fields)) {
            $fields = [
                Torrent\Get::ID,
                Torrent\Get::NAME,
                Torrent\Get::TOTAL_SIZE,
                Torrent\Get::DOWNLOAD_DIR,
                Torrent\Get::UPLOAD_EVER,
                Torrent\Get::DOWNLOAD_EVER,
                Torrent\Get::DONE_DATE,
                Torrent\Get::ADDED_DATE,
            ];
        }

        $this->createSession();
        $torrentList = $this->api->torrentGet($this->sessionId, $ids, $fields);
        return $torrentList;
    }

    public function getTorrentsSize(array $torrentList, $fieldName = Torrent\Get::TOTAL_SIZE)
    {
        $torrentSize = 0;
        foreach ($torrentList as $torrent) {
            $torrentSize += $torrent[$fieldName];
        }
        return $torrentSize;
    }

    public function getTorrentsField(array $torrentList, $fieldName)
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
    public function filterTorrents(array $torrentList, array $filters)
    {
        $filters += ['age_min' => 0, 'age_max' => 99999, 'name' => ''];
        $filters['name'] = str_replace(['/', '.', '*'], ['\/', '\.', '.*?'], $filters['name']);

        if (isset($filters['age'])) {
            $filters = $this->parseAgeFilter($filters['age']) + $filters;
        }

        return array_filter($torrentList, function ($torrent) use ($filters) {
            $age = $this->getTorrentAgeInDays($torrent);
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

    private function parseAgeFilter($age)
    {
        $filters = [];
        preg_match_all('/([<>])\s?(\d+)/', $age, $results, PREG_SET_ORDER);
        if (count($results)) {
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
     * @return int days from torrent finish download
     */
    public function getTorrentAgeInDays($torrent)
    {
        $date = isset($torrent[Torrent\Get::DONE_DATE]) && $torrent[Torrent\Get::DONE_DATE] ?
            $torrent[Torrent\Get::DONE_DATE] :
            (isset($torrent[Torrent\Get::ADDED_DATE]) ? $torrent[Torrent\Get::ADDED_DATE] : 0);
        return $date ? round((time() - $date) / 86400) : 0;
    }

    public function printTorrentsTable(array $torrentList, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['Name', 'Id', 'Size']);

        foreach ($torrentList as $torrent) {
            $table->addRow([
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                round($torrent[Torrent\Get::TOTAL_SIZE] / 1024 / 1000 / 1000, 2),
            ]);
        }

        $table->render();
    }

    public function getObsoleteTorrents()
    {
        $torrentList = $this->getTorrentData();
        $all = [];
        $obsolete = [];

        foreach ($torrentList as $torrent) {
            $name = $torrent[Torrent\Get::NAME];
            if (!isset($all[$name])) {
                $all[$name] = $torrent;
                continue;
            }

            $obs = $this->detectObsoleteTorrent($all[$name], $torrent);
            if ($obs) {
                $obsolete[] = $obs;
                if ($obs[Torrent\Get::ID] !== $torrent[Torrent\Get::ID]) {
                    $all[$name] = $torrent;
                }
            }
        }

        return $obsolete;
    }

    /**
     * @param array $torrentList Array of Torrent data or torrent_ids
     * @param bool $deleteLocalData
     * @return bool
     */
    public function removeTorrents(array $torrentList, $deleteLocalData = false)
    {
        if (empty($torrentList)) {
            return false;
        }

        $torrentIds = [];

        foreach ($torrentList as $torrent) {
            $torrentIds[] = $torrent[Torrent\Get::ID];
        }

        $this->createSession();
        $this->api->torrentRemove($this->sessionId, $torrentIds, $deleteLocalData);

        return true;
    }

    private function detectObsoleteTorrent($torrentInList, $torrentNotInList)
    {
        if ($torrentInList[Torrent\Get::DOWNLOAD_DIR] !== $torrentNotInList[Torrent\Get::DOWNLOAD_DIR]) {
            return false;
        }

        return $torrentInList[Torrent\Get::TOTAL_SIZE] < $torrentNotInList[Torrent\Get::TOTAL_SIZE] ?
            $torrentInList : $torrentNotInList;
    }
}
