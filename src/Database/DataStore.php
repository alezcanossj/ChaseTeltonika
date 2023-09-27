<?php
/**
 * Created by PhpStorm.
 * User: Alvaro
 * Date: 18/07/2018
 * Time: 13:23
 */

namespace lbarrous\TeltonikaDecoder\Database;

require_once 'pointLocation.php';
require_once 'Device.php';
require_once 'Car.php';
use Medoo\Medoo;
//include("/../../config.php");

class DataStore
{
    
    private $dataBaseInstance;
    private $pointLocation;
    private $api_token;
    private $api_host;
    /**
     * DataStore constructor.
     * @param $dataBaseInstance
     */
    public function __construct(){
       
        $this->dataBaseInstance = new Medoo([
            // required
            'database_type' => 'mysql',
            'database_name' => \Conf::db_name,
            'server' => \Conf::db_host,
            'username' => \Conf::db_user,
            'password' => \Conf::db_pass,
        ]);
        $this->api_token = \Conf::api_token;
        $this->api_host = \Conf::api_host;
    }

    public function enviarNotificacion($datos){
        if($datos["status"]==1){
            
            $contenido="El vehículo " .$datos["plate"]. " ha entrado en la geocerca Segunda Geo";
        }else{
            $contenido="El vehículo " .$datos["plate"]. " ha salido de la geocerca Segunda Geo";
        }
        $data = array(
            'imei' => $datos["imei"],
            'status'=> $datos["status"],
            'geofence_id' =>$datos["geofence_id"],
            'geofence_name' => $datos["geofence_name"],
            "content"=> $contenido,
            "title"=> "Notificación de geocerca",
            "is_office"=> $datos["is_office"]
        );

        $jsonData = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_host.'/notificacion-geo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: '. $this->api_token
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    
        // Ejecutar la solicitud cURL
        $response = curl_exec($ch);
    
        // Manejar la respuesta como desees (puede imprimirse para depuración)
        // echo $response;
    
        // Cerrar la sesión cURL
        
        curl_close($ch);
       
    }

    public function enviarNotificacionVelocidad($datos){

            
       $contenido="El vehículo " .$datos["plate"]. " está superando los limites de velocidad";

        $data = array(
            'imei' => $datos["imei"],
            "content"=> $contenido,
            "title"=> "Velocidad permitida superada!",
            "speed" => $datos["speed"]
        );

        $jsonData = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_host.'/notificacion-velocidad');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: '.$this->api_token
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    
        // Ejecutar la solicitud cURL
        $response = curl_exec($ch);
    
        // Manejar la respuesta como desees (puede imprimirse para depuración)
        // echo $response;
    
        // Cerrar la sesión cURL
        
        curl_close($ch);
       
    }

    public function storeDataFromDevice($AVLElement,$geofenceCoordinatesArray) {

        $this->pointLocation = new pointLocation();
        $device = new  Device($this->dataBaseInstance);
        //VERIFICAR SI EXISTE EL DEVICE
        $deviceExists = $device->checkIfImeiExists($AVLElement->getImei()->getImeiNumber());
        $car = new Car($this->dataBaseInstance);

        // SI EXISTE EL DISPOSITIVO
        if ($deviceExists) {
            //SI EXISTE UN VEHICULO CON ESE DISPOSITIVO
            if (!$car->checkIfCarExists($AVLElement->getImei()->getImeiNumber())) {
                  // El vehículo no existe, crea un nuevo registro
                  $car->createCar($AVLElement->getImei()->getImeiNumber(), "Default", "Default", "123-abc", 1);
            }
        } else {
            //NO EXISTE EL DISPOSITIVO NI EL VEHICULO
            $device->saveDevice($AVLElement->getImei()->getImeiNumber(),"-",0);
            $car->createCar($AVLElement->getImei()->getImeiNumber(), "Default", "Default", "123-abc", 1);
        }

        //LUEGO DE VERIFICAR O CREAR SE INSERTA LOS DATOS DEL GPS
        $this->dataBaseInstance->insert(
            'gps_data_devices',
            array(
                'imei' => $AVLElement->getImei()->getImeiNumber(),
                'longitude' => $AVLElement->getGpsData()->getLongitude(),
                'latitude' => $AVLElement->getGpsData()->getLatitude(),
                'altitude' => $AVLElement->getGpsData()->getAltitude(),
                'angle' => $AVLElement->getGpsData()->getAngle(),
                'satellites' => $AVLElement->getGpsData()->getSatellites(),
                'speed' => $AVLElement->getGpsData()->getSpeed(),
                'datetime' => $AVLElement->getDateTime(),
            )
        );
        
        $points = $AVLElement->getGpsData()->getLongitude() . " " . $AVLElement->getGpsData()->getLatitude();
       
        // Verificar si el vehículo se encuentra dentro de una geocerca
        $sql = "Select cars.id,cars.plate,geofence from cars left join car_in_geofence on car_in_geofence.car=cars.id where car_in_geofence.status=1 and cars.device = ".$AVLElement->getImei()->getImeiNumber()." limit 1";
        $carInGeofence = $this->dataBaseInstance->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $placa="";
        if (count($carInGeofence) > 0) {
            $insideGeofence = true;
            $geofenceInId = $carInGeofence[0]['geofence'];
            $placa= $carInGeofence[0]['plate'];
          
        } else {
            $sql = "Select cars.plate from cars where cars.device = ".$AVLElement->getImei()->getImeiNumber()." limit 1";
            $carSearch = $this->dataBaseInstance->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            if(count($carSearch)>0){
                $placa = $carSearch[0]['plate'];
            }
            $insideGeofence = false;
         
        }
        
        //recorrer las coordenadas para validar el IMEI
        foreach ($geofenceCoordinatesArray as $geofenceCoordinates) {
            $geofenceId = $geofenceCoordinates['id'];
            $geofenceName=$geofenceCoordinates["name"];
            $is_office = $geofenceCoordinates['is_office'];
            $respuesta = $this->pointLocation->pointInPolygon($points, $geofenceCoordinates['puntos']);
         
            if($respuesta == "inside"){
              
                if(!$insideGeofence){
                    
                   $this->enviarNotificacion(["imei"=>$AVLElement->getImei()->getImeiNumber(),"geofence_id"=>$geofenceId,"status"=>1,"is_office"=>$is_office, "plate"=>$placa,"geofence_name"=>$geofenceName]);
                }
            }else{
                if($insideGeofence &&  $geofenceInId == $geofenceId ){
                   
                    $this->enviarNotificacion(["imei"=>$AVLElement->getImei()->getImeiNumber(),"geofence_id"=>$geofenceId, "status"=>0, "is_office"=>$is_office,"plate"=>$placa,"geofence_name"=>$geofenceName]);
                }elseif($insideGeofence && $geofenceInId != $geofenceId){

                }
            
            }
        }
        //Buscar el limite de velocidad
        $speedLimit = $this->getSpeedLimitFromOverpass($AVLElement->getGpsData()->getLatitude(), $AVLElement->getGpsData()->getLongitude());
        //Si no es 0 el valor
        if($speedLimit!==0){
            //si ha encontrado el limite de velocidad
            if($speedLimit!==false){
                //Si la velocidad actual supera al limite de la carretera
                if($speedLimit< $AVLElement->getGpsData()->getSpeed()){
                    $this->enviarNotificacionVelocidad(["speed"=>$AVLElement->getGpsData()->getSpeed(), "imei"=>$AVLElement->getImei()->getImeiNumber()]);
                }
            }
                
        }
    }

    public function getGeofences(){
        $sql = "Select geofence.id,geofence.name,geofence.is_office, CONCAT( geofence_coordinates.longitude,' ',geofence_coordinates.latitude) AS POINTS from geofence left join geofence_coordinates on geofence_coordinates.id_geofence=geofence.id";

        $geofenceCoordinatesArray = $this->dataBaseInstance->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
      
        $geofenceArray = [];

        foreach ($geofenceCoordinatesArray as $pointData) {

            $geofenceName = $pointData['name'];
            $geofenceId = $pointData['id'];
            $is_office = $pointData['is_office'];
            $coordinates = explode(' ', $pointData['POINTS']);
        
            if (!isset($geofenceArray[$geofenceName])) {
                $geofenceArray[$geofenceName] = [
                    'id' => $geofenceId,
                    'is_office' => $is_office,
                    'puntos' => ''
                ];
            }
        
            $geofenceArray[$geofenceName]['puntos'] .= implode(' ', $coordinates) . ',';
            $geofenceArray[$geofenceName]['name']=$geofenceName;
        }
        
        // Elimina la coma final en cada cadena de puntos
        foreach ($geofenceArray as &$geofence) {
            $geofence['puntos'] = rtrim($geofence['puntos'], ',');
        }
        foreach ($geofenceArray as &$geofence) {
                $coordinates = explode(',', $geofence['puntos']);
                $geofence['puntos'] = array_map('trim', $coordinates); // Eliminar espacios en blanco
                
                // Opcional: convertir los elementos en strings
                $geofence['puntos'] = array_map('strval', $geofence['puntos']);
        }
        foreach ($geofenceArray as &$geofence) {
            $geofence["puntos"][] = $geofence["puntos"][0];
        }
        
        return $geofenceArray;
    }

    public function getSpeedLimitFromOverpass($latitude, $longitude) {


        // URL de la API Overpass
        $url = "http://overpass-api.de/api/interpreter?data=[out:json];way[maxspeed](around:15,$latitude,$longitude);out%20tags;";
    
        // Realizar la solicitud a la API
        $response = file_get_contents($url);
    
        if ($response === false) {
            return false;
        }
    
        // Decodificar la respuesta JSON
        $data = json_decode($response, true);
    
        // Inicializar la velocidad máxima como 0 (por defecto)
        $maxSpeed = 0;
    
        // Iterar a través de los elementos para encontrar la velocidad máxima y validar la calle
        foreach ($data['elements'] as $element) {
            if ($element['type'] === 'way' && isset($element['tags']['maxspeed'])) {
                $speed = intval($element['tags']['maxspeed']);
    
                
                if ($speed > $maxSpeed) {
                    $maxSpeed = $speed;
                }
            }
        }
        if($maxSpeed===0){
            return false;
        }else{
            return $maxSpeed;
        }
        
    }

    
}