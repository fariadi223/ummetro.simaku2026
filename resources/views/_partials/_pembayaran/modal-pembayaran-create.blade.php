<!-- Edit User Modal -->
<div class="modal fade" id="modal-pelunasan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">PEMBAYARAN TRANSITO</h3>
          <p class="text-muted">Halaman untuk pelunasan pembayaran ujikom.</p>
        </div>
        <form id="form-input-pelunasan" class="row g-3" action="" method="POST">
          {{ csrf_field() }}
          <input type="hidden" name="id" id="kpp_id" value="">
          <div class="col-xl-12 col-12">
            <dl class="row mb-0">
              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Nama Lengkap</dt>
              <dd class="col-sm-8 text01-nm_pd">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">NPM</dt>
              <dd class="col-sm-8 text01-nipd">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Uraian Transaski</dt>
              <dd class="col-sm-8 text01-keterangan">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Jumlah Setoran</dt>
              <dd class="col-sm-8 text01-uang">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Tanggal Setoran</dt>
              <dd class="col-sm-8 text01-create_date">...</dd>
            </dl>
          </div>
          <div class="col-xl-12 col-12">
            <dl class="row mb-0">
                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Nomor Tagihan</dt>
                <dd class="col-sm-8 text01-nomor-tagihan">...</dd>

                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Nama Skema</dt>
                <dd class="col-sm-8 text01-skema">...</dd>

                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Jumlah Tagihan</dt>
                <dd class="col-sm-8 text01-tagihan">...</dd>

                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Sudah Dibayar</dt>
                <dd class="col-sm-8 text01-dibayar">...</dd>

                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Tanggal Bayar</dt>
                <dd class="col-sm-8 text01-tanggal-bayar">...</dd>

                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Sisa Tagihan</dt>
                <dd class="col-sm-8 fw-bold text01-sisa">...</dd>

                <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Status</dt>
                <dd class="col-sm-8 text01-status">...</dd>
            </dl>
          </div>

          <div class="col-12 d-none" id="info-lunas">
            <div class="alert alert-success d-flex align-items-start mb-0" role="alert">
              <i class="ti ti-circle-check me-2 mt-1"></i>
              <div>Tagihan formulir ini <strong>sudah lunas</strong>. Pelunasan tidak perlu diproses lagi.</div>
            </div>
          </div>

          <div class="col-12 d-none" id="info-tidak-sesuai">
            <div class="alert alert-warning d-flex align-items-start mb-0" role="alert">
              <i class="ti ti-alert-triangle me-2 mt-1"></i>
              <div>
                Jumlah setoran <strong class="text01-info-setoran"></strong> tidak sama dengan sisa tagihan
                <strong class="text01-info-sisa"></strong>. Pelunasan tidak dapat diproses.
              </div>
            </div>
          </div>
          
          <div class="col-12 text-center" id="tombol-lunas" style="display: none;">
            <button type="submit" class="btn btn-primary me-sm-3 me-1" id="btnLunas">Proses Lunas</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
