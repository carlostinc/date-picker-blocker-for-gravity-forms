# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-08-14

Pasada de cumplimiento con el Plugin Checker de WordPress.org: 87 avisos a cero,
sin cambios de comportamiento. Ninguno era una vulnerabilidad real; todos venían
de dos límites del análisis estático.

### Base de datos
- **Los nombres de tabla se enlazan con el placeholder `%i`** de
  `$wpdb->prepare()` en lugar de interpolarse en la cadena SQL. Esto elimina de
  raíz los avisos `UnescapedDBParameter` e `InterpolatedNotPrepared`, en vez de
  silenciarlos: el sniff solo audita el primer parámetro de `prepare()`, así que
  pasar la tabla como argumento la deja fuera de la superficie de análisis.
- **`form_scope_where()` pasa a ser `form_scope_csv()`.** La cláusula `IN ()` de
  longitud variable obligaba a construir los `%d` dentro del SQL; ahora los IDs
  de formulario viajan como CSV enlazado a un único `%s` y se filtran con
  `FIND_IN_SET()`, de modo que las consultas de `for_js()` son literales
  completas. Equivalente en resultados: las filas globales las sigue cubriendo
  la rama `gravity_form_id IS NULL`, y una lista vacía sigue devolviendo solo
  las globales.
- Los `$wpdb->update()` / `delete()` sobre tablas propias del plugin llevan ya la
  justificación de consulta directa que les faltaba.

### Seguridad
- **La verificación de nonce vuelve al cuerpo de cada handler** de admin-post y
  AJAX, en lugar de vivir en un helper. PHPCS solo la reconoce cuando está en el
  mismo scope que los datos de la petición que protege, así que centralizarla
  producía 29 falsos positivos. Los helpers conservan la comprobación de
  capability. El comportamiento —y la protección— no cambian.

### Validación en el servidor
- **Corregido un bypass real en los campos de fecha multi-parte.** Gravity Forms
  envía las variantes de tres inputs y de desplegables (`datefield` /
  `datedropdown`) como un array (`input_5[]`), no como las claves
  `input_5_1/_2/_3` que esperaba el parseo propio; `sanitize_text_field()` sobre
  un array devuelve `''` y el campo se saltaba la validación del servidor. Con
  JS desactivado, POST directo o envíos por la API de Gravity Forms, una fecha
  bloqueada se aceptaba. El JS del navegador sí cubría los tres tipos — por eso
  nunca se vio en pruebas manuales.
- **La validación migró de `gform_validation` a `gform_field_validation`**, el
  punto de extensión canónico por campo: Gravity Forms entrega el valor ya
  compuesto por `GFFormsModel::get_field_value()` y el plugin ya no lee `$_POST`
  en absoluto, que era el aviso del equipo de revisión. El parseo pasa a
  `GFCommon::parse_date()` + `checkdate` — el mismo par que usa el propio GF —
  así que plugin y GF aceptan exactamente las mismas fechas (el parseo propio
  era más estricto y descartaba en silencio fechas que GF admite).
- La validación ahora respeta los campos ocultos por lógica condicional y las
  páginas de los formularios multipágina, porque GF los excluye antes de
  disparar el filtro. Antes, un valor obsoleto en un campo oculto podía dejar
  un formulario imposible de enviar.
- Los handlers del admin leen cada input junto a su verificación de nonce; se
  eliminó el helper `optional_int()` y los helpers de formato de fecha que el
  parseo propio hacía necesarios.

### Compatibilidad
- **Requiere WordPress 6.2 o superior** (antes 6.0), que es donde se introdujo el
  placeholder `%i`.

### Arquitectura
- **`features/DateRestrictions/` se aplana en `features/`.** La arquitectura
  Vertical Slice preveía varias slices, pero solo llegó a existir una: ese nivel
  intermedio era una carpeta con un único hijo y un segmento de namespace que no
  distinguía de nada. `Admin/`, `Database/`, `Enforcement/`, `Frontend/` y
  `Rules/` cuelgan ahora directamente de `features/`, y el namespace acompaña al
  movimiento (`Paxrank\DateBlocker\Admin`, `…\Database`, …), con el handler en
  la raíz `Paxrank\DateBlocker`. `Shared/` no se mueve: no es una feature.
- **El autoloader deja de depender del orden de sus raíces.** Al aplanar, los
  prefijos pasan a solaparse (`Paxrank\DateBlocker\` contiene a
  `…\DateBlocker\Shared\`) y el bucle abandonaba en cuanto un prefijo coincidía,
  encontrase o no el archivo — con lo que `Shared\DateFormat` se habría buscado
  solo en `features/Shared/` y habría fallado. Ahora solo se detiene cuando
  encuentra el archivo de verdad, y sigue probando las demás raíces si no.
- Cambio puramente interno: no varían el comportamiento ni los nombres públicos
  (tablas, opciones, nonces, acciones `admin_post_*`, globals JS).

### Empaquetado
- **Fuera los archivos de traducción del paquete** (`.po`, `.mo` y la plantilla
  `.pot`), a petición de la revisión de WordPress.org. Los plugins alojados en
  .org reciben sus traducciones por translate.wordpress.org, que las genera para
  todos los idiomas, admite contribuciones de la comunidad y las distribuye por
  el sistema estándar de actualizaciones. La internacionalización del código no
  cambia: el `Text Domain` se conserva, y es lo que asocia el proyecto en
  GlotPress. Se retira el header `Domain Path`, que ya no apunta a nada.
- La traducción al español sigue en el historial de Git y su destino es GlotPress.

## [1.0.0] - 2026-08-12

Primera versión pública. Todo el trabajo anterior fue iteración previa al
lanzamiento y se consolidó en esta entrega.

### Funcionalidad
- **Fechas y rangos bloqueados** en campos de fecha de Gravity Forms: un día
  suelto o un rango completo en un solo registro (`end_date`).
- **Antelación mínima** de 0 a 365 días, con resolución por prioridad
  (campo > formulario > global). Las fechas pasadas se bloquean siempre.
- **Días de la semana** bloqueados, con resolución aditiva (global + formulario
  + campo se combinan).
- **Tres niveles de alcance** para cada restricción: global, por formulario, o
  por campo de fecha concreto.
- Soporte para las tres presentaciones del campo Date de Gravity Forms:
  datepicker, input de texto y desplegables día/mes/año.

### Arquitectura
- **El navegador es UX, el servidor es autoridad.** Las fechas no disponibles se
  agrisan en el datepicker vía el filtro oficial
  `gform_datepicker_options_pre_init`, y cada envío se revalida en
  `gform_validation`, de modo que las reglas se sostienen aun sin JavaScript.
- **El cliente ejecuta decisiones ya resueltas por el servidor**: el payload
  lleva la antelación resuelta por campo, el offset horario del sitio y la
  lista de campos que el servidor reconoce. El JS nunca reimplementa la
  semántica del servidor.
- El payload se emite **una sola vez**, solo en páginas que renderizan un
  formulario con campos de fecha, y solo con las filas de esos formularios más
  las globales. Las páginas sin formulario no ejecutan ninguna consulta.
- Parseo por **formato propio de cada campo** de Gravity Forms (no el ajuste
  global del plugin), con verificación de ida y vuelta que rechaza fechas
  imposibles. "Hoy" se calcula en la zona horaria del sitio.
- Escrituras atómicas (`INSERT ... WHERE NOT EXISTS`) y limpieza automática de
  restricciones al borrar permanentemente un formulario o campo.
- **Namespaces PHP + autoload PSR-4.** Todas las clases viven en
  `Paxrank\DateBlocker\...` (el sub-namespace espeja la carpeta) y se cargan
  de forma perezosa vía `autoload.php` — sin listas de `require_once` y sin
  prefijos `PaxRank_GF_`. Nombre de archivo == nombre de clase, y el plugin
  queda con una sola slice (`features/DateRestrictions/`) más `Shared/`.

### Administración
- Pantalla bajo **Ajustes**, con formularios nativos (POST → redirect → aviso
  estándar de WordPress).
- Formato de fecha configurable, panel de debug y borrado de datos opcional al
  desinstalar.
- Totalmente traducible; las traducciones se entregan vía translate.wordpress.org.

---

# Historial de desarrollo previo a 1.0

Las entradas siguientes corresponden a la iteración interna anterior al
lanzamiento. Se conservan como registro de desarrollo; ninguna de esas
versiones llegó a publicarse.

## [1.4.0] - 2026-08-12

### Fixed
- **Formato por campo en el cliente.** Los valores tecleados se parsean ahora con el formato propio del campo de GF (capturado del propio filtro `gform_datepicker_options_pre_init`), no con el formato global del plugin. Con los defaults de fábrica (`mdy` en GF, `DD/MM/YYYY` en el plugin), el cliente podía borrar fechas válidas como "fechas pasadas" sin que el envío llegara nunca al servidor.
- **Antelación con la misma prioridad que el servidor.** El picker consumía las filas crudas con `Math.max`; ahora recibe la antelación **ya resuelta por campo** (campo > formulario > global), así que una fila de formulario que relaja la global funciona también visualmente.
- **"Hoy" es el reloj del negocio.** El payload lleva el offset horario del sitio y el cliente computa "hoy del sitio" desde su reloj UTC; visitantes en otras zonas ven la misma disponibilidad que el servidor impone.
- **El mensaje bloqueado ya no rompe la lógica condicional de GF:** al limpiar un valor bloqueado se dispara un `change` sintético marcado que el propio handler ignora.
- **Flag de validación rancio** en procesos de larga vida (`$failed_forms` se resetea al inicio de cada validación).

### Added
- **Bloqueo visual para las presentaciones multi-input y desplegables** (día/mes/año): partes limpiadas + mensaje, identificando las partes por las clases estructurales de GF. Antes solo la presentación datepicker tenía UX de bloqueo.
- **Campos con `inputType` date** (p. ej. un Post Custom Field mostrado como datepicker) ahora se reconocen, restringen y validan (`get_input_type()` en las tres capas PHP, con gating espejo en el JS).
- **Limpieza automática de restricciones huérfanas** al borrar permanentemente un formulario o campo de GF (`gform_after_delete_form` / `gform_after_delete_field`).

### Changed
- **Payload único y filtrado.** Se emite una sola vez (muere el duplicado localize + inline), solo en páginas que renderizan un formulario de GF con campos de fecha, y solo con las filas de esos formularios más las globales. Páginas sin formulario ya no ejecutan ninguna consulta de restricciones. El script inline de bootstrap por formulario se eliminó por completo: el JS se auto-inicializa vía `gform_post_render`, el pipeline de init del propio GF.
- **Ruptura para integradores:** cambia la forma del global `paxrankGFBlocker` (la antelación viaja resuelta por campo bajo `forms`; las filas van filtradas por página).

### Removed
- **El tope oculto de 3 años** del datepicker (hardcodeado, sin filtro y sin contraparte en el servidor).

### Hardened
- **Guardados atómicos** (`INSERT ... WHERE NOT EXISTS`): un doble clic concurrente ya no puede duplicar filas ni romper el invariante de una-fila-por-scope de antelación/días de semana.

## [1.3.0] - 2026-08-10

### Changed
- **La pantalla de ajustes pasó al ciclo nativo de WordPress** (POST → redirect → aviso). Cada alta, borrado y guardado envía un formulario real a `admin-post.php`, redirige y confirma con un aviso estándar descartable arriba de la página, usando el mecanismo `settings_errors` del propio núcleo. Varios avisos apilan correctamente, cosa que antes fallaba porque caían dentro del flex del header.
- El header ahora vive en un contenedor `paxrank-settings-section` igual que las demás secciones, seguido del marcador `wp-header-end` que ancla los avisos debajo. Corregida además la especificidad del título, cuya regla de 26px nunca llegaba a aplicarse frente al `.wrap h1` del núcleo.
- Los borrados por fila son enlaces GET con nonce por ítem (estilo core), con confirmación traducida renderizada por el servidor — el plural del mensaje de anticipación usa ahora el conteo real de la fila.

### Removed
- **Los dos sistemas de avisos propios**: el toast flotante (template, JS y CSS) y los avisos inline por sección. Los reemplazan los avisos nativos.
- **Las cajas de status** del encabezado y sus tres consultas `COUNT(*)` por render (una de ellas ya era trabajo muerto).
- **El fondo crème custom** de la página; queda el fondo nativo del admin y cada sección conserva su superficie blanca.
- **Ruptura para integradores:** las acciones AJAX de escritura (`wp_ajax_paxrank_gf_add_*`, `_delete_*`, `_save_*`) ya no existen; las reemplazan sus equivalentes `admin_post_*`. Solo `wp_ajax_paxrank_gf_get_form_fields` sigue siendo AJAX.

## [1.2.1] - 2026-08-07

### Changed
- **La pantalla pasó de menú de primer nivel a submenú de Ajustes** (`Ajustes → Bloqueador de Fechas`), para no sumar ruido a la barra lateral del admin. El slug de la pantalla no cambió; la URL sí pasa de `admin.php?page=…` a `options-general.php?page=…`.
- Agregado un enlace **Ajustes** en la fila del plugin dentro de la pantalla de Plugins, que compensa la menor visibilidad de vivir bajo Ajustes.

### Removed
- **Ícono del menú y `admin-menu.css`.** Un submenú no tiene ícono, así que la hoja de estilos que lo dimensionaba (agregada en 1.1.1) quedó sin ningún selector aplicable. Cargaba en todas las páginas del admin, de modo que quitarla elimina además una petición en todo wp-admin. El JPG se conserva en `assets/` como base para el ícono que pide el directorio de WordPress.org.

## [1.2.0] - 2026-08-07

### Changed
- **Renombrado a Date Picker Blocker for Gravity Forms**, con slug y text domain `date-picker-blocker-for-gravity-forms`, para cumplir la política de marcas de WordPress.org, que rechaza los nombres que lideran con una marca ajena.
- **Los strings fuente pasaron a inglés** y el español se entrega ahora como traducción (`es_ES`). La interfaz en español queda idéntica. Los strings de JavaScript, que antes estaban hardcodeados y eran intraducibles, viajan vía `wp_localize_script`.

### Added
- `readme.txt` en formato WordPress.org y archivo `LICENSE` con el texto de la GPL-2.0.
- **Migración automática de esquema en las actualizaciones.** Las actualizaciones automáticas no disparan el hook de activación, así que un sitio que venía de la v1 se quedaba sin la columna `end_date`. Ahora se aplica sola.

### Fixed
- **Mensaje de validación a nivel de formulario.** Dependía de buscar una subcadena en español dentro de un mensaje ya traducido, por lo que nunca se disparaba para los fallos de anticipación ni de fecha pasada, y se habría roto por completo al traducir el plugin.
- Corregido el único error de PHPCS del plugin, en el armado dinámico de SQL de `ReadingBlockedRanges`.
- Encabezados del plugin: `Author` en texto plano con `Author URI` aparte, más `Plugin URI` y `License URI`. `Requires at least` pasó de 5.0 (incorrecto: `wp_timezone()` exige 5.3) a 6.0.

## [1.1.1] - 2026-07-21

### Security
- **Validación server-side robusta por formato de campo (cierra un fail-open):** la validación de envíos ahora parsea cada fecha usando el formato propio del campo de Gravity Forms (`dateFormat`) — el valor de input único con el formato PHP del campo, y los sub-inputs `_1/_2/_3` en el orden real del campo — en lugar del único formato global del plugin. Antes, si el formato de un campo difería del global (p. ej. campo `mm/dd/yyyy` con plugin en `DD/MM/YYYY`) o si un campo dropdown usaba orden `dmy`, la fecha no se podía parsear y se **salteaba toda la verificación**, dejando pasar una fecha que debía estar bloqueada. Las fechas genuinamente inválidas se siguen salteando (las valida Gravity Forms).
- **XSS DOM en el selector de campos del admin:** los `<option>` del selector de campos ahora se construyen con `.text()` en vez de concatenar HTML, así el *label* de un campo de Gravity Forms (controlable por un rol con permiso de editar formularios) no puede inyectar `<script>` vía `jQuery.html()`.
- **Endurecimiento:** el `wp_json_encode` del inline script del frontend usa flags `JSON_HEX_*` (previene un breakout `</script>` latente); `enabled_post_types` se valida contra los post types públicos registrados; los días de la semana se acotan a 0–6; y `save_uninstall_option` usa `wp_unslash`.

### Fixed
- **Ícono del menú de administración sobredimensionado:** el refactor 1.1.0 había quitado la regla que ajustaba el ícono (un JPG de 192×192) al tamaño del menú, por lo que se veía enorme y desbordado en la barra lateral. Se restauró vía una hoja de estilos enqueuada (`admin-menu.css`) que se carga en todas las páginas de admin y acota el ícono a 20×20.

## [1.1.0] - 2026-07-20

### Added
- **Bloqueo por rangos de fechas:** ahora se puede bloquear un rango completo (fecha inicio → fecha fin) en un solo registro, además de fechas sueltas. Nueva columna `end_date` (nullable; NULL = un solo día) en `wp_paxrank_gf_blocked_dates`.
- Filtro `paxrank_gf_date_blocker_should_load` para habilitar el frontend en contextos no singulares (archives, home) sin editar código.
- Internacionalización: todos los textos PHP envueltos en funciones i18n + plantilla `languages/paxrank-gf-date-blocker.pot`.

### Changed
- **Refactor a Vertical Slice Architecture (VSA + DDD-Light):** el código pasó de capas técnicas (`includes/`, `admin/`) a features (`Shared/`, `features/DateRestrictions/`, `features/PluginSettings/`) con tipos PHP 8 y PHPDoc. HTML/CSS inline extraídos a plantillas y hojas de estilo con clases `paxrank-`.
- Las tablas se crean con el esquema completo (incluida `end_date`) en la **activación** del plugin vía `dbDelta`, en lugar de consultar `SHOW TABLES` en cada request. En sitios existentes la actualización del esquema es un paso manual (reactivar el plugin o correr el `ALTER TABLE`).
- Zona horaria: el cálculo de "hoy" ahora usa la zona horaria del sitio (`wp_timezone()`) en vez de la del servidor.

### Fixed
- **Datepicker duplicado a ancho completo al final de la página (causa raíz):** las llamadas post-inicialización `.datepicker('option', ...)` del propio plugin repintaban el calendario dentro del div compartido `#ui-datepicker-div` (jQuery UI ejecuta `_updateDatepicker` en cada cambio de opción) estando cerrado; sin el CSS base del datepicker (Bricks / optimizadores de CSS) ese div lleno se veía como un calendario a ancho completo al final del `<body>`. Ahora las opciones (`minDate`, `maxDate`, `beforeShowDay`) se inyectan **antes** de la inicialización mediante el filtro oficial de Gravity Forms `gform_datepicker_options_pre_init`, así el div nunca se pinta mientras está oculto. El camino anterior queda solo como fallback (en una única llamada batcheada), y se conserva la regla CSS defensiva `#ui-datepicker-div { display: none; }` como red de seguridad.
- `uninstall.php` ahora elimina las **tres** tablas (antes omitía `weekday_restrictions`) y todas las opciones del plugin.
- Verificación de capacidad (`current_user_can`) añadida al handler AJAX de campos de formulario.
- Nonce AJAX verificado con `isset()` previo (evita warnings) y unificado en el JS de administración.
- Se corrigió el texto de la sección de anticipación ("Sistema Aditivo" → prioridad campo > formulario > global).
- Se eliminó la duplicación de instancias de la clase core (el enqueue del frontend se registraba dos veces).

## [1.0.1] - 2025-01-14

### Fixed
- **Critical Fix**: Resolved issue where dates with single-digit days (1-10) could be selected but would incorrectly show "No puedes seleccionar fechas en el pasado" error message and clear the field
- **Date Selection Issue**: Fixed problem where days 1-9 could not be selected from datepicker dropdown while day 10+ worked correctly
- **Field Population**: Resolved issue where valid dates were not being populated in the date field after selection
- **Date Format Issue**: Fixed critical bug where DD/MM/YYYY dates (e.g., "02/10/2025" for October 2nd) were being incorrectly parsed as MM/DD/YYYY format (February 10th), causing future dates to be blocked as "past dates"
- **Date Parsing**: Completely rewrote `normalizeDateString()` function to properly handle all date formats:
  - M/D/YYYY (e.g., "1/1/2025", "1/10/2025")
  - MM/DD/YYYY (e.g., "01/01/2025", "12/10/2025")
  - YYYY-M-D (e.g., "2025-1-1", "2025-1-10")
  - YYYY-MM-DD (e.g., "2025-01-01", "2025-01-10")
  - M-D-YYYY (e.g., "1-1-2025", "1-10-2025")
  - M/D/YY (e.g., "1/1/25", "12/10/25")
- **Timezone Issues**: Fixed timezone-related bugs in date comparison logic by using explicit date construction instead of string parsing
- **Date Validation**: Improved date validation to properly handle edge cases and invalid dates (e.g., Feb 30, month 13, day 32)
- **Consistency**: Updated all date-related functions to use consistent parsing and validation methods

### Changed
- **Error Handling**: Enhanced error handling in date parsing functions with better console warnings for debugging
- **Performance**: Improved date parsing performance with more efficient regex patterns and validation logic
- **Validation Flow**: Modified validation flow to avoid blocking dates that cannot be parsed, letting browser handle basic format validation

### Technical Details
- Updated `isDateTooSoon()` function to use normalized date parsing and explicit date object construction
- Fixed `isWeekdayBlocked()` function to handle date parsing consistently
- Enhanced `validateSelectedDate()` function with better error handling for unparseable dates
- Improved `getBlockedDateMessage()` function to use consistent date parsing
- Added comprehensive date format validation in `normalizeDateString()`
- Fixed `formatDateForComparison()` function with null checks and error handling
- **Datepicker Improvements**: Removed problematic day customization in `customizeVisibleDatepicker()` that was hiding days 1-9
- **Event Handling**: Added delay to change event validation to prevent interference with field population
- **Error Handling**: Enhanced `beforeShowDay` function with better error handling and fallbacks
- **Debugging**: Added comprehensive logging throughout validation chain for troubleshooting (removed in final version)
- **Smart Format Detection**: Added `tryDateInterpretation()` helper function that tries DD/MM/YYYY format first, then falls back to MM/DD/YYYY
- **Ambiguous Date Handling**: Enhanced date parser to handle ambiguous formats like "02/10/2025" by trying both European (DD/MM/YYYY) and American (MM/DD/YYYY) interpretations

## [1.0.0] - 2024-09-15

### Added
- Initial release of PaxRank Gravity Forms Date Blocker
- Block specific dates globally, per form, or per field
- Advance booking restrictions (require X days advance notice)
- Weekday restrictions (block specific days of the week)
- Support for HTML5 date inputs and jQuery datepicker
- Server-side validation as backup to client-side blocking
- Admin interface for managing blocked dates and restrictions
- Multi-language support (Spanish)
- Comprehensive database structure for flexible date blocking rules