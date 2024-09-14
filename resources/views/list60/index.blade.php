@extends('layouts/layoutMaster')

@section('title', __('app.list60') )

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<!-- Row Group CSS -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-rowgroup-bs5/rowgroup.bootstrap5.css')}}">
<!-- Form Validation -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<!-- Flat Picker -->
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<!-- Form Validation -->
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/tables-datatables-list60.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('app.list60') }}</h4>
        <p class="text-muted">Averigua cómo esta vaina va a lanzar tus ventas</p>
    </div>
</div>

<!-- DataTable with Buttons -->
<div class="card">
  <div class="card-datatable table-responsive pt-0">
    <table class="datatables-basic table">
      <thead>
        <tr>
          <th></th>
          <th></th>
          <th>id</th>
          <th>Nombre</th>
          <th>Email</th>
          <th>Proximo contacto</th>
          <th>Negocio</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
    </table>
  </div>
</div>
<!-- Modal to add new record -->
<div class="offcanvas offcanvas-end" id="add-new-record">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="exampleModalLabel">+ Añadir contacto</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body flex-grow-1">
    <form class="add-new-record pt-0 row g-2" id="form-add-new-record" onsubmit="return false">
      <div class="col-sm-12">
        <label class="form-label" for="basicFullname">Full Name</label>
        <div class="input-group input-group-merge">
          <span id="basicFullname2" class="input-group-text"><i class="ti ti-user"></i></span>
          <input type="text" id="basicFullname" class="form-control dt-full-name" name="basicFullname" placeholder="John Doe" aria-label="John Doe" aria-describedby="basicFullname2" />
        </div>
      </div>
      <div class="col-sm-12">
        <label class="form-label" for="basicPost">Post</label>
        <div class="input-group input-group-merge">
          <span id="basicPost2" class="input-group-text"><i class='ti ti-briefcase'></i></span>
          <input type="text" id="basicPost" name="basicPost" class="form-control dt-post" placeholder="Web Developer" aria-label="Web Developer" aria-describedby="basicPost2" />
        </div>
      </div>
      <div class="col-sm-12">
        <label class="form-label" for="basicEmail">Email</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text"><i class="ti ti-mail"></i></span>
          <input type="text" id="basicEmail" name="basicEmail" class="form-control dt-email" placeholder="john.doe@example.com" aria-label="john.doe@example.com" />
        </div>
        <div class="form-text">
          You can use letters, numbers & periods
        </div>
      </div>
      <div class="col-sm-12">
        <label class="form-label" for="basicDate">Joining Date</label>
        <div class="input-group input-group-merge">
          <span id="basicDate2" class="input-group-text"><i class='ti ti-calendar'></i></span>
          <input type="text" class="form-control dt-date" id="basicDate" name="basicDate" aria-describedby="basicDate2" placeholder="MM/DD/YYYY" aria-label="MM/DD/YYYY" />
        </div>
      </div>
      <div class="col-sm-12">
        <label class="form-label" for="basicSalary">Salary</label>
        <div class="input-group input-group-merge">
          <span id="basicSalary2" class="input-group-text"><i class='ti ti-currency-dollar'></i></span>
          <input type="number" id="basicSalary" name="basicSalary" class="form-control dt-salary" placeholder="12000" aria-label="12000" aria-describedby="basicSalary2" />
        </div>
      </div>
      <div class="col-sm-12">
        <button type="submit" class="btn btn-primary data-submit me-sm-3 me-1">Submit</button>
        <button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cancel</button>
      </div>
    </form>

  </div>
</div>
<!--/ DataTable with Buttons -->

@endsection
