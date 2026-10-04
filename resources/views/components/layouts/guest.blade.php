@props(['title' => null, 'bodyClass' => null])

@include('layouts.guest', ['slot' => $slot, 'title' => $title, 'bodyClass' => $bodyClass])
