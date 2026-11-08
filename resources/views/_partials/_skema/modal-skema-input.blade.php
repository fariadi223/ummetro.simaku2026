<!-- Edit User Modal -->
<div class="modal fade" id="modal-skema-add" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modalJadwalTitle">...</h3>
          <p class="text-muted">Form input skema.</p>
        </div>
        <form id="form-skema-add" class="row g-3" onsubmit="return false">
          <input type="hidden" id="skema_id" value="">
          <div class="col-12 col-md-6">
            <label class="form-label" for="kode_skema">SK Nom.</label>
            <input type="text" id="kode_skema" name="kode_skema" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="jenis_skema">Jenis Skema</label>
            <input type="text" id="jenis_skema" name="jenis_skema" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="nama_skema">Nama Skema</label>
            <input type="text" id="nama_skema" name="nama_skema" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label" for="jurnal_id">Jurnal ID (SIMAKU)</label>
            <input type="text" id="jurnal_id" name="jurnal_id" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="jurnal_nama">Jurnal Nama (SIMAKU)</label>
            <input type="text" id="jurnal_nama" name="jurnal_nama" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label" for="biaya">Biaya</label>
            <input type="text" id="biaya" name="biaya" placeholder="0" class="form-control" />
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
