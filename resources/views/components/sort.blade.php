{{-- Sortable table header: <x-sort col="name">Name</x-sort> --}}
@props(['col'])
@php
  $on = request('sort') === $col;
  $dir = $on && request('dir') === 'asc' ? 'desc' : 'asc';
@endphp
<th {{ $attributes }}><a class="inline-flex items-center gap-1 hover:text-navy {{ $on ? 'text-navy' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $col, 'dir' => $dir, 'page' => null]) }}">{{ $slot }}<span class="text-[10px]">{{ $on ? (request('dir') === 'asc' ? '▲' : '▼') : '↕' }}</span></a></th>
