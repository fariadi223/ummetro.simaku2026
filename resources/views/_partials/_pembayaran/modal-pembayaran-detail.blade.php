<!-- Edit User Modal -->
<div class="modal fade" id="modal-pelunasan-detail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">DETAIL PEMBAYARAN TRANSITO</h3>
          <p class="text-muted">Rincian transaksi setoran mahasiswa.</p>
        </div>
        
        <div class="row g-3">
          <div class="col-xl-12 col-12">
            <dl class="row mb-0">
              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Nama Lengkap</dt>
              <dd class="col-sm-8 text01-nm_pd">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">NPM</dt>
              <dd class="col-sm-8 text01-nipd">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Tahun Akademik</dt>
              <dd class="col-sm-8 text01-thn_akad">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Semester Mahasiswa</dt>
              <dd class="col-sm-8 text01-smt_mhs">...</dd>

              <dt class="col-sm-4 mb-2 fw-semibold text-nowrap">Tanggal Setoran</dt>
              <dd class="col-sm-8 text01-create_date">...</dd>
            </dl>
          </div>
          <div class="col-12">
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 50px;">No</th>
                    <th>Rincian</th>
                    <th class="text-end">Jumlah (Rp.)</th>
                  </tr>
                </thead>
                <tbody class="detail-rinci">
                  <tr><td colspan="3" class="text-center text-muted py-4">Memuat...</td></tr>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="2" class="text-end">Total Setoran</th>
                    <th class="text-end detail-total">-</th>
                  </tr>
                  <tr>
                    <th colspan="2" class="text-end">Total Ujikom</th>
                    <th class="text-end text-primary detail-total-ujikom">-</th>
                  </tr>
                </tfoot>
              </table>
            </div>
            <small class="text-muted d-block mt-2">
              <span class="badge bg-label-primary">Ujikom</span> menandai rincian rekening uji kompetensi.
            </small>
          </div>
        </div>
       
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
