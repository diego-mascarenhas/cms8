@extends('layouts.layoutMaster')

@section('content')
    <h1>Archivo Subido</h1>

    @if($fileName)
        <p>Nombre del archivo: <strong>{{ $fileName }}</strong></p>
    @else
        <p>No se encontró el nombre del archivo.</p>
    @endif
@endsection 