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

    /**
     * @throws InfluxDB\Database\Exception
     */
    public function connectDatabase()
    {
        if (isset($this->database)) {
            return true;
        }

        $database = $this->influxDb->selectDB($this->databaseName);

        try {
            $databaseExists = $database->exists();
        } catch (ConnectException $e) {
            throw new \RuntimeException('InfluxDb connection error: ' . $e->getMessage());
        }
        if (!$databaseExists) {
            $this->logger->info('Database ' . $this->databaseName . ' not exists, creating');
            $database->create();
        }

        $this->database = $database;
        return true;
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
        $this->connectDatabase();

        $torrentName = $torrent[Torrent\Get::NAME];
        $queryBuilder = $this->database->getQueryBuilder();
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
        $this->connectDatabase();
        return $this->database->writePoints($points, $precision);
    }
}
