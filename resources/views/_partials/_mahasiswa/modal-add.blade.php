<!-- Edit User Modal -->
<div class="modal fade" id="modal-add-mhs" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-4">
          <h3 class="mb-2" id="modalTitleLabel">...</h3>
          <p class="text-muted">Form input data mahasiswa.</p>
        </div>
        <form id="addMhsModalForm" class="row g-3" onsubmit="return false">
          <input type="hidden" name="id_reg_pd" value="">
          <input type="hidden" name="id_sms" value="{{ $roleProdi['id_sms'] }}">
          <div class="col-12 col-md-4">
            <label class="form-label" for="nipd">N P M</label>
            <input type="text" id="nipd" name="nipd" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label" for="nm_pd">Nama Lengkap</label>
            <input type="text" id="nm_pd" name="nm_pd" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label" for="tmpt_lahir">Tempat Lahir</label>
            <input type="text" id="tmpt_lahir" name="tmpt_lahir" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label" for="tgl_lahir">Tanggal Lahir</label>
            <input type="text" id="tgl_lahir" name="tgl_lahir" placeholder="DD/MM/YYYY" class="form-control" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="tmpt_lahir">Kelamin</label>
            <select id="jk" name="jk" class="form-select" aria-label="Kelamin">
              <option selected></option>
              <option value="L">Laki-Laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="email">Email</label>
            <input type="text" id="email" name="email" class="form-control" placeholder="" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="telpn">Nomor HP</label>
            <div class="input-group">
              <span class="input-group-text">ID (+62)</span>
              <input type="text" id="telpn" name="telpn" class="form-control phone-number-mask" placeholder="202 555 0111" />
            </div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="id_agama">Agama</label>
            <select id="id_agama" name="id_agama" class="select2 form-select" data-allow-clear="true">
              <option value="" selected>Select</option>
              <option value="1">Islam</option>
              <option value="2">Kristen</option>
              <option value="3">Katholik</option>
              <option value="4">Hindu</option>
              <option value="5">Budha</option>
              <option value="6">Konghucu</option>
              <option value="99">Lainnya</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="modalEditUserCountry">Wilayah</label>
            <select id="modalEditUserCountry" name="modalEditUserCountry" class="select2 form-select" data-allow-clear="true">
              <option value="">Select</option>
              <option value="Australia">Australia</option>
              <option value="Bangladesh">Bangladesh</option>
              <option value="Belarus">Belarus</option>
              <option value="Brazil">Brazil</option>
              <option value="Canada">Canada</option>
              <option value="China">China</option>
              <option value="France">France</option>
              <option value="Germany">Germany</option>
              <option value="India">India</option>
              <option value="Indonesia">Indonesia</option>
              <option value="Israel">Israel</option>
              <option value="Italy">Italy</option>
              <option value="Japan">Japan</option>
              <option value="Korea">Korea, Republic of</option>
              <option value="Mexico">Mexico</option>
              <option value="Philippines">Philippines</option>
              <option value="Russia">Russian Federation</option>
              <option value="South Africa">South Africa</option>
              <option value="Thailand">Thailand</option>
              <option value="Turkey">Turkey</option>
              <option value="Ukraine">Ukraine</option>
              <option value="United Arab Emirates">United Arab Emirates</option>
              <option value="United Kingdom">United Kingdom</option>
              <option value="United States">United States</option>
            </select>
          </div>
          <div class="col-12">
            <label class="switch">
              <input type="checkbox" class="switch-input">
              <span class="switch-toggle-slider">
                <span class="switch-on"></span>
                <span class="switch-off"></span>
              </span>
              <span class="switch-label">Use as a billing address?</span>
            </label>
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Submit</button>
            <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
