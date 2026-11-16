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

Sigue: Etapa 5, el PDF con el QR de ARCA, el panel y la revisión mobile.

> **Para probar contra ARCA de verdad hace falta la clave fiscal de una persona**: generar el
> pedido acá, subirlo a WSASS y traer el certificado. No se puede automatizar.
