<x-legal-page :title="__('Política de cookies')" :updated="__('13 de septiembre de 2026')">
    <p>
        {{ __('Una cookie es un pequeño fichero que un sitio web guarda en tu navegador cuando lo visitas. Esta página explica qué cookies utiliza Tracely, para qué sirven y cómo puedes eliminarlas.') }}
    </p>

    <p>
        <strong>{{ __('Tracely no utiliza cookies de analítica, de publicidad ni de perfilado, ni propias ni de terceros.') }}</strong>
        {{ __('Todas las que se describen aquí son necesarias para que el servicio funcione.') }}
    </p>

    <h2>{{ __('Cookies que utilizamos') }}</h2>

    {{-- Tabla con el patrón de design.md → Tabla: cabecera sobre `surface-2`,
         etiquetas de 12 px en `ink-muted`, filas separadas por 1 px y sin
         bordes verticales. Los nombres y duraciones salen de la configuración
         real de la aplicación para que no se queden desfasados. --}}
    <div class="overflow-x-auto rounded-xl border border-line">
        <table class="w-full min-w-[36rem] border-collapse text-start text-sm">
            <thead class="bg-surface-2">
                <tr>
                    @foreach ([__('Nombre'), __('Titular'), __('Finalidad'), __('Duración')] as $header)
                        <th class="px-4 py-3 text-start text-xs font-semibold text-ink-muted">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ([
                    [
                        'name' => config('session.cookie'),
                        'purpose' => __('Identifica tu sesión para que la aplicación recuerde quién eres entre una página y la siguiente.'),
                        'duration' => __(':minutos minutos', ['minutos' => config('session.lifetime')]),
                    ],
                    [
                        'name' => 'XSRF-TOKEN',
                        'purpose' => __('Protege los formularios frente a la falsificación de peticiones (CSRF).'),
                        'duration' => __(':minutos minutos', ['minutos' => config('session.lifetime')]),
                    ],
                    [
                        'name' => 'remember_web_*',
                        'purpose' => __('Solo se crea si marcas «Recuérdame» al iniciar sesión: te mantiene identificado cuando vuelves.'),
                        'duration' => __('400 días'),
                    ],
                ] as $cookie)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-mono text-[13px] text-ink">{{ $cookie['name'] }}</td>
                        <td class="px-4 py-3 text-ink-2">{{ __('Tracely (propia)') }}</td>
                        <td class="px-4 py-3 text-ink-2">{{ $cookie['purpose'] }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-ink-2">{{ $cookie['duration'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>{{ __('Otra información guardada en tu navegador') }}</h2>

    <p>
        {{ __('Además de las cookies, Tracely guarda en el almacenamiento local de tu navegador (localStorage) la clave «flux.appearance», con el tema que hayas elegido: claro u oscuro. No se envía a ningún servidor, no identifica a nadie y permanece hasta que borras los datos del sitio.') }}
    </p>

    <h2>{{ __('Por qué no te pedimos consentimiento') }}</h2>

    <p>
        {{ __('Todas las cookies de la tabla son técnicas o estrictamente necesarias: sin ellas no es posible iniciar sesión ni enviar un formulario con seguridad. El artículo 22.2 de la LSSI exime a este tipo de cookies del deber de obtener consentimiento previo, así que Tracely no muestra un banner de cookies.') }}
    </p>

    <p>
        {{ __('Si en el futuro se añadieran cookies de analítica o de terceros, dejarían de estar exentas: habría que pedirte consentimiento antes de instalarlas y actualizar esta página.') }}
    </p>

    <h2>{{ __('Servicios de terceros') }}</h2>

    <p>
        {{ __('Las tipografías de la interfaz se cargan desde Google Fonts. Google no instala cookies al servirlas, pero sí recibe la dirección IP de tu navegador para poder entregar los archivos.') }}
    </p>

    <h2>{{ __('Cómo eliminar o bloquear las cookies') }}</h2>

    <p>
        {{ __('Puedes borrar las cookies ya guardadas y bloquear las nuevas desde la configuración de tu navegador. Cada navegador lo explica en su propia ayuda: Chrome, Firefox, Safari, Edge y Opera tienen una sección de privacidad donde hacerlo.') }}
    </p>

    <p>
        {{ __('Ten en cuenta que, si bloqueas las cookies de este sitio, no podrás iniciar sesión ni usar el panel. La consulta pública de un envío con su número de guía sí seguirá funcionando.') }}
    </p>

    <h2>{{ __('Cambios en esta política') }}</h2>

    <p>
        {{ __('Esta política se actualizará siempre que cambien las cookies que utilizamos. Si tienes cualquier duda, puedes escribirnos a la dirección de contacto que figura en el aviso legal.') }}
    </p>
</x-legal-page>
