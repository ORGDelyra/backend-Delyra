# Configuración de cron para tareas programadas de Laravel

Para que los mensajes de chat se eliminen automáticamente cada día, debes agregar la siguiente línea al cron de tu servidor (Linux):

```
* * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Reemplaza `/ruta/a/tu/proyecto` por la ruta real de tu proyecto, por ejemplo:

```
* * * * * cd /home/usuario/ApiDelyra-1 && php artisan schedule:run >> /dev/null 2>&1
```

Esto ejecutará el scheduler de Laravel cada minuto y permitirá que todas las tareas programadas (como la eliminación de mensajes) se ejecuten automáticamente.

## ¿Cómo agregarlo?
1. Abre la terminal de tu servidor.
2. Ejecuta:
   
   crontab -e

3. Pega la línea anterior al final del archivo y guarda.

¡Listo! Ahora Laravel eliminará los mensajes viejos automáticamente cada día.
