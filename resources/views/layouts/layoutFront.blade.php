@php
$configData = Helper::appClasses();
$isFront = true;
$includeSharePreview = true;
@endphp

@section('layoutContent')

@extends('layouts/commonMaster' )

@include('layouts/sections/navbar/navbar-front')

<!-- Sections:Start -->
@yield('content')
<!-- / Sections:End -->

@endsection
