<?php

namespace App\Controller;

use App\Service\GetTrainDataService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class GetTrainDataController
{
    #[Route('/train-data/{station}')]
    public function getTrainData(string $station, GetTrainDataService $service): Response
    {
        try {
            $data = $service->fetchTrainDataByStation($station);
            return new Response(json_encode($data), 200, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return new Response(json_encode(['Error' => $e->getMessage()]), 500, ['Content-Type' => 'application/json']);
        }
    }
}