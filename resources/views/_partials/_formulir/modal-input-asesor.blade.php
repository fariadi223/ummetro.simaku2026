<!-- Edit User Modal -->
<div class="modal fade" id="modal-asesor-input" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Tunjuk Asesor</h3>
          <p class="text-muted">Halaman untuk memilih asesor asesmen.</p>
        </div>
        <form id="form-input-asesor" class="row g-3" action="" method="POST">
          {{ csrf_field() }}
          <input type="hidden" name="id" id="formulir_id" value="">
          <div class="col-xl-12 col-12">
            <dl class="row mb-0">
              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Nama Lengkap</dt>
              <dd class="col-sm-8 text01-nama">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Nama Skema</dt>
              <dd class="col-sm-8 text01-skema">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Jadwal</dt>
              <dd class="col-sm-8 text01-jadwal">...</dd>
            </dl>
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="asesor_lead_id">Lead Asesor</label>
            <select id="asesor_lead_id" name="asesor_lead_id" class="form-select select2_asesor_lead" aria-label="Pilih asesor">
              <option selected></option>
            </select>
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="asesor_id">Asesor</label>
            <select id="asesor_id" name="asesor_id" class="form-select select2_asesor" aria-label="Pilih asesor">
              <option selected></option>
            </select>
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="asesor_peninjau_id">Asesor Peninjau</label>
            <select id="asesor_peninjau_id" name="asesor_peninjau_id" class="form-select select2_asesor_peninjau" aria-label="Pilih asesor">
              <option selected></option>
            </select>
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="asesor_surat_tugas">Nomor Surat Tugas</label>
            <input type="text" id="asesor_surat_tugas" name="asesor_surat_tugas" class="form-control" placeholder="" />
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
