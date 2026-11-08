<!-- Edit User Modal -->
<div class="modal fade" id="modal-password" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Password</h3>
          <p class="text-muted">Halaman untuk merubah password mahasiswa.</p>
        </div>
        <form id="form-password-user" class="row g-3" onsubmit="return false">
          {{ csrf_field() }}
          <input type="hidden" name="id" value="">
          <div class="col-12 col-md-12">
            <label class="form-label" for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="email">Email</label>
            <input type="text" id="email" name="email" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="" />
          </div>
          <div class="col-sm-6 form-password-toggle mb-3">
            <label class="form-label" for="new_password">Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="new_password" class="form-control" name="new_password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
          </div>
          <div class="col-sm-6 form-password-toggle mb-3">
              <label class="form-label" for="confirm_new_password">Confirm Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="confirm_new_password" class="form-control" name="confirm_new_password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Update Password</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
