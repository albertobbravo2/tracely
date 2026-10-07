@props(['url'])
{{-- Marca de design.md (patrón de navbar): cuadrado de radio 9 en `primary` con
     el isotipo (la caja de `x-app-logo-icon`), y el wordmark a su derecha.

     El isotipo va como PNG alojado (public/images/mail-logo.png, la caja en
     blanco sobre el mismo #2549E6 de la celda, opaco y a 3x) y no como SVG en
     línea: Gmail y Outlook descartan el SVG. No se incrusta como adjunto (CID) porque estas plantillas
     se pintan como componentes y no reciben `$message`. La URL sale de APP_URL,
     así que en producción tiene que ser pública. Si el cliente bloquea las
     imágenes queda el cuadrado de marca sólido, que sigue leyéndose como la
     marca. Si cambia el logotipo, hay que regenerar también ese PNG. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<table cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td class="brand-mark" width="32" height="32" align="center" valign="middle">
<img src="{{ asset('images/mail-logo.png') }}" width="17" height="17" alt="" style="display: block; margin: 0 auto; border: 0;">
</td>
<td class="brand-name" valign="middle">{!! $slot !!}</td>
</tr>
</table>
</a>
</td>
</tr>
