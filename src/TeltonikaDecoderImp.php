<?php
/**
 * Created by PhpStorm.
 * User: Alvaro
 * Date: 12/07/2018
 * Time: 19:47
 */

namespace lbarrous\TeltonikaDecoder;

use lbarrous\TeltonikaDecoder\Entities\AVLData;
use lbarrous\TeltonikaDecoder\Entities\GPSData;
use lbarrous\TeltonikaDecoder\Entities\IOData;
use lbarrous\TeltonikaDecoder\Entities\ImeiNumber;

class TeltonikaDecoderImp implements TeltonikaDecoder
{

    const HEX_DATA_LENGHT = 130;
    const HEX_DATA_HEADER = 20;

    const CODEC8 = 8;
    const CODEC7 = 7;
    const CODEC16 = 16;

    const TIMESTAMP_HEX_LENGTH = 16;
    const PRIORITY_HEX_LENGTH = 2;
    const LONGITUDE_HEX_LENGTH = 8;
    const LATITUDE_HEX_LENGTH = 8;
    const ALTITUDE_HEX_LENGTH = 4;
    const ANGLE_HEX_LENGTH = 4;
    const SATELLITES_HEX_LENGTH = 2;
    const SPEED_HEX_LENGTH = 4;

    const EVENTID_HEX_LENGTH = 2;
    const ELEMENTCOUNT_HEX_LENGTH = 2;
    const ID_HEX_LENGTH = 2;
    const VALUE_HEX_LENGTH = 2;
    const ELEMENT_COUNT_1B_HEX_LENGTH = 2;
    private $imei;
    private $endPosition = 0;
    private $dataFromDevice;
    private $AVLData;

    /**
     * TeltonikaDecoderImp constructor.
     * @param $dataFromDevice
     */
    public function __construct(string $dataFromDevice, $imei)
    {
        $this->dataFromDevice = $dataFromDevice;
        $this->imei = $imei;
        $this->AVLData = array();
    }

    public function getCountIoData(): int{
        $totalNumberOfIO = substr($this->dataFromDevice,70,2);
        $totalNumberOfIO = hexdec($totalNumberOfIO);
        return $totalNumberOfIO;
    }

    public function getNumberOfElements(): int
    {
        $dataCountHex = substr($this->dataFromDevice,18,2);
        $dataCountDecimal = hexdec($dataCountHex);

        return $dataCountDecimal;
    }

    public function getCodecType(): int
    {
        $codecTypeHex = substr($this->dataFromDevice,16,2);
        $codecTypeDecimal = hexdec($codecTypeHex);

        return $codecTypeDecimal;
    }

    public function decodeAVLArrayData(string $hexDataOfElement) :AVLData
    {
        $codecType = $this->getCodecType();

        if($codecType == self::CODEC8) {
            return $this->codec8Decode($hexDataOfElement);
        }

    }

    public function getArrayOfAllData(): array
    {
        $AVLArray = array();

        $totalNumberOfIo = $this->getCountIoData();

        $hexDataWithoutCRC = substr($this->dataFromDevice, 0, -8);
        //$hexAVLDataArray = substr($hexDataWithoutCRC, self::HEX_DATA_HEADER);

        $dataCount = $this->getNumberOfElements();

        $startPosition = self::HEX_DATA_HEADER;
        $this->endPosition = self::HEX_DATA_LENGHT;

       
        for($i=0; $i<$dataCount; $i++) {
        
            $hexDataOfElement = substr($hexDataWithoutCRC,$startPosition, $this->endPosition);
           
            //
            //Decode and add to array of elements
            $AVLArray[] = $this->decodeAVLArrayData($hexDataOfElement,$i);
            $startPosition += $this->endPosition;
            
        }

        return $AVLArray;
    }

    private function codec8Decode(string $hexDataOfElement) :AVLData {
        $archivo = fopen('log.txt', 'a');
        $arrayElement = array();

        $AVLElement = new AVLData();

        $AVLElement->setImei($this->imei);

        //We only get first 10 characters to get timestamp up to seconds.
        //date
       // $hexdata = substr($hexDataOfElement, 0, self::TIMESTAMP_HEX_LENGTH);
        $timestamp = substr(hexdec(substr($hexDataOfElement, 0, self::TIMESTAMP_HEX_LENGTH)), 0, 10);
        //fwrite($archivo, "Fecha Hex: ".$hexdata."\n" . PHP_EOL);
        $dateTimeWithoutFormat = new \DateTime();
        $dateTimeWithoutFormat->setTimestamp(intval($timestamp));
        $dateTimeWithFormat =  $dateTimeWithoutFormat->format('Y-m-d H:i:s') . "\n";
        //fwrite($archivo, "Fecha Decodificada: ".$dateTimeWithFormat."\n" . PHP_EOL);
        $AVLElement->setTimestamp($timestamp);
        $AVLElement->setDateTime($dateTimeWithFormat);
        //priority
        $stringSplitter = self::TIMESTAMP_HEX_LENGTH;
       // $hexdate = substr($hexDataOfElement, $stringSplitter, self::PRIORITY_HEX_LENGTH);
       // fwrite($archivo, "Priority Hex: ".$hexdate."\n" . PHP_EOL);
        $priority = hexdec(substr($hexDataOfElement, $stringSplitter, self::PRIORITY_HEX_LENGTH));
        //fwrite($archivo, "Priority Decodificada: ".$priority."\n" . PHP_EOL);
        $AVLElement->setPriority($priority);
        //longitude
        $stringSplitter+= self::PRIORITY_HEX_LENGTH;
        //$hexdate = substr($hexDataOfElement, $stringSplitter, self::LONGITUDE_HEX_LENGTH);
        //fwrite($archivo, "Longitude Hex: ".$hexdate."\n" . PHP_EOL);
        $longitudeValueOnArrayTwoComplement = unpack("l", pack("l", hexdec(substr($hexDataOfElement, $stringSplitter, self::LONGITUDE_HEX_LENGTH))));
        $longitude = (float) (reset($longitudeValueOnArrayTwoComplement) / 10000000);
        //fwrite($archivo, "Longitud Decodificada: ".$longitude."\n" . PHP_EOL);
        //latitude
        $stringSplitter+= self::LONGITUDE_HEX_LENGTH;
        $hexdate = substr($hexDataOfElement, $stringSplitter, self::LATITUDE_HEX_LENGTH);
        //fwrite($archivo, "Longitude Hex: ".$hexdate."\n" . PHP_EOL);
        $latitudeValueOnArrayTwoComplement = unpack("l", pack("l", hexdec(substr($hexDataOfElement, $stringSplitter, self::LATITUDE_HEX_LENGTH))));
        $latitude = (float) (reset($latitudeValueOnArrayTwoComplement) / 10000000);
        //fwrite($archivo, "Latitud Decodificada: ".$latitude."\n" . PHP_EOL);
        //Altitude
        $stringSplitter+= self::LATITUDE_HEX_LENGTH;
        $hexdate = substr($hexDataOfElement, $stringSplitter, self::ALTITUDE_HEX_LENGTH);
        //fwrite($archivo, "Altitude Hex: ".$hexdate."\n" . PHP_EOL);
        $altitude = hexdec(substr($hexDataOfElement, $stringSplitter, self::ALTITUDE_HEX_LENGTH));
        //fwrite($archivo, "Altitude Decodificada: ".$altitude."\n" . PHP_EOL);
        //Angle
        $stringSplitter+= self::ALTITUDE_HEX_LENGTH;
        //$hexdate = substr($hexDataOfElement, $stringSplitter, self::ANGLE_HEX_LENGTH);
        //fwrite($archivo, "Angle Hex: ".$hexdate."\n" . PHP_EOL);
        $angle = hexdec(substr($hexDataOfElement, $stringSplitter, self::ANGLE_HEX_LENGTH));
        //fwrite($archivo, "Angle Decodificada: ".$angle."\n" . PHP_EOL);
        //satellites
        $stringSplitter+= self::ANGLE_HEX_LENGTH;
        //$hexdate = substr($hexDataOfElement, $stringSplitter, self::SATELLITES_HEX_LENGTH);
        //fwrite($archivo, "satellites Hex: ".$hexdate."\n" . PHP_EOL);
        $satellites = hexdec(substr($hexDataOfElement, $stringSplitter, self::SATELLITES_HEX_LENGTH));
        //fwrite($archivo, "satellites Decodificada: ".$satellites."\n" . PHP_EOL);
        //speed
        $stringSplitter+= self::SATELLITES_HEX_LENGTH;
        //$hexdate = substr($hexDataOfElement, $stringSplitter, self::SPEED_HEX_LENGTH);
        //fwrite($archivo, "speed Hex: ".$hexdate."\n" . PHP_EOL);
        $speed = hexdec(substr($hexDataOfElement, $stringSplitter, self::SPEED_HEX_LENGTH));
        //fwrite($archivo, "speed Decodificada: ".$speed."\n" . PHP_EOL);
       
        //save Data
        $GPSData = new GPSData($longitude, $latitude, $altitude, $angle, $satellites, $speed);
        $AVLElement->setGpsData($GPSData);
        //Event ID
        $stringSplitter+= self::SPEED_HEX_LENGTH;
        //$hexdate =substr($hexDataOfElement, $stringSplitter, self::EVENTID_HEX_LENGTH);
        //fwrite($archivo, "Event Id Hex: ".$hexdate."\n" . PHP_EOL);
        $eventID = hexdec(substr($hexDataOfElement, $stringSplitter, self::EVENTID_HEX_LENGTH));
        //fwrite($archivo, "EventId Decodificada: ".$eventID."\n" . PHP_EOL);
        //IO DATA
        $stringSplitter+= self::EVENTID_HEX_LENGTH;
        //$hexdate =substr($hexDataOfElement, $stringSplitter, self::EVENTID_HEX_LENGTH);
        //fwrite($archivo, "Element Count Hex: ".$hexdate."\n" . PHP_EOL);
        $elementCount = hexdec(substr($hexDataOfElement, $stringSplitter, self::ELEMENTCOUNT_HEX_LENGTH));
        //fwrite($archivo, "ElementCount Decodificada: ".$elementCount."\n" . PHP_EOL);

        //IODATA 2
        $stringSplitter+= self::ELEMENTCOUNT_HEX_LENGTH+self::ELEMENT_COUNT_1B_HEX_LENGTH;
        $hexdate =substr($hexDataOfElement, $stringSplitter, self::ID_HEX_LENGTH);
       // fwrite($archivo, "Id Count Hex: ".$hexdate."\n" . PHP_EOL);
        $ID = hexdec(substr($hexDataOfElement, $stringSplitter, self::ID_HEX_LENGTH));
        //fwrite($archivo, "Id Count Decodificada: ".$ID."\n" . PHP_EOL);
        //IO ELEMENT DATA
        $stringSplitter+= self::ID_HEX_LENGTH;
        $hexdate =substr($hexDataOfElement, $stringSplitter, self::VALUE_HEX_LENGTH);
       // fwrite($archivo, "IO DATA Hex: ".$hexdate."\n" . PHP_EOL);
        $value = hexdec(substr($hexDataOfElement, $stringSplitter, self::VALUE_HEX_LENGTH));
        //fwrite($archivo, "IO DATA: ".$value."\n" . PHP_EOL);
        $IOElement = new Entities\IOData($eventID, $elementCount, $ID, $value);

        $AVLElement->setIOData($IOElement);
        //fwrite($archivo, "----------------------------------------------------------------------"."\n" . PHP_EOL);
        return $AVLElement;
      
    }

    private function convert($hex)
    {
        $dec = hexdec($hex);
        return ($dec < 0x7fffffff) ? $dec
            : 0 - (0xffffffff - $dec);
    }
}