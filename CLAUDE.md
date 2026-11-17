# FacturAR — convenciones del proyecto

Facturación electrónica para **monotributistas** contra los web services de **ARCA** (ex AFIP).
Cada cuenta es un emisor con su propio CUIT, su certificado y sus puntos de venta (SaaS
multiusuario). La v1 emite **Factura C** (productos, servicios o ambos), obtiene el CAE y
genera el PDF con el QR que exige ARCA.

El plan de trabajo por etapas vive fuera del repo. Ante una discrepancia entre ese plan y
este archivo, **manda este archivo**: recoge lo que efectivamente se decidió al implementar.

> **El repo es público.** Acá no van host, usuario ni puerto de SSH, ni credenciales, ni
> CUIT, certificados o claves de nadie. Los datos de conexión al hosting viven en la skill
> `deploy-hostinger`, a nivel usuario. Los fixtures de ARCA se guardan sin token ni sign.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8 · Inertia 3 · Vue 3 + TypeScript · Tailwind 4 · Vite
Auth por **Fortify** (2FA y passkeys ya cableados por el starter kit).
Rutas tipadas con **Wayfinder**, no con Ziggy.
Tests con **Pest 4** (PHPUnit 12 — no subir a Pest 5, que exige PHPUnit 13 y rompe el kit).
PHPStan nivel 7 con Larastan.
Integración con ARCA **propia**, sobre `SoapClient` + `openssl_pkcs7_sign` (requiere
`ext-soap` y `ext-openssl`). No se usa `afipsdk/afip.php`: desde la 1.0 exige un token pago
para producción.

Sin Pinia ni Vue Router: el estado viaja en props de Inertia y las rutas las define Laravel.

> El `laravel/vue-starter-kit` de Packagist está **desactualizado**. Este proyecto se armó
> clonando la rama principal del repo de GitHub. Si hay que rearmar el andamiaje, no uses
> `composer create-project`.

## Idioma

- Todo el texto visible va en **español rioplatense, con voseo**. Nada de "usted".
- Tablas, columnas, modelos, rutas y servicios **en español**.
- **Excepción:** `users` y sus columnas base quedan como las genera el starter kit. Las
  tablas de dominio usan `usuario_id` apuntando a `users.id`.
- **Excepción 2:** los nombres de ARCA se respetan tal cual en el borde del wire
  (`CbteTipo`, `PtoVta`, `FECAESolicitar`, `CondicionIVAReceptorId`). Adentro de la app, el
  dominio va en español (`tipo`, `punto_de_venta`, `condicion_iva`).
- No hay capa de i18n en el frontend. `laravel-lang` traduce en "usted": si se regeneran los
  `lang/es`, hay que volver a pasar `auth.php`, `passwords.php` y `es.json` a voseo.

## Fechas

- Se persiste **siempre en UTC**. `config/app.php` tiene `timezone` fijo en `'UTC'`.
- **Lo fiscal sale de `RelojFiscal`**, en `America/Argentina/Buenos_Aires`: la fecha del
  comprobante, el período de servicio, el vencimiento de pago y el TRA de WSAA. Nunca
  `now()->format('Ymd')` suelto: a las 21 h de Buenos Aires ya es mañana en UTC, y ARCA
  rechaza un `CbteFch` corrido.
- Las fechas fiscales son columnas `date`. Al wire van como `YYYYMMDD` y vuelven igual
  (`CAEFchVto`): el formato vive solo en `RelojFiscal::ymd()` y `RelojFiscal::desdeYmd()`.

## Dinero

- **Todos los importes en centavos**, `bigInteger`. Nunca `float` ni `decimal` para plata.
- Al wire: `number_format($centavos / 100, 2, '.', '')`. ARCA valida
  `ImpTotal = ImpNeto + ImpTrib` y un flotante lo rompe.
- En el front, `resources/js/lib/moneda.ts` formatea en `es-AR` con 2 decimales.

## Backend

- Un **FormRequest por escritura**, una **Policy por modelo** (todo se filtra por el emisor
  del usuario), la lógica en **servicios**, los controladores solo orquestan.
- **Enums de PHP** para todo lo que tenga casos fijos, guardados en columnas **`string`**, no
  ENUM de MySQL: sqlite no valida ENUM, así que un caso nuevo pasaría los tests y reventaría
  recién en producción al primer guardado (lección de misalud).
- Eager loading explícito; soft deletes en las tablas de dominio.
- **Secretos con el cast `encrypted`** en columnas `text`: la clave privada del certificado y
  el token y el sign del ticket de acceso. Nada más se cifra: el resto necesita `WHERE`,
  `UNIQUE` y `MAX()`. La clave privada nunca es fillable, nunca va a una prop de Inertia y
  nunca se descarga.

## ARCA

> Esta sección resume reglas que no dan ningún síntoma hasta que ARCA rechaza algo en
> producción. El detalle de cada una está en el plan; acá van las que no se pueden olvidar.

- **Servicios**: WSAA (`LoginCms`) para el ticket de acceso y WSFEv1 para facturar. El TRA
  pide el servicio **`wsfe`**, no `wsfev1`.
- **Ambientes**: homologación y producción tienen certificados distintos, puntos de venta
  distintos y numeración distinta. Todo lo que dependa del ambiente lo guarda como columna.
- **Ticket de acceso**: dura 12 h y ARCA **niega uno nuevo mientras el anterior está vigente**.
  Vive en la tabla `tickets_de_acceso`, no en el cache: un `cache:clear` en un deploy lo
  perdería y dejaría al emisor sin facturar hasta 12 h. Esa fila no se borra nunca.
- **Numeración**: la asigna el emisor con `FECompUltimoAutorizado + 1`, bajo un lock por
  punto de venta y con un UNIQUE en la base como última defensa.
- **Emisión síncrona** en el request, nunca dentro de una transacción de base de datos
  abierta durante la llamada SOAP.
- **Timeout después de enviar**: el comprobante pudo quedar autorizado en ARCA. Queda
  `Pendiente` y se resuelve con `FECompConsultar`; ese punto de venta no emite hasta resolverlo.
- **Factura C**: sin nodo `Iva`, `ImpIVA = 0`, `MonId = PES`, `MonCotiz = 1`.
  **`CondicionIVAReceptorId` va siempre** (obligatorio desde el 1/12/2026).
- **Consumidor final**: hasta el tope vigente no se identifica al receptor (`DocTipo 99`,
  `DocNro 0`). El tope está en `config/arca.php`, no hardcodeado.
- **WSDL locales** en `resources/arca/wsdl/`; el endpoint se fija con `location` por ambiente.
  Un solo WSDL de WSAA sirve para los dos ambientes: el elemento del pedido usa el mismo
  namespace (`http://wsaa.view.sua.dvadac.desein.afip.gov`) en homologación y producción.
- **OpenSSL siempre con `config`**: toda llamada `openssl_pkey_new`, `openssl_pkey_export` y
  `openssl_csr_new` lleva `GeneradorDeCertificados::opcionesOpenSsl()`, que apunta a
  `resources/arca/openssl.cnf`. En Windows PHP no encuentra ningún `openssl.cnf` y esas
  funciones devuelven `false` sin un mensaje útil. `openssl_csr_new` recibe la clave **por
  referencia**: pasale una variable, no el resultado de una función.
- **Certificados**: la app genera la clave y el CSR; la persona solo sube el CSR a ARCA y trae
  el certificado. Al cargarlo se valida que corresponda a la clave, al CUIT
  (`serialNumber=CUIT …`) y al ambiente (el emisor del certificado es "Computadores Test" en
  homologación y "Computadores" en producción). Al renovar, el anterior sigue vigente hasta
  que llega el nuevo.
- **Puntos de venta**: se cargan a mano. En producción tienen que existir en ARCA con el
  sistema "Factura Electrónica - Monotributo - Web Services"; en homologación no hace falta.
- `php artisan arca:diagnostico` dice si el servidor puede hablar con ARCA (extensiones,
  OpenSSL, WSDL, directorio temporal, salida a los cuatro endpoints y desvío del reloj).
  Se corre en cada deploy.
- **Horas que manda ARCA → `->utc()` antes de guardarlas.** Vienen con `-03:00` y Eloquent
  guarda la hora tal cual, sin convertir: el vencimiento del ticket quedaba tres horas antes
  y se renovaba antes de tiempo. Lo cubre `ProveedorDeTicketsTest` releyendo de la base.
- **El WSFE de producción no conecta con OpenSSL 3 sin bajar el nivel de seguridad.**
  `servicios1.afip.gov.ar` negocia una clave Diffie-Hellman que OpenSSL 3 rechaza en el
  handshake ("Could not connect to host"). En Laragon no pasa; en Hostinger (OpenSSL 3.5)
  sí, medido el 16/11/2026. `config('arca.cifrados_tls')` (`DEFAULT@SECLEVEL=1`) va en el
  `stream_context` de cada SoapClient, solo para ARCA, y `arca:diagnostico` prueba con los
  mismos cifrados: si no, marcaría falla donde facturar anda.
- **WSAA de homologación tarda 10 s en contestar un error** (medido también con `curl`: es
  del servidor, no de PHP). El timeout de lectura (`config/arca.php`, 25 s) tiene que seguir
  siendo mayor.
- **Los errores de WSFE vienen adentro de la respuesta** (`Errors`), no como SOAP Fault.
  `ClienteWsfe::llamar()` los convierte en `ErrorDeWsfe`; el 602 ("sin resultados") lo trata
  cada método como lista vacía o `null`, no como error.
- Cada llamada queda en `llamadas_arca` con token y sign tachados
  (`RegistradorDeLlamadas::tachar`), y se purga con `model:prune` a los 90 días.

### Emisión

- **`EmisorDeComprobantes` es el único que cambia el estado** de un comprobante hacia
  Pendiente, Autorizado, Rechazado o Error. Cada resultado posible de ARCA tiene su test en
  `EmisionTest`.
- Un comprobante **Autorizado no se modifica ni se borra**: lo impiden la Policy y un
  `saving`/`deleting` en el modelo (se anula con nota de crédito, en una etapa posterior).
- `Comprobante` declara en `$attributes` los mismos defaults que la migración. Sin eso, un
  comprobante recién creado tenía `moneda` en null en memoria y se mandaba a ARCA sin
  `MonId`: los defaults de la base no llegan al modelo hasta releerlo.
- El receptor se **copia** al comprobante; editar o borrar el cliente no toca la factura.
- `numero` se asigna recién al emitir y vuelve a null en Rechazado o Error: un rechazo no
  consume el número.
- La fecha tiene que estar dentro de ±5 días (productos) o ±10 (servicios) y no ser anterior
  a la última autorizada del punto de venta. Se valida en el FormRequest y otra vez al emitir,
  porque un borrador puede quedar viejo.

### PDF y QR

- El PDF se genera al vuelo y solo para facturas autorizadas: todo lo que lleva está en la base
  y una autorizada no cambia. Lleva la marca "sin validez fiscal" si es de homologación.
- dompdf no soporta flexbox ni grid: `resources/views/pdf/factura-c.blade.php` va con tablas.
  Sin `isFontSubsettingEnabled` embebe la fuente entera y el PDF pasa de 30 KB a casi 900 KB.
- El QR (RG 4892) es `https://www.afip.gob.ar/fe/qr/?p=` + base64 del JSON, con números como
  números (`cuit`, `nroCmp`, `codAut`). A consumidor final sin identificar no se le informa
  receptor. Se probó: el PNG se decodifica y ARCA redirige la URL a su página de constatación.
- `bacon/bacon-qr-code` está declarado en `composer.json` aunque Fortify ya lo trae: lo usamos
  directamente y no conviene depender de una dependencia ajena.

### Términos, privacidad y cookies

- Los textos viven en `resources/legales/*.md`, con `{responsable}`, `{contacto}` y
  `{retencion_llamadas}` que completa `LegalesController::markdown()` desde
  `config/legales.php` (y este, desde el `.env`: el repo es público). Sin configurar, el
  texto muestra «[falta configurar …]» a la vista en vez de quedar vacío.
- **Cada promesa de la política está atada al código**, y cambiar una obliga a cambiar la
  otra y subir `legales.version`:
    - «al eliminar la cuenta se borra todo en el momento»: `User` sin SoftDeletes y las FK en
      cascada; `ProfileController::destroy` borra además las sesiones. Lo prueba
      `TerminosYPrivacidadTest` tabla por tabla.
    - «Descargar mis datos» en Configuración → Perfil: `ExportadorDeDatos`, un ZIP con
      `datos.json` y los PDF. **Nunca lleva la clave privada** (lo prueba el mismo test).
    - el registro de ARCA se borra a los N días: `config('arca.retencion_llamadas_dias')`,
      que es lo mismo que lee el texto.
    - «no hay terceros en el navegador»: las fuentes las sirve la app (plugin de fuentes de
      Vite, sin pedido a Bunny en tiempo de ejecución).
- `AceptoTerminos` va en todo el grupo `web`; las rutas exentas están en
  `AceptoTerminos::EXENTAS` y un test fija la lista exacta de las que tienen sesión.
  Descargar los datos y eliminar la cuenta son exentas: son derechos.
- La `UserFactory` acepta la versión vigente por defecto (`sinAceptarTerminos()` para lo
  contrario): sin eso, toda la suite queda frenada en la pantalla de aceptación.
- El aviso de cookies es **informativo** porque todo lo que se guarda es necesario. La
  página `/cookies` es el inventario completo y `AvisoCookiesTest` lo mantiene: una clave
  nueva en `localStorage`, una cookie o una caché del service worker sin declarar rompe
  la suite. Si algún día entra algo no necesario (analíticas), pasa a consentimiento
  previo: no se carga hasta aceptar, y se sube la versión del aviso.

### Ingreso con Google

- Socialite, portado de MiSalud. Sin `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` en el `.env`
  no hay botón y `/auth/google/*` da 404. El redirect es `APP_URL` + `/auth/google/callback`
  y tiene que estar autorizado en Google Cloud tal cual.
- **El email manda**: si coincide con una cuenta existente se le vincula el `google_id`
  (se busca primero por `google_id`, que no cambia). Se rechaza si Google no dice que el
  email está verificado: si no, cualquiera podría quedarse con el certificado de otro.
- La cuenta nueva nace **sin contraseña** (`password` nullable) y sin términos aceptados:
  `AceptoTerminos` la frena en el primer pedido.
- **El alias `password.confirm` es `ConfirmarClaveSiLaTiene`** (`bootstrap/app.php`): lo
  usan la pantalla de seguridad y las rutas de 2FA y llaves de Fortify, y sin contraseña
  no se podría confirmar nunca. Cambiar y eliminar la cuenta no piden la que no existe
  (`auth.tieneContrasena` en las props compartidas).
- Quien activó el 2FA pasa por el desafío de Fortify también al entrar con Google.
- `google_id` está en `Hidden`: no viaja al navegador.

### Tests de ARCA

- `tests/TestCase.php` registra una fábrica de transportes que **explota** si un test llega
  a ARCA: el que lo necesite llama a `arcaFalso()` (en `tests/Pest.php`) antes de resolver
  cualquier servicio, y prepara respuestas con `RespuestasArca`.
- `SoapClientGrabado` es un `SoapClient` real con el WSDL real que contesta con XML grabado de
  homologación (`tests/Fixtures/arca/`): prueba que el WSDL y la lectura coinciden con lo que
  manda ARCA de verdad.
- Contra homologación real, desde la consola: `arca:dummy`, `arca:ticket`, `arca:ultimo`,
  `arca:consultar` y `arca:parametros`, todos con el emisor por id o CUIT.
- **Tests sin red**: todo pasa por la interfaz `TransporteSoap`; los tests usan dobles y
  fixtures. Contra homologación real se prueba con los comandos `arca:*`.

## Frontend

- Páginas siempre en `resources/js/pages/` (minúscula): Linux distingue mayúsculas y el CI
  falla aunque en Windows ande.
- Reutilizar `components/ui/` (shadcn-vue) antes de crear un componente.
- **Todo aviso al usuario va en un toast** de vue-sonner, disparado desde el layout con un
  `watch` sobre el flash. Nada de carteles dentro de la página.
- `npm run build` después de tocar un `.vue`, antes de verificar en el navegador.
- **Marca**: la fuente es `resources/marca/*.svg`; `npm run generar:iconos` saca de ahí los PNG
  de la PWA y `public/img/facturar-logo.webp` (el logo para documentar). Los PNG no se
  editan a mano.

### Tamaño de letra

- Configuración → Apariencia, primero: Pequeño 87,5 % · **Normal 100 %** (default) · Grande
  112,5 % (`App\Enums\TamanioTexto`). En `%` sobre `<html>`, nunca en px: así se multiplica
  con el tamaño que la persona puso en su navegador. `TamanioTextoTest` ata el CSS al enum.
- Lo escribe el servidor (`data-texto` en `app.blade.php`, vía `HandleTamanioTexto`): sin
  script inline ni parpadeo. Manda la cuenta, después la cookie `tamanio_texto` (sin cifrar,
  declarada en `/cookies`), después el default.
- `PUT /tamanio-texto` no pide sesión y está en `AceptoTerminos::EXENTAS`: quien no puede
  leer los términos tiene que poder agrandar la letra.
- Los breakpoints (`md:`) no escalan con la letra: revisar desbordes como matriz ancho ×
  tamaño, cambiando el tamaño **por el endpoint** (con sesión, la cookie sola no cambia nada).

## Comandos

```bash
composer dev              # servidor, vite, cola y logs
composer ci:check         # TODO lo que corre el CI: formato, lint, tipos, PHPStan y tests
composer lint             # pint
npm run check:fix         # formato y lint del front
php artisan test          # Pest
```

## Estado

Hecho: andamiaje (Laravel 13 + Inertia 3 + Fortify + Wayfinder, MySQL, Pest 4), todo el texto
en español, CI con PHP 8.4 y MySQL. Etapa 1: emisor, certificados (clave + CSR, carga y
validación del certificado de ARCA), puntos de venta, pantalla "Datos fiscales" y
`arca:diagnostico`.

Etapa 2: la capa ARCA (transporte SOAP, TRA y firma, WSAA con ticket persistido y lock,
WSFEv1 para consultas, registro de llamadas, comandos `arca:*` y el botón "Probar la
conexión"). Verificado contra homologación real: `FEDummy` responde y `loginCms` acepta el
formato del TRA y la firma (rechaza solo el certificado autofirmado, como corresponde).

Etapa 3: clientes (CUIT, CUIL o DNI con su verificador, condición frente al IVA coherente con
el documento, búsqueda, restauración de borrados). Consumidor final no es un cliente.

Etapa 4: la emisión de la factura C (borradores con ítems, `EmisorDeComprobantes` con lock
por punto de venta, reintento único ante 10016, consulta tras un corte, `Pendiente` resuelto
por `arca:reconciliar-pendientes` cada 5 minutos, `arca:emitir-prueba` para homologación).

Etapa 5: el PDF de la factura C con el QR de ARCA (dompdf, maquetado con tablas, fuente
DejaVu con subsetting), el panel (configuración pendiente, facturado del mes, vencimiento del
certificado, últimas facturas) y la revisión a 360 px de todas las pantallas.

Etapa 6, local: PWA instalable con marca propia, términos y privacidad con aceptación,
descarga de datos, aviso y página de cookies.

Desplegado en `https://facturar.bambudev.com.ar` (17/11/2026): base, correo, cron de
`schedule:run` y `arca:diagnostico` en orden. Ingreso con Google.

Sigue: el certificado de producción y la primera factura real.

> **Para probar contra ARCA de verdad hace falta la clave fiscal de una persona**: generar el
> pedido acá, subirlo a WSASS y traer el certificado. No se puede automatizar.
