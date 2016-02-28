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

    public function getTorrentData(array $ids = [])
    {
        $this->createSession();
        $torrentList = $this->api->torrentGet($this->sessionId, $ids, [
            API\Argument\Torrent\Get::ID,
            API\Argument\Torrent\Get::NAME,
            API\Argument\Torrent\Get::TOTAL_SIZE,
            API\Argument\Torrent\Get::DOWNLOAD_DIR,
            API\Argument\Torrent\Get::UPLOAD_EVER,
        ]);
        return $torrentList;
    }

    public function getTorrentsSize(array $torrentList)
    {
        $torrentSize = 0;
        foreach ($torrentList as $torrent) {
            $torrentSize += $torrent[Torrent\Get::TOTAL_SIZE];
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
            $torrentInList :
            $torrentNotInList;
    }
}
