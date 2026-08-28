@extends('layouts.landing')

{{--
    The landing page is assembled from whichever body sections are switched on,
    in the order set in the admin panel. Nothing here is hard-coded: turning the
    gallery off in Filament removes the section, its navbar anchor and its
    reveal observers in one step.
--}}

@section('content')
    @foreach ($landing->orderedBodyKeys() as $key)
        @if (view()->exists("landing.sections.{$key}"))
            @include("landing.sections.{$key}")
        @endif
    @endforeach
@endsection
