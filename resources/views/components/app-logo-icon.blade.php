{{-- Isotipo de Tracely: el icono `package` de Lucide (una caja). Es de trazo, no
     de relleno, así que quien lo use no debe ponerle `fill-current`: el color
     sale de `currentColor` vía `stroke`. Si cambia, hay que rehacer también
     public/favicon.svg (y de ahí favicon.ico y apple-touch-icon.png) y el PNG de
     la cabecera de los correos, public/images/mail-logo.png. --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z" />
    <path d="M12 22V12" />
    <path d="M3.3 7 12 12l8.7-5" />
    <path d="m7.5 4.27 9 5.15" />
</svg>
