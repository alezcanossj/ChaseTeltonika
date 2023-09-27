<?php
namespace lbarrous\TeltonikaDecoder\Database;

class Car
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function checkIfCarExists($imei)
    {
        return $this->database->has("cars", ["device" => $imei]);
    }

    public function createCar($imei, $brand, $model, $plate, $location)
    {
        $this->database->insert("cars", [
            "brand" => $brand,
            "model" => $model,
            "plate" => $plate,
            "location" => $location,
            "device" => $imei
        ]);
    }
}