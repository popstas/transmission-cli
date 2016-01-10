<?php

namespace Popstas\Transmission\Console;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Torrent;
use GuzzleHttp;

class TransmissionClient {
    private $api;
    private $sessionId;

    public function __construct($host = 'localhost', $port = 9091, $username = '', $password = '') {
        $httpClient = new GuzzleHttp\Client(['base_uri' => 'http://' . $host . ':' . $port . '/transmission/rpc']);
        $this->api = new API\RpcClient($httpClient, $username, $password);

        $this->sessionId = $this->getSessionId($this->sessionId);
    }

    private function getSessionId($sessionId) {
        try {
            $this->api->sessionGet($sessionId);
        } catch (API\CSRFException $e) {
            // The session has been reinitialized. Fetch the new session ID with the method getSessionId().
            $sessionId = $e->getSessionId();
        } catch (API\TransmissionException $e) {
            // The API returned an error, retrieve the reason with the method getResult().
            die('API error: ' . $e->getResult());
        }

        return $sessionId;
    }

    public function getTorrentData(array $ids=[]) {
        $torrentList = $this->api->torrentGet($this->sessionId, $ids, [
            API\Argument\Torrent\Get::ID,
            API\Argument\Torrent\Get::NAME,
            API\Argument\Torrent\Get::TOTAL_SIZE,
            API\Argument\Torrent\Get::DOWNLOAD_DIR,
            API\Argument\Torrent\Get::UPLOAD_EVER,
        ]);
        return $torrentList;
    }

    function getTorrentsSize(array $torrentList){
        $total_size = 0;
        foreach ($torrentList as $torrentData) {
            $total_size += $torrentData[Torrent\Get::TOTAL_SIZE];
        }
        return $total_size;
    }

    public function printTorrentsTable(array $torrentList, OutputInterface $output){
        $table = new Table($output);
        $table->setHeaders(['Name', 'Id', 'Size']);

        foreach($torrentList as $torrent){
            $table->addRow([
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                round($torrent[Torrent\Get::TOTAL_SIZE] / 1024 / 1000 / 1000, 2)
            ]);
        }

        $table->render();
    }

    public function getObsoleteTorrents(){
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
                if($obs[Torrent\Get::ID] !== $torrent[Torrent\Get::ID]){
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
    public function removeTorrents(array $torrentList, $deleteLocalData=false) {
        if(empty($torrentList)){
            return false;
        }

        $torrent_ids = [];

        foreach($torrentList as $torrent){
            $torrent_ids[] = $torrent[Torrent\Get::ID];
        }

        $this->api->torrentRemove($this->sessionId, $torrent_ids, $deleteLocalData);

        return true;
    }

    private function detectObsoleteTorrent($a, $b){
        if($a[Torrent\Get::DOWNLOAD_DIR] !== $b[Torrent\Get::DOWNLOAD_DIR]){
            return false;
        }

        return $a[Torrent\Get::TOTAL_SIZE] < $b[Torrent\Get::TOTAL_SIZE] ? $a : $b;
    }
}
