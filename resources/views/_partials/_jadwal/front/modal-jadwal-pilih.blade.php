<!-- Edit User Modal -->
<div class="modal fade" id="modal-jadwal-pilih" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modalJadwalTitle">...</h3>
          <p class="text-muted">Form Pilih jadwal.</p>
        </div>
        <form id="form-jadwal-pilih" class="row g-3" onsubmit="return false">
          @csrf
          <input type="hidden" id="jadwal_id" name="jadwal_id" value="">
          <input type="hidden" id="dokumen_paraf" name="dokumen_paraf" value="">
          <div class="col-xl-12 col-12">
            <dl class="row mb-0">
              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Tahun</dt>
              <dd class="col-sm-8 text01-tahun">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Batch</dt>
              <dd class="col-sm-8 text01-batch">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Tanggal Ujikom</dt>
              <dd class="col-sm-8 text01-tanggal-mulai">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Kuota Max/Kelas</dt>
              <dd class="col-sm-8 text01-kuota">...</dd>
              
            </dl>
          </div>
          <div class="col-12 col-md-12">
            <label class="form-label" for="asesor_id">Pilih Nomor Formulir Sudah Dibayar</label>
            <select id="formulir_id" name="formulir_id" class="form-select select2_formulir" aria-label="Pilih formulir">
              <option selected></option>
            </select>
          </div>
          <!--
          <div class="col-12 col-md-12">
            <label class="form-label">Paraf Asesmen</label>
            <x-creagia-signature-pad
                  border-color="#eaeaea"
                  pad-classes="rounded-xl border-2"
                  button-classes="btn btn-warning me-sm-3 me-1 mt-4 hidden"
                  clear-name="Bersihakan"
                  submit-name="Paraf"
                  :disabled-without-signature="true"
            />
          </div>
          -->

          <div class="col-12 text-center">
            <button type="button" class="btn btn-primary me-sm-3 me-1 handle-crate-jadwal">Ambil Jadwal</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
        @inject('myModel', '\App\Models\Signature\SigantureModel')
        @if (!$myModel->hasBeenSigned())
          <form onsubmit="return false" >
            @csrf
            <div style="text-align: center" class="mt-2">
                <x-creagia-signature-pad
                    border-color="#eaeaea"
                    pad-classes="rounded-xl border-2"
                    button-classes="btn btn-warning me-sm-3 me-1 mt-4"
                    clear-name="Bersihakan"
                    submit-name="Paraf"
                    :disabled-without-signature="true"
                />
            </div>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
