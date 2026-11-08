<!-- Edit User Modal -->
<div class="modal fade" id="modal-bebasujikom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Bebas Ujikom</h3>
          <p class="text-muted">Halaman untuk menambahkan bebas ujikom.</p>
        </div>
        <form id="form-input" class="row g-3" action="" method="POST">
          {{ csrf_field() }}
          <div class="col-12 col-md-12">
            <label class="form-label" for="user_id">Pilih user</label>
            <select id="user_id" name="user_id" class="form-select select2_user_id" aria-label="Pilih user">
              <option selected></option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="nomor_indentias">No Identitas</label>
            <input type="text" id="nomor_indentias" name="nomor_indentias" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label" for="nama_lengkap">Nama Lengkap</label>
            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="tanggal_lahir">Tanggal Lahir</label>
            <input type="text" id="tanggal_lahir" name="tanggal_lahir" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label" for="">Tempat Lahir</label>
            <input type="text" id="tempat_lahir" name="tempat_lahir" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="nomor_hp">No HP</label>
            <input type="text" id="nomor_hp" name="nomor_hp" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label" for="email">Email</label>
            <input type="text" id="email" name="email" class="form-control" placeholder="" />
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
