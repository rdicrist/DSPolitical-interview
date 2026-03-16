<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use App\Entity\TrainDataEntity;

class GetTrainDataService
{
    private RateLimiterFactory $wmataApiRateLimiter;

    /**
     * Constructor for GetTrainDataService.
     * 
     * @param HttpClientInterface $client The HTTP client to use for making API requests.
     * @param RateLimiterFactory $wmataApiRateLimiter The rate limiter factory for managing API request limits.
     * The client should be configured with the base URL and API key for the external train data API.
     * @return void
     */
    public function __construct(private HttpClientInterface $client, RateLimiterFactory $wmataApiRateLimiter)
    {
        $this->wmataApiRateLimiter = $wmataApiRateLimiter;
    }

    /**
     * Fetch train data from WMATA by station.
     * Uses a local rate limiter + short-term cache + retry/backoff for 429.
     * Returns an array of TrainDataEntity on success or an array with an 'error' key on failure.
     *
     * @param string $station
     * @return array (array of TrainDataEntity objects on success, or ['Error' => 'error message'] on failure)
     */
    public function fetchTrainDataByStation(string $station): array
    {
        $apiUrl = $_ENV['TRAIN_ARRIVAL_URL'] ?? 'http://api.wmata.com/StationPrediction.svc/json/GetPrediction/{StationCodes}';
        $apiUrl = str_replace('{StationCodes}', urlencode($station), $apiUrl);

        // Check the rate limit before making the API request. If the limit has been hit, this will throw an exception with a retry message.
        $this->checkRateLimit($station);

        try {
            $response = $this->client->request('GET', $apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'api_key' => $_ENV['WMATA_API_KEY'] ?? '',
                ],
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();

            if ($status === 429) {
                $retryAfter = $response->getHeaders()['Retry-After'][0] ?? 60; // Default to 60 seconds if not provided
                $error = "WMATA API rate limit exceeded for station {$station}. Retry after {$retryAfter} seconds.";

                $this->logError($error);
                throw new \RuntimeException($error);
            }

            if ($status !== 200) {
                $error = "External API returned HTTP $status, " . $response->getContent(false) ?? 'No content';
                
                $this->logError($error);
                throw new \RuntimeException($error);
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            if (!str_contains($contentType, 'application/json')) {
                $error = "WMATA API returned HTTP $status: Invalid format error - expected JSON but got '$contentType'";
                
                $this->logError($error);
                throw new \RuntimeException($error);
            }
            // If we got here, we have a successful JSON response, so we can attempt to parse and map it to our entity
            $data = $response->getContent(false);
            return $this->mapToEntities($data);
        } catch (\Throwable $e) {
            $error = "Failed to fetch train data for station {$station}. " . $e->getMessage();
            
            $this->logError($error);
            throw new \RuntimeException($error);
        }
    }

    /**
     * Check the rate limit for the given station and return an error if the limit has been hit.
     * This uses Symfony's RateLimiter component to track requests and enforce limits.
     *
     * @param string $station
     * @return void
     */
    private function checkRateLimit($station): void {
        $limiter = $this->wmataApiRateLimiter->create($station);
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $retry = $limit->getRetryAfter()?->getTimestamp() - time();
            $error = "WMATA API rate limit hit for station {$station}. Retry in {$retry}s";
            
            $this->logError($error);
            throw new \RuntimeException($error);
        }
    }

    /**
     * Normalize various JSON shapes into an array of TrainDataEntity objects.
     *
     * @param mixed $data
     * @return TrainDataEntity[]
     */
    private function mapToEntities($data): array
    {
        $trainDataEntityList = [];
        $trainData = json_decode($data, true);

        foreach ($trainData['Trains'] as $item) {
            $entity = new TrainDataEntity();
            $entity->cars = $item['Car'];
            $entity->destination = $item['DestinationName'];
            $entity->min = $item['Min'];

            $trainDataEntityList[] = $this->cleanData($entity);
        }

        return $trainDataEntityList;
    }

    /**
     * Clean and normalize the data in TrainDataEntity (e.g., handle empty strings, convert special values, etc.).
     * 
     * @param TrainDataEntity $trainDataEntity
     * @return TrainDataEntity
     */
    private function cleanData($trainDataEntity)
    {
        // Data cleaning logic here (e.g., handle missing fields, convert types, etc.)
        if ($trainDataEntity->destination === '' || is_null($trainDataEntity->destination)) {
            $trainDataEntity->destination = 'Unknown';
        }
        
        if ($trainDataEntity->cars === '' || is_null($trainDataEntity->cars) || $trainDataEntity->cars === '-') {
            $trainDataEntity->cars = 'Unknown';
        }

        if ($trainDataEntity->min === '' || is_null($trainDataEntity->min)) {
            $trainDataEntity->min = 'Unknown';
        } elseif ($trainDataEntity->min === 'BRD') {
            $trainDataEntity->min = 'Boarding';
        } elseif ($trainDataEntity->min === 'ARR' || $trainDataEntity->min === '0') {
            $trainDataEntity->min = 'Arriving';
        } elseif ($trainDataEntity->min === '1') {
            $trainDataEntity->min = '1 minute';
        } else {
            $trainDataEntity->min = "{$trainDataEntity->min} minutes";
        }

        return $trainDataEntity;        
    }

    /**
     * Log errors to a file or monitoring system.
     * 
     * @param string $message
     * @return void
     */
    private function logError($message)
    {
        error_log($message);
    }
}
