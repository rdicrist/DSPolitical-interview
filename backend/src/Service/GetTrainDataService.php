<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use App\Entity\TrainDataEntity;

class GetTrainDataService
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    /**
     * Fetch train data from an external API using the provided station.
     * Returns an array of TrainDataEntity on success, a raw string for non-JSON
     * responses, or an array with an 'error' key on failure.
     *
     * @param string $station
     * @return array|string|null  (array of TrainDataEntity objects on success)
     */
    public function fetchTrainDataByStation(string $station): array|string|null
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
            if ($status >= 400) {
                return ['error' => "External API returned HTTP $status"];
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $data = $response->getContent(false);
                return $this->mapToEntities($data);
            }

            return $response->getContent(true);
        } catch (TransportExceptionInterface $e) {
            return ['error' => 'Transport error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
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
        $items = [];
        $trainData = json_decode($data, true);

        foreach ($trainData['Trains'] as $item) {
            $entity = new TrainDataEntity();
            $entity->cars = $item['Car'];
            $entity->destination = $item['DestinationName'];
            $entity->min = $item['Min'];

            $items[] = $entity;
        }

        return $items;
    }

    private function validateTrainData($data)
    {
        // Validation logic here
        return true;
    }
}
