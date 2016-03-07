<?php

namespace Popstas\Transmission\Console;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Torrent;

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

        $cleanedIds = array_map(function ($torrentId) {
            return (int)$torrentId;
        }, $ids);

        $this->createSession();
        $torrentList = $this->api->torrentGet($this->sessionId, $cleanedIds, $fields);

        return $torrentList;
    }

    public function addTorrent($torrentFile, $downloadDir = null)
    {
        // remove error suppress after https://github.com/MartialGeek/transmission-api/issues/6 closed
        $errorLevel = ini_get('error_reporting');
        error_reporting(E_ALL & ~ E_NOTICE & ~ E_STRICT & ~ E_DEPRECATED);

        $arguments = [];
        if (is_file($torrentFile)) {
            $arguments[Torrent\Add::METAINFO] = base64_encode(file_get_contents($torrentFile));
        } else {
            $arguments[Torrent\Add::FILENAME] = $torrentFile;
        }

        if (!is_null($downloadDir)) {
            $arguments[API\Argument\Session\Accessor::DOWNLOAD_DIR] = $downloadDir;
        }

        $this->createSession();

        $response = $this->api->torrentAdd($this->sessionId, $arguments);
        error_reporting($errorLevel);
        return $response;
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

    public function waitForTransmission($int)
    {
        sleep($int);
        $this->createSession();
    }
}
