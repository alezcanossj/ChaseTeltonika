<?php
/* Inicializa el servidor y comenzará a recibir datos del dispositivo, 
analizándolos y almacenándolos en la base de datos
*/
use lbarrous\TeltonikaDecoder\Server\SocketServer;
use Medoo\Medoo;
require 'src/server/SocketServer.php';
require 'config.php.dist.php';
$server = new SocketServer(Conf::host, Conf::port);

$server->runServer();