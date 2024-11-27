@extends('layouts/blankLayout')

@section('title', 'Conocer Humano.app')

@section('page-style')
{{-- Page Css files --}}
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/css/pages/page-auth.css')) }}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-basic px-4">
    <div class="authentication-inner py-4">
        <!-- Logo -->
        <div class="app-brand mb-4">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                <span
                    class="app-brand-logo demo">@include('_partials.macros', ["height" => 20, "withbg" => 'fill: #fff;'])</span>
            </a>
        </div>
        <!-- /Logo -->
        <div class="card">
            <div class="card-body">
                <h4 class="mb-2">¡Quiero escalar mi negocio hasta la luna y más allá!</h4>
                <p class="mb-4" style="color: #566a7f;">Rellena este formulario para hacerlo realidad</p>
                <form method="POST" action="{{ route('lead.store') }}">
                    @csrf
                    <input type="hidden" name="team_id" value="3">
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-fullname">Nombre</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="basic-default-fullname" name="name" value="{{ old('name') }}" />
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-email">Email</label>
                        <input type="email" id="basic-default-email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               name="email" value="{{ old('email') }}" />
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="basic-default-phone">Teléfono</label>
                        <input type="text" id="basic-default-phone" 
                               class="form-control phone-mask @error('phone') is-invalid @enderror" 
                               name="phone" value="{{ old('phone') }}" />
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary d-grid w-100">Enviar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection