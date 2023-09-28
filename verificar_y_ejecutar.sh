#!/bin/bash

PUERTO=21328;

# Verificar si el puerto está en uso
if lsof -i :$PUERTO; then
    echo "El puerto $PUERTO está en uso."
else
    echo "El puerto $PUERTO no está en uso."
    php /home/locchase/repositories/ChaseTeltonika/run.php
fi