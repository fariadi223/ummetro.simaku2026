<!-- Edit User Modal -->
<div class="modal fade" id="modal-jadwal-add" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modalJadwalTitle">...</h3>
          <p class="text-muted">Form input jadwal.</p>
        </div>
        <form id="form-jadwal-add" class="row g-3" onsubmit="return false">
          <input type="hidden" name="id" value="" id="jadwal_id">
          <div class="col-12 col-md-4">
            <label class="form-label" for="tahun">Tahun</label>
            <input type="text" id="tahun" name="tahun" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="batch">Batch</label>
            <input type="text" id="batch" name="batch" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="kuota">Kuota Max/Kelas</label>
            <input type="text" id="kuota" name="kuota" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="tanggal_mulai">Tanggal Mulai</label>
            <input type="text" id="tanggal_mulai" name="tanggal_mulai" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="tanggal_selesai">Tanggal Selesai</label>
            <input type="text" id="tanggal_selesai" name="tanggal_selesai" placeholder="DD/MM/YYYY" class="form-control" />
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="tanggal_selesai_validasi_apl01">Tanggal Selesai Validasi APL-01</label>
            <input type="text" id="tanggal_selesai_validasi_apl01" name="tanggal_selesai_validasi_apl01" placeholder="DD/MM/YYYY" class="form-control" />
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
