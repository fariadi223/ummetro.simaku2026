<!-- Edit User Modal -->
<div class="modal fade" id="modal-login-as" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Login As</h3>
          <p class="text-muted">Halaman untuk login sebagai peserta.</p>
        </div>
        <form id="loginAsModalForm" class="row g-3" action="{{url('auth/peserta/authenticate')}}" method="POST">
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
            <label class="form-label" for="telpn">Nomor HP</label>
            <div class="input-group">
              <span class="input-group-text">ID (+62)</span>
              <input type="text" id="telpn" name="telpn" class="form-control phone-number-mask" placeholder="202 555 0111" />
            </div>
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Login As</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
