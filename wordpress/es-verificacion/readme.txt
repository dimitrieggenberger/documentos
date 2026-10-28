=== Verificación de documentos (EuropaSchule) ===
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv3 or later

Página pública que verifica los documentos oficiales emitidos en la plataforma escolar (plugin de Moodle local_documents).

== Instalación ==

1. Plugins › Añadir nuevo › Subir el ZIP es-verificacion-<versión>.zip y activarlo.
2. Ajustes › Verificación de documentos: la dirección de Moodle y el token (el mismo que en Moodle).
3. Crear la página «Verificar» en europaschule.eu/verificar/, con el shortcode [es_verificacion].
4. En Moodle: Administración del sitio › Plugins locales › Documentos oficiales › Ajustes:
   «Página de verificación» = la dirección de esa página (por defecto https://europaschule.eu/verificar/), «Token del sitio web» = el mismo token.

Desde entonces el código QR de cada documento abre /verificar/?c=CÓDIGO.

== Qué muestra ==

Solo lo que el propio documento imprime (título, número, a nombre de quién, fechas, firmantes), con el número de
identidad enmascarado, y el estado: válido, vencido, sustituido por una versión corregida o anulado. Quien tenga el
PDF puede comprobar en su navegador que es idéntico al original (SHA-256); el archivo no se envía.
