<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use App\Entity\TrainDataEntity;

class GetTrainDataService
{
    /**
     * Constructor for GetTrainDataService.
     * 
     * @param HttpClientInterface $client The HTTP client to use for making API requests.
     * The client should be configured with the base URL and API key for the external train data API.
     * @return void
     */
    public function __construct(private HttpClientInterface $client)
    {
    }

    /**
     * Fetch train data from an external API using the provided station.
     * Returns an array of TrainDataEntity on success or an array with an 'error' key on failure.
     *
     * @param string $station
     * @return array (array of TrainDataEntity objects on success, or ['Error' => 'error message'] on failure)
     */
    public function fetchTrainDataByStation(string $station): array
    {
        $apiUrl = $_ENV['TRAIN_ARRIVAL_URL'] ?? 'http://api.wmata.com/StationPrediction.svc/json/GetPrediction/{StationCodes}';

        // put station into path if placeholder present
        $apiUrl = str_replace('{StationCodes}', $station, $apiUrl);

        try {
            $response = $this->client->request('GET', $apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'api_key' => $_ENV['WMATA_API_KEY'] ?? '',
                ],
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            if ($status !== 200) {
                $error = "External API returned HTTP $status for station $station, error:{{$response->getContent(false)}}";
                $this->logError($error);
                return ['Error' => "{{$error}}"];
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            if (!str_contains($contentType, 'application/json')) {
                $error = "External API returned HTTP $status for station $station: Invalid format error - expected JSON but got '$contentType'";
                $this->logError($error);
                return ['Error' => "{{$error}}"];
            }
            // If we got here, we have a successful JSON response, so we can attempt to parse and map it to our entity
            $data = $response->getContent(false);
            return $this->mapToEntities($data);
        } catch (\Throwable $e) {
            return ['Error' => $e->getMessage()];
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
        if ($trainDataEntity->destination == '' || is_null($trainDataEntity->destination)) {
            $trainDataEntity->destination = 'Unknown';
        }
        
        if ($trainDataEntity->cars == '' || is_null($trainDataEntity->cars) || $trainDataEntity->cars == '-') {
            $trainDataEntity->cars = 'Unknown';
        }

        if ($trainDataEntity->min == '' || is_null($trainDataEntity->min)) {
            $trainDataEntity->min = 'Unknown';
        }
        else if ($trainDataEntity->min == 'BRD') {
            $trainDataEntity->min = 'Boarding';
        }
        else if ($trainDataEntity->min == 'ARR' || $trainDataEntity->min == '0') {
            $trainDataEntity->min = 'Arriving';
        }
        else if ($trainDataEntity->min == '1') {
            $trainDataEntity->min = '1 minute';
        }
        else {
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
