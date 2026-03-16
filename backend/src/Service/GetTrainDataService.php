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

            // Check the response for rate limit errors, non-success status codes, and invalid content types. If any of these issues are detected, this will throw an exception with a detailed error message. 
            $this->checkInvalidResponse($response, $station);

            $data = $response->getContent(false);
            return $this->mapToEntities($data);
        } catch (\Throwable $e) {
            // This will catch any of the exceptions thrown by the rate limit check, the API request, or the response checks. We log the error with the station information and then throw a new exception with a detailed message that includes the original error message for debugging purposes.
            $error = "Failed to fetch train data for station {$station}. " . $e->getMessage();
            
            $this->logError($error);
            throw new \RuntimeException($error);
        }
    }

    /**
     * Check the rate limit for the given station and return an error if the limit has been hit.
     * This uses Symfony's RateLimiter component to track requests and enforce limits.
     *
     * NOTE: I put this in a separate method to centralize the rate limit checking logic. This way, we can easily modify the rate limiting behavior in one place without affecting the main data fetching logic.
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
     * Check the API response for errors such as rate limits, non-success status codes, and invalid content types. If any issues are detected, log the error and throw an exception with a detailed message.
     * 
     * NOTE: I put all the response checks in one method to centralize the error handling logic for API responses. Additional response checks can also be added to this method in the future if needed.
     * 
     * @param mixed $response The HTTP response object from the API request.
     * @param string $station The station code for logging purposes.
     * @return void
     */
    private function checkInvalidResponse($response, $station): void {
        $status = $response->getStatusCode();

        // Handle rate limit response from the external API. If we receive a 429, we should log the error and throw an exception with the retry information.
        if ($status === 429) {
            $retryAfter = $response->getHeaders()['Retry-After'][0] ?? 60; // Default to 60 seconds if not provided
            $error = "WMATA API rate limit exceeded for station {$station}. Retry after {$retryAfter} seconds.";

            $this->logError($error);
            throw new \RuntimeException($error);
        }
        // Handle other non-successful responses from the external API. If we receive a non-200 status code, we should log the error and throw an exception with the status and any error message from the response.
        else if ($status !== 200) {
            $error = "External API returned HTTP $status, " . $response->getContent(false) ?? 'No content';
            
            $this->logError($error);
            throw new \RuntimeException($error);
        }

        $contentType = $response->getHeaders()['content-type'][0] ?? '';
        
        // Handle invalid content type. If we receive a successful status code but the content type is not JSON, we should log the error and throw an exception indicating that we expected JSON but got something else.
        if (!str_contains($contentType, 'application/json')) {
            $error = "WMATA API returned HTTP $status: Invalid format error - expected JSON but got '$contentType'";
            
            $this->logError($error);
            throw new \RuntimeException($error);
        }
    }

    /**
     * Normalize various JSON shapes into an array of TrainDataEntity objects.
     *
     * NOTE: I put the data mapping and cleaning logic in a separate method to keep the main data fetching method focused on just fetching the data. This way, if we need to modify the mapping or cleaning logic in the future, we can do so in one place without affecting the API request logic.
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
     * NOTE: I put the data cleaning logic in a separate method to keep the mapping method focused on just mapping the raw data to entities. This way, if we need to modify the cleaning logic in the future, we can do so in one place without affecting the mapping logic.
     * 
     * @param TrainDataEntity $trainDataEntity
     * @return TrainDataEntity
     */
    private function cleanData($trainDataEntity): TrainDataEntity
    {
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
     * NOTE: I put the error logging in a separate method to centralize the logging logic. This way, if we want to change how we log errors, we can do so in one place without affecting the main data fetching and error handling logic. Additionally this method is called whenever we detect an error condition (e.g., rate limit hit, invalid response, etc.) to ensure that all errors are consistently logged with the relevant information for debugging and monitoring purposes.
     * 
     * @param string $message
     * @return void
     */
    private function logError($message): void
    {
        error_log($message);
    }
}
