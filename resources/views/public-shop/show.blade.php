@extends('layouts/blankLayout')

@section('title', $team->getDecodedBusinessConfig()['business_name'] ?? $team->name)

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<style>
.assistant-content h1,
.assistant-content h2,
.assistant-content h3,
.assistant-content h4,
.assistant-content h5,
.assistant-content h6 {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.35;
    margin: 0.65rem 0 0.35rem;
}
.assistant-content h1 { font-size: 1.05rem; }
.assistant-content h2 { font-size: 1rem; }
.assistant-content h3,
.assistant-content h4,
.assistant-content h5,
.assistant-content h6 { font-size: 0.95rem; }
.assistant-content h1:first-child,
.assistant-content h2:first-child,
.assistant-content h3:first-child {
    margin-top: 0;
}
.assistant-content p { margin-bottom: 0.5rem; }
.assistant-content p:last-child { margin-bottom: 0; }
.assistant-content ul, .assistant-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
.assistant-content li { margin-bottom: 0.25rem; }
.assistant-content strong { font-weight: 600; }
.assistant-content hr {
    margin: 0.65rem 0;
    opacity: 0.25;
}
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
