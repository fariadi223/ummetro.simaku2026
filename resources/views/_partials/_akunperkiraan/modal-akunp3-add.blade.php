<!-- Add Permission Modal -->
<div class="modal fade" id="addAkunP3Modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 p-md-5">
      <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body">
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modal-title-akunp3">....</h3>
          <p class="text-muted">....</p>
        </div>
        <form id="addAkunP3Form" class="row" onsubmit="return false">
          @csrf
          <input type="hidden" name="id" value="">
          <input type="hidden" name="akun_p2_id" value="{{ request()->input('akun_p2_id') }}">
          <div class="col-6 mb-3">
            <label class="form-label" for="akun_p3_kode">Kode</label>
            <input type="text" id="akun_p3_kode" name="akun_p3_kode" class="form-control" placeholder="00" autofocus />
          </div>
          <div class="col-12 mb-3">
            <label class="form-label" for="akun_p3_nama">Nama Akun Perkiraan</label>
            <input type="text" id="akun_p3_nama" name="akun_p3_nama" class="form-control" placeholder="Nama Akun" autofocus />
          </div>
          <div class="col-12 text-center demo-vertical-spacing">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Simpan</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Batal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Add Permission Modal -->
