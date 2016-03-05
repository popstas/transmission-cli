<?php

namespace Popstas\Transmission\Console;

use GuzzleHttp\Exception\ConnectException;
use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
use Psr\Log\LoggerInterface;

class InfluxDbClient
{
    /**
     * @var InfluxDB\Client $influxDb
     */
    private $influxDb;

    /**
     * @var InfluxDB\Database
     */
    private $database;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $databaseName;

    public function __construct(InfluxDB\Client $influxDb, $databaseName)
    {

        $this->influxDb = $influxDb;
        $this->databaseName = $databaseName;
    }

    /**
     * @return InfluxDB\Database $database
     */
    private function getDatabase()
    {
        if (!isset($this->database)) {
            $this->database = $this->connectDatabase();
        }
        return $this->database;
    }

    /**
     * @param InfluxDB\Database $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Injects a logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    private function log($level, $message, $context = [])
    {
        if (!is_null($this->logger)) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * @return InfluxDB\Database
     * @throws InfluxDB\Database\Exception
     */
    public function connectDatabase()
    {
        if (isset($this->database)) {
            $this->database;
        }

        $database = $this->influxDb->selectDB($this->databaseName);

        try {
            $databaseExists = $database->exists();
        } catch (ConnectException $e) {
            throw new \RuntimeException('InfluxDb connection error: ' . $e->getMessage());
        }
        if (!$databaseExists) {
            $this->log('info', 'Database ' . $this->databaseName . ' not exists, creating');
            $database->create();
        }

        return $database;
    }

    /**
     * @param array $torrent
     * @param string $transmissionHost
     * @return InfluxDB\Point
     */
    public function buildPoint(array $torrent, $transmissionHost)
    {
        $age = TorrentUtils::getTorrentAge($torrent);
        $lastPoint = $this->getLastPoint($torrent, $transmissionHost);

        $tagsData = [
            'host'             => $transmissionHost,
            'torrent_name'     => $torrent[Torrent\Get::NAME],
        ];

        $uploadedDerivative = count($lastPoint) && $torrent[Torrent\Get::UPLOAD_EVER] - $lastPoint['last'] >= 0 ?
            $torrent[Torrent\Get::UPLOAD_EVER] - $lastPoint['last'] : $torrent[Torrent\Get::UPLOAD_EVER];

        $fieldsData = [
            'uploaded_last'       => $uploadedDerivative,
            'downloaded'          => $torrent[Torrent\Get::TOTAL_SIZE],
            'age'                 => $age,
            'uploaded_per_day'    => $age ? intval($torrent[Torrent\Get::UPLOAD_EVER] / $age * 86400) : 0,
        ];

        return new InfluxDB\Point(
            'uploaded',
            $torrent[Torrent\Get::UPLOAD_EVER],
            $tagsData,
            $fieldsData,
            time()
        );
    }

    public function getLastPoint(array $torrent, $transmissionHost)
    {
        $torrentName = $torrent[Torrent\Get::NAME];
        $queryBuilder = $this->getDatabase()->getQueryBuilder();
        $results = $queryBuilder
            ->last('value')
            ->from('uploaded')
            ->where([
                "host='$transmissionHost'",
                "torrent_name='$torrentName'"
            ])
            ->getResultSet()
            ->getPoints();

        return count($results) ? $results[0] : [];
    }
    
    public function writePoints($points, $precision = InfluxDB\Database::PRECISION_SECONDS)
    {
        foreach ($points as $point) {
            $this->log('debug', 'Send point: {point}', ['point' => $point]);
        }
        return $this->getDatabase()->writePoints($points, $precision);
    }

    public function sendTorrentPoints(array $torrentList, $transmissionHost)
    {
        $points = [];
        foreach ($torrentList as $torrent) {
            $points[] = $this->buildPoint($torrent, $transmissionHost);
        }
        $isSuccess = $this->writePoints($points);
        $this->log('info', 'InfluxDB write ' . ($isSuccess ? 'success' : 'failed'));
        return $isSuccess;
    }

    /**
     * @param array $torrent
     * @param string $fieldName
     * @param string $transmissionHost
     * @param int $lastDays
     * @return int
     */
    public function getTorrentSum(array $torrent, $fieldName, $transmissionHost = '', $lastDays = 0)
    {
        $where = [];

        if (isset($torrent[Torrent\Get::NAME])) {
            $where[] = "torrent_name = '" . $torrent[Torrent\Get::NAME] . "'";
        }

        if ($transmissionHost) {
            $where[] = "host = '" . $transmissionHost . "'";
        }

        if ($lastDays) {
            $fromTimestamp = strtotime('-' . $lastDays . ' days');
            $fromDate = date('c', $fromTimestamp);
            $where[] = "time >= '$fromDate'";
        }

        $results = $this->getDatabase()->getQueryBuilder()
            ->from('uploaded')
            ->select("sum($fieldName) as $fieldName")
            ->where($where)
            ->getResultSet()
            ->getPoints();
        ;

        $this->log('debug', $this->influxDb->getLastQuery());

        if (!empty($results)) {
            return $results[0][$fieldName];
        }
        return 0;
    }
}
