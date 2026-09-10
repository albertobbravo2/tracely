{{-- Respaldo para los códigos que no tienen su propia vista (402, 405, ...).
     Las plantillas de Laravel hacen `@extends('errors::minimal')` y solo
     rellenan la sección `code`, así que se recoge de ahí y el texto se pone en
     castellano en vez de heredar el genérico en inglés del framework. --}}
@php
    $code = trim($__env->yieldContent('code')) ?: '500';
@endphp

<x-error-page
    :code="$code"
    :description="__('No hemos podido completar la petición. Vuelve a intentarlo y, si sigue pasando, avísanos.')"
/>
