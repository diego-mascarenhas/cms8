@extends('layouts/blankLayout')

@section('title', 'Gracias')

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<style>
    .success-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 2rem;
        display: block;
    }
    .success-message {
        text-align: center;
        color: #566a7f;
    }
    .success-title {
        font-size: 2.5rem;
        margin-bottom: 1.5rem;
        color: #566a7f;
    }
</style>
@endsection

@section('content')
<div class="authentication-wrapper authentication-basic px-4">
    <div class="authentication-inner py-4">
        <div class="card">
            <div class="card-body">
                <div class="success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h1 class="success-title text-center">¡Gracias!</h1>
                <p class="success-message">
                    En menos de 48 hs recibirás noticias nuestras 😉
                </p>
            </div>
        </div>
    </div>
</div>
@endsection 