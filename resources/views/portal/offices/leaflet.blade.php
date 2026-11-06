@push('head')
{{-- Leaflet 1.9.4 self-hosted. ponytail: OSM tiles are for development only; switch to a commercial tile provider before production. --}}
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
@endpush
