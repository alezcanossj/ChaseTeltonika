<?php
/* Inicializa el servidor y comenzará a recibir datos del dispositivo, 
analizándolos y almacenándolos en la base de datos
*/
use lbarrous\TeltonikaDecoder\Server\SocketServer;
use Medoo\Medoo;

require __DIR__ . '/src/Server/SocketServer.php';
require __DIR__.'/config.php.dist.php';
$server = new SocketServer(Conf::host, Conf::port);

$server->runServer();