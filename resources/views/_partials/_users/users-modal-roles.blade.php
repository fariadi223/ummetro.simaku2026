<!-- Edit User Modal -->
<div class="modal fade" id="modal-roles" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Roles</h3>
          <p class="text-muted">Halaman input role users.</p>
        </div>
        <form id="form-role-user" class="row g-3" onsubmit="return false">
          {{ csrf_field() }}
          <input type="hidden" name="id" id="role_user_id" value="">
          <div class="col-12 col-md-7">
            <label class="form-label" for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label" for="email">Email</label>
            <input type="text" id="email" name="email" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-12">
              <label class="form-check-label">Role</label>
              <div class="col mt-2">
                <div class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" name="roles_id[]" value="1" id="defaultCheck1" />
                  <label class="form-check-label" for="defaultCheck1">
                    Administrator
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="roles_id[]" value="3" id="defaultCheck2"/>
                  <label class="form-check-label" for="defaultCheck2">
                    Asesor
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="roles_id[]" value="2" id="defaultCheck3" checked />
                  <label class="form-check-label" for="defaultCheck3">
                    Peserta
                  </label>
                </div>
                
              </div>
              <!--
              <div class="col mt-2">
                <div class="form-check form-check-inline">
                  <input name="roles_id" class="form-check-input" type="radio" value="1" id="roles-admin" checked="" />
                  <label class="form-check-label" for="roles-admin">Administrator</label>
                </div>
                <div class="form-check form-check-inline">
                  <input name="roles_id" class="form-check-input" type="radio" value="3" id="roles-asesor" />
                  <label class="form-check-label" for="roles-asesor"> Asesor </label>
                </div>
                <div class="form-check form-check-inline">
                  <input name="roles_id" class="form-check-input" type="radio" value="2" id="roles-peserta" />
                  <label class="form-check-label" for="roles-peserta"> Peserta </label>
                </div>
              </div>
              -->
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Simpan</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
