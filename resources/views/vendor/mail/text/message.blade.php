<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
Tracely
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ __('© :year Tracely · Rastreo de envíos', ['year' => date('Y')]) }}

{{ __('Este es un mensaje automático, no hace falta que respondas.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
