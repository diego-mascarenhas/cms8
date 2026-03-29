@extends('layouts/blankLayout')

@section('title', $team->getDecodedBusinessConfig()['business_name'] ?? $team->name)

@section('content')
<div class="authentication-bg min-vh-100 py-3">
    <div class="container-fluid">
        @livewire('public-shop.shopping-assistant', ['slug' => $slug], key('public-shop-'.$slug))
    </div>
</div>
@endsection
