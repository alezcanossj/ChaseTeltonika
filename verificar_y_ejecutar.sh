#!/bin/bash

PUERTO=21328;
LOG_FILE=/home/locchase/repositories/ChaseTeltonika/log_bash.log
FECHA_HORA=$(date +"%Y-%m-%d %H:%M:%S")
# Verificar si el puerto está en uso
if nc -z -w1 127.0.0.1 $PUERTO; then
     echo "$FECHA_HORA: El puerto $PUERTO está en uso." >> $LOG_FILE
else
    echo "$FECHA_HORA: El puerto $PUERTO no está en uso. Ejecutando archivo PHP." >> $LOG_FILE
    php /home/locchase/repositories/ChaseTeltonika/run.php
fi