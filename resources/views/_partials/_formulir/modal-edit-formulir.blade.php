<!-- Edit Formulir Modal -->
<div class="modal fade" id="modal-formulir-edit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2">Edit Formulir</h3>
          <p class="text-muted mb-1">Perbarui data pemohon uji kompetensi.</p>
          <span class="badge bg-label-primary edit-text-nomor"></span>
          <span class="badge bg-label-secondary edit-text-skema"></span>
        </div>
        <form id="form-formulir-edit" class="row g-3" action="" method="POST" onsubmit="return false">
          {{ csrf_field() }}
          <input type="hidden" name="id" />

          <div class="col-12">
            <h6 class="mb-0"><i class="ti ti-clipboard-text me-1"></i> Data Asesmen</h6>
            <hr class="mt-2 mb-0">
          </div>
          <div class="col-12">
            <label class="form-label" for="edit-skema_id">Skema Sertifikasi</label>
            <select id="edit-skema_id" name="skema_id" class="form-select select2_edit_skema" style="width: 100%;"></select>
            <div class="edit-biaya-info mt-2 d-none">
              <div class="alert alert-warning d-flex align-items-start mb-0 py-2" role="alert">
                <i class="ti ti-alert-triangle me-2 mt-1"></i>
                <div>
                  Biaya skema akan berubah dari <strong class="edit-biaya-lama"></strong> menjadi <strong class="edit-biaya-baru"></strong>.
                  <div class="edit-biaya-bayar small mt-1 d-none">
                    Peserta sudah membayar <strong class="edit-jumlah-bayar"></strong>; status tagihan akan dihitung ulang dari biaya baru.
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-tujuan">Tujuan Asesmen</label>
            <select id="edit-tujuan" name="tujuan" class="form-select">
              <option value="Sertifikasi">Sertifikasi</option>
              <option value="Sertifikasi Ulang">Sertifikasi Ulang</option>
              <option value="Pengakuan Kompetensi Terkini (PKT)">Pengakuan Kompetensi Terkini (PKT)</option>
              <option value="Rekognisi Pembelajaran Lampau">Rekognisi Pembelajaran Lampau</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-pendidikan">Pendidikan Terakhir</label>
            <select id="edit-pendidikan" name="pendidikan" class="form-select">
              <option value=""></option>
              <option value="S3">S3</option>
              <option value="S2">S2</option>
              <option value="S1">S1</option>
              <option value="D-4">D-4</option>
              <option value="D-3">D-3</option>
              <option value="D-2">D-2</option>
              <option value="D-1">D-1</option>
              <option value="SMA">SMA/SMK Sederajat</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>

          <div class="col-12 mt-4">
            <h6 class="mb-0"><i class="ti ti-user me-1"></i> Data Pemohon</h6>
            <hr class="mt-2 mb-0">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-nama_lengkap">Nama Lengkap</label>
            <input type="text" id="edit-nama_lengkap" name="nama_lengkap" class="form-control" maxlength="225" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-nomor_indentias">NIK / Nomor Identitas</label>
            <input type="text" id="edit-nomor_indentias" name="nomor_indentias" class="form-control" maxlength="35" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-tempat_lahir">Tempat Lahir</label>
            <input type="text" id="edit-tempat_lahir" name="tempat_lahir" class="form-control" maxlength="225" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-tanggal_lahir">Tanggal Lahir</label>
            <input type="date" id="edit-tanggal_lahir" name="tanggal_lahir" class="form-control" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-kelamin">Jenis Kelamin</label>
            <select id="edit-kelamin" name="kelamin" class="form-select">
              <option value=""></option>
              <option value="Laki-Laki">Laki-Laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-kebangsaan">Kebangsaan</label>
            <input type="text" id="edit-kebangsaan" name="kebangsaan" class="form-control" maxlength="100" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-email">Email</label>
            <input type="email" id="edit-email" name="email" class="form-control" maxlength="225" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="edit-nomor_hp">Nomor HP</label>
            <input type="text" id="edit-nomor_hp" name="nomor_hp" class="form-control" maxlength="15" />
          </div>

          <div class="col-12 mt-4">
            <h6 class="mb-0"><i class="ti ti-map-pin me-1"></i> Alamat</h6>
            <hr class="mt-2 mb-0">
          </div>
          <div class="col-12">
            <label class="form-label" for="edit-alamat_rumah">Alamat Rumah</label>
            <textarea id="edit-alamat_rumah" name="alamat_rumah" class="form-control" rows="2" maxlength="225"></textarea>
          </div>
          <div class="col-4">
            <label class="form-label" for="edit-rt">RT</label>
            <input type="text" id="edit-rt" name="rt" class="form-control" maxlength="4" />
          </div>
          <div class="col-4">
            <label class="form-label" for="edit-rw">RW</label>
            <input type="text" id="edit-rw" name="rw" class="form-control" maxlength="4" />
          </div>
          <div class="col-4">
            <label class="form-label" for="edit-kode_pos">Kode Pos</label>
            <input type="text" id="edit-kode_pos" name="kode_pos" class="form-control" maxlength="8" />
          </div>
          <div class="col-12">
            <label class="form-label" for="edit-alamat_kantor">Alamat Kantor</label>
            <textarea id="edit-alamat_kantor" name="alamat_kantor" class="form-control" rows="2" maxlength="225"></textarea>
          </div>

          <div class="col-12 text-center mt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1 btn-simpan-edit">
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
              Simpan
            </button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Batal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit Formulir Modal -->
