{{-- Inlined, so the icon needs no public asset or web server route. --}}
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(resource_path('nova/favicon.svg'))) }}">
