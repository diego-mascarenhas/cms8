{{-- extend layout --}}
@extends('layouts.contentLayoutMaster')

{{-- page title --}}
@section('title','Contactos')

{{-- vendor styles --}}
@section('vendor-style')
<link rel="stylesheet" type="text/css" href="{{asset('vendors/flag-icon/css/flag-icon.min.css')}}">
<link rel="stylesheet" type="text/css" href="{{asset('vendors/data-tables/css/jquery.dataTables.min.css')}}">
<link rel="stylesheet" type="text/css" href="{{asset('vendors/data-tables/extensions/responsive/css/responsive.dataTables.min.css')}}">
@endsection

{{-- page styles --}}
@section('page-style')
<link rel="stylesheet" type="text/css" href="{{asset('css/pages/app-sidebar.css')}}">
<link rel="stylesheet" type="text/css" href="{{asset('css/pages/app-contacts.css')}}">
@endsection

{{-- page content --}}
@section('content')
<!-- Add new contact popup -->
<div class="contact-overlay"></div>
<div style="bottom: 54px; right: 19px;" class="fixed-action-btn direction-top">
  <a class="btn-floating btn-large primary-text gradient-shadow contact-sidebar-trigger" href="#modal1">
    <i class="material-icons">person_add</i>
  </a>
</div>
<!-- Add new contact popup Ends-->
<style>
    .form-check-input[type=checkbox]:indeterminate {
  background-color: #0d6efd;
  border-color: #0d6efd;
  --bs-form-check-bg-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10h8'/%3e%3c/svg%3e");
}

.form-check-input:disabled {
  pointer-events: none;
  filter: none;
  opacity: 0.5;
}

.form-check-input[disabled] ~ .form-check-label, .form-check-input:disabled ~ .form-check-label {
  cursor: default;
  opacity: 0.5;
}
  </style>

<!-- Sidebar Area Starts -->
<div class="sidebar-left sidebar-fixed">
  <div class="sidebar">
    <div class="sidebar-content">
      <div class="sidebar-header">
        <div class="sidebar-details">
          <h5 class="m-0 sidebar-title"><i class="material-icons app-header-icon text-top">perm_identity</i> {{ __('locale.Contacts') }}
          </h5>
          <div class="mt-10 pt-2">
            <p class="m-0 subtitle font-weight-700">{{ __('locale.Total number of contacts') }}</p>
            <p class="m-0 text-muted"><span id="total_registros"></span> {{ __('locale.Contacts') }}</p>
          </div>
        </div>
      </div>
      <div id="sidebar-list" class="sidebar-menu list-group position-relative animate fadeLeft delay-1">
        <div class="sidebar-list-padding app-sidebar sidenav" id="contact-sidenav">
          <ul class="contact-list display-grid">
            <li class="sidebar-title">{{ __('locale.Filters') }}</li>
            <li class="active"><a href="javascript:void(0)" class="text-sub"><i class="material-icons mr-2">
                  perm_identity </i> {{ __('locale.All Contacts') }}</a></li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="material-icons mr-2"> history </i> {{ __('locale.Frequent') }}</a>
            </li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="material-icons mr-2"> star_border </i> {{ __('locale.Shared Contacts') }}</a></li>
            <li class="sidebar-title">{{ __('locale.Options') }}</li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="material-icons mr-2"> keyboard_arrow_down </i>
                {{ __('locale.Import') }}</a></li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="material-icons mr-2"> keyboard_arrow_up </i>
                {{ __('locale.Export') }}</a></li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="material-icons mr-2"> print </i> Print</a></li>
            <li class="sidebar-title">{{ __('locale.Department') }}</li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="purple-text material-icons small-icons mr-2">
                  fiber_manual_record </i> {{ __('locale.Engineering') }}</a></li>
            <li><a href="javascript:void(0)" class="text-sub"><i class="amber-text material-icons small-icons mr-2">
                  fiber_manual_record </i> {{ __('locale.Sales') }}</a></li>
            <li><a href="javascript:void(0)" class="text-sub"><i
                  class="light-green-text material-icons small-icons mr-2">
                  fiber_manual_record </i> {{ __('locale.Support') }}</a></li>
          </ul>
        </div>
      </div>
      <a href="#" data-target="contact-sidenav" class="sidenav-trigger hide-on-large-only"><i
          class="material-icons">menu</i></a>
    </div>
  </div>
</div>
<!-- Sidebar Area Ends -->

<!-- Content Area Starts -->
<div class="content-area content-right">
  <div class="app-wrapper">
    <div class="datatable-search">
      <i class="material-icons mr-2 search-icon">search</i>
      <input type="text" placeholder="{{ __('locale.Search Contact') }}" class="app-filter" id="global_filter">
    </div>
    <div id="button-trigger" class="card card card-default scrollspy border-radius-6 fixed-width">
      <div class="card-content p-0">
        <table id="data-table-contact" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>{{ __('locale.Name') }}</th>
                    <th className="dt-center">{{ __('locale.Phone') }}</th>
                    <th>{{ __('locale.Email') }}</th>
                    <th>{{ __('locale.Confirmed') }}</th>
                    <th className="dt-center">{{ __('locale.Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!-- Content Area Ends -->

<!--  Contact sidebar -->
<div class="contact-compose-sidebar">
  <div class="card quill-wrapper">
    <div class="card-content pt-0">
      <div class="card-header display-flex pb-2">
        <h3 class="card-title contact-title-label">{{ __('locale.Create New Contact') }}</h3>
        <div class="close close-icon">
          <i class="material-icons">close</i>
        </div>
      </div>
      <div class="divider"></div>
      <!-- form start -->
      <form id="contact-form" method="POST" class="edit-contact-item mb-5 mt-5">
        @method('POST')
        @csrf
        <div class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix"> perm_identity </i>
            <input id="name" type="text" class="validate">
            <label for="name">{{ __('locale.Name') }}</label>
          </div>
          <div class="input-field col s12">
            <i class="material-icons prefix"> perm_identity </i>
            <input id="lastname" type="text" class="validate">
            <label for="lastname">{{ __('locale.Last Name') }}</label>
          </div>
          <div class="input-field col s12">
            <i class="material-icons prefix"> business </i>
            <input id="company" type="text" class="validate">
            <label for="company">{{ __('locale.Company') }}</label>
          </div>
          <div class="input-field col s12">
            <i class="material-icons prefix"> business_center </i>
            <input id="business" type="text" class="validate">
            <label for="business">{{ __('locale.Job Title') }}</label>
          </div>
        </div>
        <div class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix"> email </i>
            <input id="email" type="email" class="validate">
            <label for="email">{{ __('locale.Email') }}</label>
          </div>
          <div class="input-field col s12">
            <i class="material-icons prefix"> call </i>
            <input id="phone" type="text" class="validate">
            <label for="phone">{{ __('locale.Phone') }}</label>
          </div>
          <div class="input-field col s12">
            <i class="material-icons prefix"> note </i>
            <input id="notes" type="text" class="validate">
            <label for="notes">{{ __('locale.Notes') }}</label>
          </div>
        </div>
      </form>
      <div class="card-action pl-0 pr-0 right-align">
        <button class="btn-small waves-effect waves-light add-contact">
          <span>{{ __('locale.Add Contact') }}</span>
        </button>
        <button class="btn-small waves-effect waves-light update-contact display-none">
          <span>{{ __('locale.Update Contact') }}</span>
        </button>
      </div>
      <!-- form start end-->
    </div>
  </div>
</div>

<!-- Modal -->
<div id="myModal" class="modal">
  <div class="modal-content">
    <!-- Contenido del modal -->
    <span class="close">&times;</span>
    <p>Contenido del modal aquí.</p>
  </div>
</div>
@endsection

{{-- vendor scripts --}}
@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('vendors/fullcalendar/lib/moment.min.js')}}"></script>
<script src="{{asset('js/moment/' . app()->getLocale() . '.js')}}"></script>
@endsection

{{-- page scripts --}}
@section('page-script')
<script src="{{asset('js/custom/app-contacts.js')}}"></script>
@endsection