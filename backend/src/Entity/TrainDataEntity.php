<?php

namespace App\Entity;

final class TrainDataEntity
{
    // Note: The API returns fields as empty strings instead of null, so we need to account for that in our code.
	private string $Cars;
	private string $Destination;
	private string $Min;
}
