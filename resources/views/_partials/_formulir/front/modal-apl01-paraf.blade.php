<!-- Edit User Modal -->
<div class="modal fade" id="modal-paraf-apl01" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modalParafTitle">...</h3>
          <p class="text-muted">Paraf Dokumen APL-01 yang akan diajukan</p>
        </div>
        <form id="form-jadwal-pilih" class="row g-3" onsubmit="return false">
          @csrf
          <input type="hidden" id="formulir_id" name="formulir_id" value="">
          <input type="hidden" id="dokumen_paraf" name="dokumen_paraf" value="">
          
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
          <!--
          <div class="col-12 text-center">
            <button type="button" class="btn btn-primary me-sm-3 me-1 handle-crate-jadwal">Simpan</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
          !-->
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
