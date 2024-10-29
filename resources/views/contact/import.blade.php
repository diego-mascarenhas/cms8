@extends('layouts.layoutMaster')

@section('content')
    <h1>Archivo Subido</h1>

    @if(session('fileName'))
        <p>Nombre del archivo: <strong>{{ session('fileName') }}</strong></p>
    @else
        <p>No se encontró el nombre del archivo.</p>
    @endif
@endsection 