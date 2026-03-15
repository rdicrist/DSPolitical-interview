<?php

namespace App\Entity;

final class TrainDataEntity
{
    // Note: The API returns fields as empty strings instead of null, so we need to account for that in our code.
    // Note: The fields are private because they are only meant to be accessed and modified through the service layer, which will handle any necessary data cleaning and normalization.
    // Note: Getters and setters are not included here becuase they are not currently needed for the functionality, but they can be added if needed for future use cases
	private string $Cars;
	private string $Destination;
	private string $Min;
}
