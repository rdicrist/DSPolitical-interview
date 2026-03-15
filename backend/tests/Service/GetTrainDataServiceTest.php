<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\GetTrainDataService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GetTrainDataServiceTest extends TestCase
{
    public function testFetchBySelectionReturnsArrayOnJson()
    {
        $client = $this->createMock(HttpClientInterface::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn(['content-type' => ['application/json']]);
        $response->method('toArray')->with(false)->willReturn(['result' => 'ok']);

        $client->method('request')->willReturn($response);

        $service = new GetTrainDataService($client);
        $res = $service->fetchBySelection('one');

        $this->assertIsArray($res);
        $this->assertEquals(['result' => 'ok'], $res);
    }
}
