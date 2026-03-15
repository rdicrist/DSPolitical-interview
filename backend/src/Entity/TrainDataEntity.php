<?php

namespace App\Entity;

final class TrainDataEntity
{
	private ?int $Cars;
	private string $Destination;
	private ?int $Min;

	// public function __construct(string $destination, ?int $min = null, ?int $cars = null)
	// {
	// 	$this->Destination = $destination;
	// 	$this->Min = $min;
	// 	$this->Cars = $cars;
	// }

	// public function getCars(): ?int
	// {
	// 	return $this->Cars;
	// }

	// public function getDestination(): string
	// {
	// 	return $this->Destination;
	// }

	// public function getMin(): ?int
	// {
	// 	return $this->Min;
	// }

	// public static function fromArray(array $data): self
	// {
	// 	// Accepts various key casings
	// 	$destination = $data['Destination'] ?? $data['destination'] ?? ($data['DestinationName'] ?? '');
	// 	$minRaw = $data['Min'] ?? $data['min'] ?? $data['Minutes'] ?? null;
	// 	$carsRaw = $data['Cars'] ?? $data['cars'] ?? null;

	// 	$min = is_null($minRaw) ? null : (is_numeric($minRaw) ? (int)$minRaw : null);
	// 	$cars = is_null($carsRaw) ? null : (is_numeric($carsRaw) ? (int)$carsRaw : null);

	// 	return new self((string)$destination, $min, $cars);
	// }

	// public function toArray(): array
	// {
	// 	return [
	// 		'Cars' => $this->Cars,
	// 		'Destination' => $this->Destination,
	// 		'Min' => $this->Min,
	// 	];
	// }
}
