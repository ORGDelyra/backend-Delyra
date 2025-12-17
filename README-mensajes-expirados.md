# Eliminación automática de mensajes de chat

Este proyecto elimina automáticamente los mensajes de chat con más de 1 día de antigüedad usando un comando programado de Laravel.

## Ejecución manual

Puedes ejecutar el comando manualmente con:

    php artisan messages:delete-old

Esto eliminará todos los mensajes de chat creados hace más de 1 día.

## Ejecución automática (Scheduler)

El comando está registrado en el scheduler de Laravel y se ejecuta diariamente. Para que la tarea programada funcione, asegúrate de tener corriendo el scheduler de Laravel con:

    php artisan schedule:work

O bien, agrega la siguiente línea al cron de tu servidor:

    * * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1

Así, los mensajes viejos se eliminarán automáticamente cada día.
