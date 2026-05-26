@extends('layouts/layoutMaster')

@section('title', __('humano_pricing.page_title'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/front-page-pricing.css') }}" />
@endsection

@section('page-script')
<script src="{{ asset('assets/js/front-page-pricing.js') }}"></script>
@endsection

@section('content')
<section class="section-py">
  <div class="container">
    @include('content.front-pages.partials.humano-pricing-plans', [
      'plans' => $plans,
      'showPageHeader' => true,
      'showFlashAlerts' => true,
    ])
  </div>
</section>
@endsection
