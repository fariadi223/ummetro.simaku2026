<!-- Edit User Modal -->
<div class="modal fade" id="modal-unit-add" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modalUnitTitle">...</h3>
          <p class="text-muted">Form input kriteria unjuk kerja.</p>
        </div>
        <form id="form-unit-add" class="row g-3" onsubmit="return false">
          <input type="hidden" id="kuk_id" value="">
          <div class="col-12 col-md-6">
            <label class="form-label" for="kode_unit">Kode Unit</label>
            <input type="text" id="kode_unit" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="judul_unit">Judul Unit</label>
            <input type="text" id="judul_unit" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="kode_elemen">Kode Elemen</label>
            <input type="text" id="kode_elemen" name="kode_elemen" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label" for="elemen">Elemen</label>
            <input type="text" id="elemen" name="elemen" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="kode_kuk">Kode KUK</label>
            <input type="text" id="kode_kuk" name="kode_kuk" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="kuk">Nama KUK</label>
            <input type="text" id="kuk" name="kuk" class="form-control" placeholder="" />
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
