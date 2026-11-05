# SIMPLE WEBHOOK TESTER

Es un proyecto temporal para ayudar con las integraciones de clientes externos.

Sirve para simular envio de peticioes POST desde un servidor A (este proyecto donde esté hosteado) hacia un servidor B (donde se prueba la integración)

## TECNOLOGÍAS
Ninguna en particular, es un proyecto simple que hostearemos en alguno de nuestros servidores


## METODOLOGÍA Y FORMA DE USO + CONSIDERACIONES

*COMENTARIOS DE CLAUDE*

Armé el proyecto con tu estructura (`index.php` y `ajax/send.php`), más `bootstrap.php` y `config.php` en la raíz. Es PHP puro, sin librerías externas, y necesita PHP 8.0+ con la extensión cURL. No pude ejecutarlo aquí, así que conviene probarlo en tu servidor antes de usarlo en serio.

**Qué permite hacer**
- Elegir método y URL, y el Content-Type (JSON, form-urlencoded, texto, XML u otro). El cuerpo tiene botones para formatear, minificar y cargar un ejemplo, además de validar el JSON.
- Agregar headers comunes con un clic (`X-Request-ID`, `Idempotency-Key`, `X-Webhook-Event`, etc.) o escribir los tuyos.
- Usar variables `{{uuid}}`, `{{timestamp}}`, `{{iso}}` y `{{random}}` en URL, cuerpo y headers, para que cada evento de prueba sea distinto.
- Autenticar con Bearer, Basic o API key en un header.
- Firmar el cuerpo con HMAC, con plantillas para GitHub, Shopify y una genérica, o con timestamp (`timestamp.cuerpo`). La firma se calcula en el servidor sobre el cuerpo exacto que se envía, ya con las variables reemplazadas.
- Ver el status, el tiempo desglosado, los headers y el cuerpo de la respuesta, y la petición real enviada. La "Vista previa" muestra esa petición sin enviarla.
- Ajustar timeout, redirecciones y verificación SSL, y enviar JSON inválido a propósito para probar cómo reacciona el receptor.

**Qué asumí**
- Los envíos salen desde el servidor donde alojes la web, no desde tu navegador, así que sirve para probar que ese servidor llega a los otros.
- Las peticiones pueden ir a IPs internas, porque entiendo que ese es el uso en tu empresa.
- Como esto funciona como un proxy, no lo dejes abierto en internet. En `config.php` puedes poner usuario y contraseña (Basic Auth), limitar los hosts destino y bloquear IPs privadas.
- El formulario se guarda en `localStorage` del navegador, secretos incluidos. Hay un botón para borrarlo en la pestaña Opciones.