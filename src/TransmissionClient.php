<?php

namespace Popstas\Transmission\Console;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Session;
use Martial\Transmission\API\Argument\Torrent;
use Martial\Transmission\API\TorrentIdList;
use Martial\Transmission\API\DuplicateTorrentException;

class TransmissionClient
{
    private $api;
    private $sessionId;

    public function __construct(API\TransmissionAPI $api)
    {
        $this->api = $api;
    }

    private function getSessionId()
    {
        try {
            $this->api->sessionGet($this->sessionId);
        } catch (API\CSRFException $e) {
            // The session has been reinitialized. Fetch the new session ID with the method getSessionId().
            $this->sessionId = $e->getSessionId();
        }
        return $this->sessionId;
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

        $this->getSessionId();

        $torrentList = $this->api->torrentGet($this->sessionId, new TorrentIdList($cleanedIds), $fields);

        return $torrentList;
    }

    public function addTorrent($torrentFile, $downloadDir = null)
    {
        $arguments = [];
        if (is_file($torrentFile)) {
            $arguments[Torrent\Add::METAINFO] = base64_encode(file_get_contents($torrentFile));
        } else {
            $arguments[Torrent\Add::FILENAME] = $torrentFile;
        }

        if (!is_null($downloadDir)) {
            $arguments[API\Argument\Session\Accessor::DOWNLOAD_DIR] = $downloadDir;
        }

        $this->getSessionId();

        try {
            $response = $this->api->torrentAdd($this->sessionId, $arguments);
        } catch (DuplicateTorrentException $e) {
            return [
                'id'         => $e->getTorrentId(),
                'name'       => $e->getTorrentName(),
                'hashString' => $e->getTorrentHashString(),
                'duplicate'  => true
            ];
        }
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

        $this->getSessionId();
        $this->api->torrentRemove($this->sessionId, new TorrentIdList($torrentIds), $deleteLocalData);

        return true;
    }

    public function waitForTransmission($int)
    {
        sleep($int);
        $this->getSessionId();
    }

    /**
     * @return string
     */
    public function getDownloadDir()
    {
        $this->getSessionId();
        $session = $this->api->sessionGet($this->sessionId);
        return $session[Session\Get::DOWNLOAD_DIR];
    }

    public function getFreeSpace($downloadDir = null)
    {
        if (is_null($downloadDir)) {
            $downloadDir = $this->getDownloadDir();
        }
        $this->getSessionId();
        $freeSpace = $this->api->freeSpace($this->sessionId, $downloadDir);
        return $freeSpace['size-bytes'];
    }
}
