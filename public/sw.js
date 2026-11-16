/*
 * Service worker de FacturAR.
 *
 * Propio y no de `vite-plugin-pwa` a propósito, con dos reglas:
 *
 *   1. **Los datos no se cachean.** Una factura, un CAE o un estado viejo
 *      mostrados sin conexión son peores que un cartel de "sin conexión": la
 *      app no sirve para facturar sin ARCA del otro lado. Solo se guarda el
 *      andamiaje: el bundle y los íconos.
 *
 *   2. **`cache-first` únicamente para URLs con hash de contenido** (las de
 *      `/build/`). Cualquier URL fija —íconos, manifest— va por red primero,
 *      o queda congelada para siempre: el `activate` solo borra cachés con
 *      OTRO nombre, así que una entrada vieja bajo la misma URL no la limpia
 *      nadie.
 *
 * Al cambiar los íconos hay que subir CACHE acá, el `?v=` de app.blade.php y
 * el `?v=` del manifest. Los tres, o se sigue viendo el ícono viejo.
 */

const CACHE = 'facturar-v1';

// Lo mínimo para que la pantalla de "sin conexión" pueda dibujarse sin red.
const BASICOS = [
    '/offline',
    '/manifest.webmanifest',
    '/icons/icon-192.png?v=1',
];

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(BASICOS))
            .catch(() => {
                // Si alguno no está, el service worker se instala igual: no
                // vale la pena dejar la app sin SW por una precarga.
            }),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((nombres) =>
                Promise.all(
                    nombres
                        .filter((nombre) => nombre !== CACHE)
                        .map((nombre) => caches.delete(nombre)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

/**
 * ¿Es una navegación de verdad, de las que pintan un documento?
 *
 * ⚠️ NO alcanza con mirar el header `Accept`: el router de Inertia manda
 * `Accept: text/html, application/xhtml+xml` en sus XHR, así que ese chequeo
 * da `true` para cada navegación interna de la SPA.
 */
function esNavegacion(request) {
    return request.mode === 'navigate';
}

/** Solo estas URLs llevan hash de contenido en el nombre. */
function tieneHashDeContenido(url) {
    return url.pathname.startsWith('/build/');
}

/*
 * Red de seguridad para el JSON crudo de Inertia (ver HandleInertiaRequests y
 * la skill inertia-json-crudo): si una navegación de documento vuelve con la
 * respuesta JSON de Inertia —porque una caché la confundió con el HTML—, se
 * pide de nuevo el HTML. Si la reobtención viene redirigida (sesión vencida),
 * se sigue el redirect a mano: el Service Worker API no deja entregar una
 * respuesta redirigida a una navegación.
 */
function rescatarJsonCrudo(request, respuesta) {
    if (!respuesta.headers.get('x-inertia')) {
        return respuesta;
    }

    return fetch(request.url, {
        cache: 'reload',
        headers: { Accept: 'text/html' },
    }).then((recuperada) =>
        recuperada.redirected
            ? Response.redirect(recuperada.url, 302)
            : recuperada,
    );
}

self.addEventListener('fetch', (evento) => {
    const { request } = evento;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // El bundle: cache-first, porque el nombre cambia cuando cambia el archivo.
    if (tieneHashDeContenido(url)) {
        evento.respondWith(
            caches.match(request).then(
                (guardada) =>
                    guardada ??
                    fetch(request).then((respuesta) => {
                        if (respuesta.ok) {
                            const copia = respuesta.clone();
                            caches
                                .open(CACHE)
                                .then((cache) => cache.put(request, copia));
                        }

                        return respuesta;
                    }),
            ),
        );

        return;
    }

    // Una navegación sin red muestra la pantalla de "sin conexión".
    if (esNavegacion(request)) {
        evento.respondWith(
            fetch(request)
                .then((respuesta) => rescatarJsonCrudo(request, respuesta))
                .catch(() =>
                    caches.match('/offline').then((r) => r ?? Response.error()),
                ),
        );

        return;
    }

    /*
     * Todo lo demás: red primero, y la caché solo como respaldo si no hay red.
     * Se guarda únicamente lo estático de URL fija (íconos, manifest). Las
     * respuestas de la app y los PDF de las facturas **no se guardan**.
     */
    evento.respondWith(
        fetch(request)
            .then((respuesta) => {
                const esEstatico =
                    url.pathname.startsWith('/icons/') ||
                    url.pathname === '/manifest.webmanifest';

                if (respuesta.ok && esEstatico) {
                    const copia = respuesta.clone();
                    caches
                        .open(CACHE)
                        .then((cache) => cache.put(request, copia));
                }

                return respuesta;
            })
            .catch(() =>
                caches.match(request).then((r) => r ?? Response.error()),
            ),
    );
});
