@props(['url'])
{{-- Marca de design.md (patrón de navbar): cuadrado de radio 9 en `primary` con
     el isotipo, y el wordmark a su derecha. El SVG en línea lo respetan Apple
     Mail y iOS; los clientes que lo descartan (Gmail) dejan el cuadrado de
     marca sólido, que sigue leyéndose como la marca.

     El trazado es una copia literal del de `x-app-logo-icon` (el mismo que pinta
     `x-brand-mark` en la navbar y en el panel de autenticación): en un correo no
     se puede incluir un componente Blade de la app porque el HTML tiene que
     quedar plano y autocontenido. Si cambia el logotipo, hay que cambiarlo
     también aquí. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<table cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td class="brand-mark" width="32" height="32" align="center" valign="middle">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" width="17" height="18" aria-hidden="true">
<path fill="#FFFFFF" fill-rule="evenodd" clip-rule="evenodd" d="M17.2 5.633 8.6.855 0 5.633v26.51l16.2 9 16.2-9v-8.442l7.6-4.223V9.856l-8.6-4.777-8.6 4.777V18.3l-5.6 3.111V5.633ZM38 18.301l-5.6 3.11v-6.157l5.6-3.11V18.3Zm-1.06-7.856-5.54 3.078-5.54-3.079 5.54-3.078 5.54 3.079ZM24.8 18.3v-6.157l5.6 3.111v6.158L24.8 18.3Zm-1 1.732 5.54 3.078-13.14 7.302-5.54-3.078 13.14-7.3v-.002Zm-16.2 7.89 7.6 4.222V38.3L2 30.966V7.92l5.6 3.111v16.892ZM8.6 9.3 3.06 6.222 8.6 3.143l5.54 3.08L8.6 9.3Zm21.8 15.51-13.2 7.334V38.3l13.2-7.334v-6.156ZM9.6 11.034l5.6-3.11v14.6l-5.6 3.11v-14.6Z" />
</svg>
</td>
<td class="brand-name" valign="middle">{!! $slot !!}</td>
</tr>
</table>
</a>
</td>
</tr>
