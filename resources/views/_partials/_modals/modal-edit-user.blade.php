<!-- Edit User Modal -->
<div class="modal fade" id="editUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Edit User Information</h3>
          <p class="text-muted">Updating user details will receive a privacy audit.</p>
        </div>
        <form id="editUserForm" class="row g-3" action="{{ route('contact.update', $data->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserFirstName">First Name</label>
            <input type="text" id="modalEditUserFirstName" name="name" class="form-control" placeholder="John" value="{{ $data->name }}" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserEmail">Email</label>
            <input type="email" id="modalEditUserEmail" name="email" class="form-control" placeholder="example@domain.com" value="{{ $data->email }}" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserStatus">Status</label>
            <x-input-select-array id="EnterpriseState" :options="$enterpriseStatuses" :value="''"
                        placeholder="Selecciona un estado" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserPhone">Phone Number</label>
            <div class="input-group">
              <span class="input-group-text">US (+1)</span>
              <input type="text" id="modalEditUserPhone" name="phone" class="form-control phone-number-mask" placeholder="202 555 0111" value="{{ $data->phone }}" />
            </div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserLanguage">Language</label>
            <select id="modalEditUserLanguage" name="language" class="select2 form-select" multiple>
              <option value="english" {{ in_array('english', explode(',', $data->language ?? '')) ? 'selected' : '' }}>English</option>
              <option value="spanish" {{ in_array('spanish', explode(',', $data->language ?? '')) ? 'selected' : '' }}>Spanish</option>
              <option value="french" {{ in_array('french', explode(',', $data->language ?? '')) ? 'selected' : '' }}>French</option>
              <option value="german" {{ in_array('german', explode(',', $data->language ?? '')) ? 'selected' : '' }}>German</option>
              <option value="dutch" {{ in_array('dutch', explode(',', $data->language ?? '')) ? 'selected' : '' }}>Dutch</option>
              <option value="hebrew" {{ in_array('hebrew', explode(',', $data->language ?? '')) ? 'selected' : '' }}>Hebrew</option>
              <option value="sanskrit" {{ in_array('sanskrit', explode(',', $data->language ?? '')) ? 'selected' : '' }}>Sanskrit</option>
              <option value="hindi" {{ in_array('hindi', explode(',', $data->language ?? '')) ? 'selected' : '' }}>Hindi</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserCountry">Country</label>
            <select id="modalEditUserCountry" name="country" class="select2 form-select" data-allow-clear="true">
              <option value="">Select</option>
              @foreach($countries as $country)
                <option value="{{ $country->code }}" {{ $data->country == $country->code ? 'selected' : '' }}>{{ $country->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Submit</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
