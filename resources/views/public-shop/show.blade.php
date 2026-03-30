@extends('layouts/blankLayout')

@section('title', $team->getDecodedBusinessConfig()['business_name'] ?? $team->name)

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<style>
.assistant-content p { margin-bottom: 0.5rem; }
.assistant-content p:last-child { margin-bottom: 0; }
.assistant-content ul, .assistant-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
.assistant-content li { margin-bottom: 0.25rem; }
.assistant-content strong { font-weight: 600; }
.assistant-content h2, .assistant-content h3 { font-size: 1rem; margin: 0.75rem 0 0.5rem; }
.assistant-demo-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
.assistant-demo-row { flex: 1; display: flex; flex-wrap: nowrap; min-height: 0; }
.assistant-demo-row .col-lg-7 { min-height: 0; display: flex; flex-direction: column; }
.assistant-demo-row .col-lg-5 { min-height: 0; display: flex; flex-direction: column; }
.assistant-demo-left { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.assistant-chat-wrapper { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.assistant-chat-wrapper .card { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.assistant-chat-wrapper .card .card-body { flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.assistant-chat-wrapper .card .card-body > .public-shop-scroll-messages { flex: 1; min-height: 0; overflow: auto; max-height: none !important; }
.assistant-demo-right { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.public-shop-cart-panel { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.public-shop-cart-panel .card { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.public-shop-cart-panel .card-body { flex: 1; min-height: 0; overflow: auto; }
</style>
@endsection

@section('content')
@livewire('public-shop.shopping-assistant', ['slug' => $slug], key('public-shop-'.$slug))
@endsection
