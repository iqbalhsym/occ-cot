@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Buat Case Baru')
@section('page_title', 'Buat Case Baru')

@section('content')
  @php
    $currentUser   = Auth::user();
    $activeRole    = session('role', $currentUser ? $currentUser->role : 'Nurse');
    $roleLabels    = [
      'Nurse'       => 'Nurse (Entry Point)',
      'VA'          => 'VA (Asuransi)',
      'Kasir'       => 'Kasir (Umum)',
      'ADRUCOT'     => 'ADRU COT (Umum)',
      'Farmasi'     => 'Farmasi',
      'AdminCOT'    => 'Admin COT',
      'CaseManager' => 'Case Manager',
      'CS'          => 'Customer Service',
      'SuperAdmin'  => 'Super Admin',
      'Administrator' => 'Administrator',
      'Viewer'      => 'Viewer (Hanya Lihat)',
    ];
    $activeRoleLabel = $roleLabels[$activeRole] ?? $activeRole;
  @endphp

  {{-- Badge user context --}}
  <div class="user-context-badge">
    <span>Pengguna:</span>
    <span>{{ $currentUser->name }}</span>
    <code style="font-size:11px; color:var(--slate-500);">({{ $currentUser->username }})</code>
    <span style="color:var(--slate-300);">|</span>
    <span class="role-pill">{{ $activeRoleLabel }}</span>
  </div>

  <div class="card">
    <h3>Form Penjadwalan Tindakan (Nurse — Entry Point)</h3>
    <div class="permission-note">Diisi berdasarkan Formulir Penjadwalan Tindakan yang telah diisi DPJP. Tersimpan sebagai <strong>Draft</strong>, dapat diedit sebelum Submit. Paket BMHP &amp; golongan otomatis mengikuti Tindakan yang dipilih.</div>
    
    <form id="caseForm">
      @csrf

      <h4>A. Identitas Pasien</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">No. Rekam Medis</label>
          <div style="display:flex; gap:8px;">
            <input name="rm" id="rmInput" required style="flex:1;">
            <button type="button" class="btn btn-sm" id="rmLookup">Cari</button>
          </div>
          <span class="hint" id="rmHint"></span>
        </div>
        <div class="field"><label class="req">Nama Pasien</label><input name="nama" id="namaInput" required></div>
        <div class="field"><label>Jenis Kelamin</label><select name="jenisKelamin" id="jkInput"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
        <div class="field">
          <label>Tanggal Lahir <span class="hint">(ketik: tgl / bln / thn)</span></label>
          <div style="display:flex; gap:6px;">
            <input name="dobD" id="dobD" placeholder="Tgl" maxlength="2" inputmode="numeric" style="width:56px; text-align:center;">
            <input name="dobM" id="dobM" placeholder="Bln" maxlength="2" inputmode="numeric" style="width:56px; text-align:center;">
            <input name="dobY" id="dobY" placeholder="Thn" maxlength="4" inputmode="numeric" style="width:72px; text-align:center;">
          </div>
        </div>
        <div class="field full">
          <label>Lokasi Pengajuan (Ruangan)</label>
          <input name="lokasiPengajuan" id="lokasiPengajuan" placeholder="Ketik nama ruangan / lokasi">
        </div>
      </div>

      <h4>B. Data Dokter (bisa lebih dari satu)</h4>
      <div class="form-grid">
        <div class="field full">
          <label class="req">DPJP</label>
          <div id="dpjpEditor"></div>
          <button type="button" class="btn btn-sm" id="addDpjp" style="margin-top:8px;">+ Tambah DPJP</button>
        </div>
        <div class="field full">
          <label>Operator <span class="hint">(spesialisasi menentukan golongan bila tindakan non-paket)</span></label>
          <div id="opEditor"></div>
          <button type="button" class="btn btn-sm" id="addOp" style="margin-top:8px;">+ Tambah Operator</button>
        </div>
      </div>

      <h4>C. Data Medis</h4>
      <div class="form-grid">
        <div class="field full"><label class="req">Diagnosis</label><textarea name="diagnosis" required></textarea></div>
        <div class="field full">
          <label class="req">Tindakan <span class="hint">(pilih dari daftar paket, atau ketik bebas untuk non-paket)</span></label>
          <div id="tindakanEditor"></div>
          <button type="button" class="btn btn-sm" id="addTindakan" style="margin-top:8px;">+ Tambah Tindakan</button>
        </div>
      </div>
      <div id="tindakanAutofill"></div>

      <h4>D. Jenis Operasi (bisa dipilih lebih dari satu)</h4>
      <div class="checkbox-grid" id="jenisOperasiBox">
        <label><input type="checkbox" name="jenisOperasi[]" class="jenisOperasiChk" value="CITO"> CITO</label>
        <label><input type="checkbox" name="jenisOperasi[]" class="jenisOperasiChk" value="Elektif" checked> Elektif</label>
        <label><input type="checkbox" name="jenisOperasi[]" class="jenisOperasiChk" value="ODC"> ODC</label>
      </div>

      <h4>E. Jenis Anestesi</h4>
      <div class="form-grid">
        <div class="field">
          <label>Jenis Anestesi</label>
          <select name="anestesi" id="anestesiSel">
            <option value="Lokal">Lokal</option>
            <option value="Spinal">Spinal</option>
            <option value="Epidural">Epidural</option>
            <option value="General">General</option>
            <option value="Sedasi">Sedasi</option>
            <option value="Dalam Konfirmasi">Dalam Konfirmasi</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>
        <div class="field" id="anestesiLainnyaWrap" style="display:none;">
          <label>Sebutkan Jenis Anestesi</label>
          <input name="anestesiLainnya" placeholder="mis. TIVA / kombinasi">
        </div>
      </div>

      <h4>F. Penjadwalan Operasi</h4>
      <div class="form-grid">
        <div class="field"><label>Tanggal Operasi — Pilihan 1</label><input type="date" name="tanggalPilihan1"></div>
        <div class="field"><label>Tanggal Operasi — Pilihan 2</label><input type="date" name="tanggalPilihan2"></div>
        <div class="field"><label>Jam Operasi</label><input type="time" name="jamOperasi"></div>
        <div class="field"><label>Estimasi Lama Operasi</label><input name="estimasiLamaOperasi" placeholder="mis. 2 jam"></div>
      </div>

      <h4>G. Lokasi Tindakan (wajib)</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Lokasi Tindakan</label>
          <select name="lokasiTindakan" required id="lokasiTindakanSel">
            <option value="COT">COT</option>
            <option value="OT IGD">OT IGD</option>
            <option value="Cathlab">Cathlab</option>
            <option value="Endoskopi">Endoskopi</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>
        <div class="field" id="lokasiLainnyaWrap" style="display:none;">
          <label>Sebutkan Lokasi Lainnya</label>
          <input name="lokasiTindakanLainnya">
        </div>
      </div>

      <h4>H. Asal Pasien (wajib)</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Asal Pasien</label>
          <select name="asalPasien" required id="asalPasienSel">
            <option value="Rawat Inap">Rawat Inap</option>
            <option value="IGD">IGD</option>
            <option value="ICU">ICU</option>
            <option value="Rawat Jalan">Rawat Jalan</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>
        <div class="field" id="asalLainnyaWrap" style="display:none;">
          <label>Sebutkan Asal Lainnya</label>
          <input name="asalPasienLainnya">
        </div>
      </div>

      <h4>I. Ruang Pasca Operasi</h4>
      <div class="form-grid">
        <div class="field">
          <label>Ruang Pasca Operasi</label>
          <select name="ruangPascaOperasi" id="ruangPascaSel">
            <option value="Rawat Inap">Rawat Inap</option>
            <option value="ICU">ICU</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>
        <div class="field" id="ruangPascaLainnyaWrap" style="display:none;">
          <label>Sebutkan Lainnya</label>
          <input name="ruangPascaOperasiLainnya">
        </div>
      </div>

      <h4>J. Estimasi Rawat Inap</h4>
      <div class="form-grid">
        <div class="field"><label>Estimasi Rawat Inap <span class="hint">(satuan hari)</span></label><input name="estimasiRawatInap" placeholder="mis. 3 - 4 hari"></div>
      </div>

      <h4>K. Penjamin (wajib)</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Penjamin</label>
          <select name="penjamin" required id="penjaminSel">
            <option value="Umum">Umum</option>
            <option value="Asuransi">Asuransi</option>
          </select>
        </div>
        <div class="field" id="guarantorWrap" style="display:none;">
          <label>Nama Guarantor / Asuransi</label>
          <input name="namaGuarantor" id="guarantorInput">
        </div>
      </div>

      <h4>M. Golongan Tindakan</h4>
      <div class="form-grid">
        <div class="field">
          <label>Golongan <span class="hint">(otomatis dari tindakan/spesialisasi)</span></label>
          <input type="text" id="golonganSelDisplay" readonly value="NON GOLONGAN" class="form-control" style="background:var(--slate-100); cursor:not-allowed;">
          <input type="hidden" name="golongan" id="golonganSel" value="NON GOLONGAN">
        </div>
        <div class="field">
          <label>Spesialisasi Operator</label>
          <input name="spesialisasiOp" id="spesialisasiInput" placeholder="mis. Ortopedi (SpOT)">
        </div>
      </div>

      <h4>N. Kebutuhan Obat &amp; BMHP <span class="hint" style="font-weight:400;">(otomatis dari paket tindakan)</span></h4>
      <div id="bmhpAutofill">
        <span class="af-empty">Pilih Tindakan berpaket untuk menampilkan paket BMHP.</span>
      </div>

      <h4>O. Alat Khusus</h4>
      <div class="permission-note" style="margin-bottom:10px;">Alat awal dapat ditarik dari database tindakan. Nurse boleh menambah bila ada data; penambahan utama dilakukan oleh <strong>Admin COT</strong>.</div>
      <div id="alatEditor"></div>
      <button type="button" class="btn btn-sm" id="addAlat" style="margin-top:8px;">+ Tambah Alat Khusus</button>

      <h4>P. Tambahan di Luar Paket</h4>
      <div id="tambahanEditor"></div>
      <button type="button" class="btn btn-sm" id="addTambahan" style="margin-top:8px;">+ Tambah item BMHP/Obat</button>

      <div class="btn-row" style="margin-top:30px;">
        <button type="submit" class="btn">Simpan sebagai Draft</button>
        <button type="button" class="btn btn-primary" id="btnSubmitDirect">Submit Pengajuan</button>
        <a href="{{ route('dashboard') }}" class="btn" style="text-decoration:none;">Batal</a>
      </div>
    </form>
  </div>
@endsection

@section('scripts')
  <script>
    // Variables for master data collections loaded via AJAX
    let masterData = {
      lokasi: [],
      penjamin: [],
      spesialisasi: [],
      tindakan: [],
      alat: []
    };

    // Arrays to manage dynamic input fields
    let dpjpList = [""];
    let opList = [""];
    let opSpesList = [""];
    let tindakanList = [""];
    let alatList = [];
    let tambahanList = [];

    // Format utility
    function rupiah(n) {
      if (n === null || n === undefined) return "-";
      return "Rp " + Number(n).toLocaleString("id-ID");
    }

    // Load master autocomplete lists on page load
    fetch('{{ route("api.master-data") }}')
      .then(res => res.json())
      .then(data => {
        masterData = data;
        initializeAutocompletes();
      });

    function initializeAutocompletes() {
      makeAutocomplete(document.getElementById("lokasiPengajuan"), masterData.lokasi);
      makeAutocomplete(document.getElementById("guarantorInput"), masterData.penjamin);
      makeAutocomplete(document.getElementById("spesialisasiInput"), masterData.spesialisasi);
      renderDpjpRows();
      renderOpRows();
      renderTindakanRows();
      renderAlatRows();
      renderTambahanRows();
    }

    // Patient lookup by RM
    function doRmLookup() {
      const rm = document.getElementById("rmInput").value.trim();
      const hint = document.getElementById("rmHint");
      if (!rm) {
        hint.textContent = "";
        return;
      }
      hint.textContent = "Mencari...";
      fetch(`/api/patients/${rm}`)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            document.getElementById("namaInput").value = data.pasien.nama || "";
            document.getElementById("jkInput").value = data.pasien.jenis_kelamin || "L";
            if (data.pasien.tgl_lahir) {
              const parts = data.pasien.tgl_lahir.split("-");
              if (parts.length === 3) {
                document.getElementById("dobY").value = parts[0];
                document.getElementById("dobM").value = parts[1];
                document.getElementById("dobD").value = parts[2];
              }
            }
            hint.textContent = "✓ Data pasien ditemukan & diisi otomatis.";
            hint.style.color = "var(--green-600)";
          } else {
            hint.textContent = "Pasien baru (belum terdaftar di master).";
            hint.style.color = "var(--slate-500)";
          }
        });
    }

    document.getElementById("rmLookup").addEventListener("click", doRmLookup);
    document.getElementById("rmInput").addEventListener("blur", doRmLookup);

    // Dynamic fields rendering functions
    function renderDpjpRows() {
      const el = document.getElementById("dpjpEditor");
      el.innerHTML = "";
      dpjpList.forEach((v, i) => {
        const row = document.createElement("div");
        row.className = "dyn-row";
        row.style.marginBottom = "8px";
        row.innerHTML = `<span class="tag">DPJP ${i + 1}</span>`;
        const inp = document.createElement("input");
        inp.name = "dpjp[]";
        inp.value = v;
        inp.placeholder = "Nama DPJP (mis. dr. Andi, Sp.B)";
        inp.className = "form-control";
        inp.addEventListener("input", (e) => dpjpList[i] = e.target.value);
        row.appendChild(inp);
        
        if (dpjpList.length > 1) {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "btn btn-sm btn-danger";
          btn.textContent = "Hapus";
          btn.onclick = () => { dpjpList.splice(i, 1); renderDpjpRows(); };
          row.appendChild(btn);
        }
        el.appendChild(row);

        const docSuggestions = masterData.doctors ? masterData.doctors.map(d => d.nama_gelar) : [];
        makeAutocomplete(inp, docSuggestions, (selectedName) => {
          dpjpList[i] = selectedName;
          renderDpjpRows();
        });
      });
    }

    document.getElementById("addDpjp").onclick = () => { dpjpList.push(""); renderDpjpRows(); };

    function renderOpRows() {
      const el = document.getElementById("opEditor");
      el.innerHTML = "";
      opList.forEach((v, i) => {
        const row = document.createElement("div");
        row.className = "dyn-row";
        row.style.marginBottom = "8px";
        row.innerHTML = `<span class="tag">Op ${i + 1}</span>`;
        
        const inpN = document.createElement("input");
        inpN.name = "operator[]";
        inpN.value = v;
        inpN.placeholder = "Nama Operator";
        inpN.className = "form-control";
        inpN.addEventListener("input", (e) => opList[i] = e.target.value);
        row.appendChild(inpN);

        const inpS = document.createElement("input");
        inpS.name = "operatorSpesialisasi[]";
        inpS.value = opSpesList[i] || "";
        inpS.placeholder = "Spesialisasi";
        inpS.className = "form-control";
        inpS.addEventListener("input", (e) => opSpesList[i] = e.target.value);
        row.appendChild(inpS);
        
        if (opList.length > 1) {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "btn btn-sm btn-danger";
          btn.textContent = "Hapus";
          btn.onclick = () => { opList.splice(i, 1); opSpesList.splice(i, 1); renderOpRows(); };
          row.appendChild(btn);
        }
        el.appendChild(row);

        const docSuggestions = masterData.doctors ? masterData.doctors.map(d => d.nama_gelar) : [];
        makeAutocomplete(inpN, docSuggestions, (selectedName) => {
          opList[i] = selectedName;
          if (masterData.doctors) {
            const found = masterData.doctors.find(d => d.nama_gelar === selectedName);
            if (found && found.spesialis) {
              opSpesList[i] = found.spesialis;
            }
          }
          renderOpRows();
        });

        makeAutocomplete(inpS, masterData.spesialisasi);
      });
    }

    document.getElementById("addOp").onclick = () => { opList.push(""); opSpesList.push(""); renderOpRows(); };

    function renderTindakanRows() {
      const el = document.getElementById("tindakanEditor");
      el.innerHTML = "";
      tindakanList.forEach((v, i) => {
        const row = document.createElement("div");
        row.className = "dyn-row";
        row.style.marginBottom = "8px";
        row.innerHTML = `<span class="tag">Tindakan ${i + 1}</span>`;
        
        const inp = document.createElement("input");
        inp.name = "tindakan[]";
        inp.value = v;
        inp.placeholder = "Pilih paket atau ketik bebas (non-paket)";
        inp.style.flex = "1";
        
        inp.addEventListener("input", (e) => { 
          tindakanList[i] = e.target.value; 
          applyTindakan(); 
        });
        
        row.appendChild(inp);
        
        if (tindakanList.length > 1) {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "btn btn-sm btn-danger";
          btn.textContent = "Hapus";
          btn.onclick = () => { tindakanList.splice(i, 1); renderTindakanRows(); applyTindakan(); };
          row.appendChild(btn);
        }
        el.appendChild(row);
        makeAutocomplete(inp, masterData.tindakan, (item) => {
          tindakanList[i] = item;
          applyTindakan();
        });
      });
    }

    document.getElementById("addTindakan").onclick = () => { tindakanList.push(""); renderTindakanRows(); };

    // Lookup selected tindakan for classification (golongan), specialty, and BMHP details
    function applyTindakan() {
      const activeTindakan = tindakanList.find(x => x && x.trim());
      const golSel = document.getElementById("golonganSel");
      const spesInput = document.getElementById("spesialisasiInput");
      const bmhpBox = document.getElementById("bmhpAutofill");
      const afBox = document.getElementById("tindakanAutofill");

      if (!activeTindakan) {
        afBox.innerHTML = "";
        bmhpBox.innerHTML = `<span class="af-empty">Pilih Tindakan berpaket untuk menampilkan paket BMHP.</span>`;
        return;
      }

      fetch(`/api/tindakan/lookup?nama=${encodeURIComponent(activeTindakan)}`)
        .then(res => res.json())
        .then(data => {
          if (data.success && data.golongan !== "NON GOLONGAN") {
            golSel.value = data.golongan;
            const golSelDisplay = document.getElementById("golonganSelDisplay");
            if (golSelDisplay) golSelDisplay.value = data.golongan;
            if (!spesInput.value.trim()) {
              spesInput.value = data.spesialisasi || "";
            }
            const hargaText = data.hargaUmum ? `${rupiah(data.hargaUmum)} (Umum) · ${rupiah(data.hargaBPJS)} (BPJS)` : "harga paket tidak tersedia";
            afBox.innerHTML = `
              <div class="autofill-box">
                <div class="af-head">
                  <span class="af-pill gol">Golongan: ${data.golongan}</span>
                  <span class="af-pill spes">${data.spesialisasi || "-"}</span>
                  <span class="af-pill harga">Paket ${data.paket || "-"}: ${hargaText}</span>
                </div>
                <span class="hint">Data ini otomatis mengikuti Tindakan berpaket. Nurse cukup melengkapi Alat Khusus &amp; Tambahan di luar paket.</span>
              </div>`;

            if (data.bmhp && data.bmhp.length > 0) {
              const rows = data.bmhp.map(it => `<tr><td>${it.n}</td><td>${it.q || "-"}</td><td>${rupiah(it.h)}</td></tr>`).join("");
              bmhpBox.innerHTML = `
                <div class="autofill-box">
                  <table class="af-table">
                    <tr style="font-weight:700;"><td>Item BMHP (paket ${data.paket || "-"})</td><td>Qty</td><td>Harga satuan</td></tr>
                    ${rows}
                  </table>
                </div>`;
            } else {
              bmhpBox.innerHTML = `<span class="af-empty">Tidak ada rincian BMHP untuk tindakan ini.</span>`;
            }
          } else {
            golSel.value = "NON GOLONGAN";
            const golSelDisplay = document.getElementById("golonganSelDisplay");
            if (golSelDisplay) golSelDisplay.value = "NON GOLONGAN";
            afBox.innerHTML = `
              <div class="autofill-box">
                <span class="hint">Tindakan tidak terdaftar di database paket → <strong>Non Golongan</strong>. Estimasi biaya akan diproses oleh <strong>Kasir</strong> atau <strong>ADRU COT</strong>.</span>
              </div>`;
            bmhpBox.innerHTML = `<span class="af-empty">Tindakan non-paket, tidak ada data BMHP bawaan.</span>`;
          }
        });
    }

    // Alat Khusus editor
    function renderAlatRows() {
      const el = document.getElementById("alatEditor");
      el.innerHTML = "";

      alatList.forEach((v, i) => {
        const row = document.createElement("div");
        row.className = "dyn-row";
        row.style.marginBottom = "8px";
        row.style.display = "flex";
        row.style.gap = "12px";
        row.style.alignItems = "center";
        
        const inp = document.createElement("input");
        inp.type = "text";
        inp.name = "alat[]";
        inp.value = v;
        inp.placeholder = "Ketik & pilih Alat Khusus...";
        inp.className = "form-control";
        
        let selectedTarif = 0;
        if (masterData.alat_details) {
          const found = masterData.alat_details.find(item => item.nama === v);
          if (found) selectedTarif = found.tarif;
        }
        
        row.appendChild(inp);
        
        const priceLabel = document.createElement("span");
        priceLabel.style.flex = "1";
        priceLabel.style.fontWeight = "bold";
        priceLabel.style.color = "var(--slate-600)";
        priceLabel.style.textAlign = "right";
        priceLabel.style.whiteSpace = "nowrap";
        priceLabel.style.minWidth = "120px";
        priceLabel.textContent = rupiah(selectedTarif);
        row.appendChild(priceLabel);
        
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn btn-sm btn-danger";
        btn.textContent = "Hapus";
        btn.style.flex = "0 0 auto";
        btn.onclick = () => { alatList.splice(i, 1); renderAlatRows(); };
        row.appendChild(btn);
        
        el.appendChild(row);

        // Setup autocomplete
        const suggestions = masterData.alat_details ? masterData.alat_details.map(x => x.nama) : [];
        makeAutocomplete(inp, suggestions, (selectedName) => {
          alatList[i] = selectedName;
          renderAlatRows();
        });

        // Give flex to autocomplete wrapper
        if (inp.parentNode && inp.parentNode.classList.contains("autocomplete-wrapper")) {
          inp.parentNode.style.flex = "3";
        }

        // Typing updates
        inp.addEventListener("input", (e) => {
          const val = e.target.value;
          alatList[i] = val;
          let rowTarif = 0;
          if (masterData.alat_details) {
            const found = masterData.alat_details.find(item => item.nama === val);
            if (found) rowTarif = found.tarif;
          }
          priceLabel.textContent = rupiah(rowTarif);
          recalculateAlatGrandTotal();
        });
      });
      
      const totalDiv = document.createElement("div");
      totalDiv.id = "alatGrandTotalContainer";
      totalDiv.style.marginTop = "8px";
      totalDiv.style.fontWeight = "bold";
      totalDiv.style.fontSize = "14px";
      totalDiv.style.color = "var(--primary-700)";
      el.appendChild(totalDiv);
      recalculateAlatGrandTotal();
    }

    function recalculateAlatGrandTotal() {
      let grandTotal = 0;
      const inputs = document.getElementsByName("alat[]");
      inputs.forEach(inp => {
        const val = inp.value;
        if (masterData.alat_details) {
          const found = masterData.alat_details.find(item => item.nama === val);
          if (found) grandTotal += found.tarif;
        }
      });
      const totalDiv = document.getElementById("alatGrandTotalContainer");
      if (totalDiv) {
        totalDiv.textContent = "Total Harga Alat Khusus: " + rupiah(grandTotal);
      }
    }

    document.getElementById("addAlat").onclick = () => { alatList.push(""); renderAlatRows(); };

    // Tambahan BMHP/Obat editor
    function renderTambahanRows() {
      const el = document.getElementById("tambahanEditor");
      el.innerHTML = "";

      tambahanList.forEach((v, i) => {
        const row = document.createElement("div");
        row.className = "dyn-row";
        row.style.marginBottom = "8px";
        row.style.display = "flex";
        row.style.gap = "12px";
        row.style.alignItems = "center";
        
        const inpN = document.createElement("input");
        inpN.type = "text";
        inpN.name = "tambahanBmhpNama[]";
        inpN.value = v.nama || "";
        inpN.placeholder = "Ketik & pilih Item BMHP / Obat...";
        inpN.className = "form-control";
        
        const inpQ = document.createElement("input");
        inpQ.type = "number";
        inpQ.name = "tambahanBmhpQty[]";
        inpQ.value = v.qty || "1";
        inpQ.placeholder = "Qty";
        inpQ.style.width = "70px";
        inpQ.style.flex = "0 0 70px";
        inpQ.style.textAlign = "center";
        
        let selectedTarif = 0;
        if (masterData.paket_bmhp) {
          const found = masterData.paket_bmhp.find(item => item.nama === v.nama);
          if (found) selectedTarif = found.tarif;
        }
        
        const rowTotal = (v.qty || 1) * selectedTarif;
        
        row.appendChild(inpN);
        row.appendChild(inpQ);
        
        const priceLabel = document.createElement("span");
        priceLabel.style.flex = "1.5";
        priceLabel.style.fontWeight = "bold";
        priceLabel.style.color = "var(--slate-600)";
        priceLabel.style.textAlign = "right";
        priceLabel.style.whiteSpace = "nowrap";
        priceLabel.style.minWidth = "220px";
        priceLabel.textContent = rupiah(selectedTarif) + " x " + (v.qty || 1) + " = " + rupiah(rowTotal);
        row.appendChild(priceLabel);
        
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn btn-sm btn-danger";
        btn.textContent = "Hapus";
        btn.style.flex = "0 0 auto";
        btn.onclick = () => { tambahanList.splice(i, 1); renderTambahanRows(); };
        row.appendChild(btn);
        
        el.appendChild(row);

        // Setup autocomplete
        const suggestions = masterData.paket_bmhp ? masterData.paket_bmhp.map(x => x.nama) : [];
        makeAutocomplete(inpN, suggestions, (selectedName) => {
          tambahanList[i].nama = selectedName;
          renderTambahanRows();
        });

        // Give flex to autocomplete wrapper
        if (inpN.parentNode && inpN.parentNode.classList.contains("autocomplete-wrapper")) {
          inpN.parentNode.style.flex = "3";
        }

        // Typing and Qty changes
        inpN.addEventListener("input", (e) => {
          tambahanList[i].nama = e.target.value;
          updateTambahanRowPrice(i, row, priceLabel);
        });

        inpQ.addEventListener("input", (e) => {
          tambahanList[i].qty = Number(e.target.value) || 0;
          updateTambahanRowPrice(i, row, priceLabel);
        });
      });
      
      const totalDiv = document.createElement("div");
      totalDiv.id = "tambahanGrandTotalContainer";
      totalDiv.style.marginTop = "8px";
      totalDiv.style.fontWeight = "bold";
      totalDiv.style.fontSize = "14px";
      totalDiv.style.color = "var(--primary-700)";
      el.appendChild(totalDiv);
      recalculateTambahanGrandTotal();
    }

    function updateTambahanRowPrice(index, rowEl, labelEl) {
      const inpN = rowEl.querySelector('input[name="tambahanBmhpNama[]"]');
      const inpQ = rowEl.querySelector('input[name="tambahanBmhpQty[]"]');
      const nameVal = inpN.value;
      const qtyVal = Number(inpQ.value) || 0;
      
      let rowTarif = 0;
      if (masterData.paket_bmhp) {
        const found = masterData.paket_bmhp.find(item => item.nama === nameVal);
        if (found) rowTarif = found.tarif;
      }
      const rowTotal = qtyVal * rowTarif;
      labelEl.textContent = rupiah(rowTarif) + " x " + qtyVal + " = " + rupiah(rowTotal);
      recalculateTambahanGrandTotal();
    }

    function recalculateTambahanGrandTotal() {
      let grandTotal = 0;
      const rows = document.getElementById("tambahanEditor").querySelectorAll(".dyn-row");
      rows.forEach(row => {
        const inpN = row.querySelector('input[name="tambahanBmhpNama[]"]');
        const inpQ = row.querySelector('input[name="tambahanBmhpQty[]"]');
        if (inpN && inpQ) {
          const nameVal = inpN.value;
          const qtyVal = Number(inpQ.value) || 0;
          if (masterData.paket_bmhp) {
            const found = masterData.paket_bmhp.find(item => item.nama === nameVal);
            if (found) grandTotal += qtyVal * found.tarif;
          }
        }
      });
      const totalDiv = document.getElementById("tambahanGrandTotalContainer");
      if (totalDiv) {
        totalDiv.textContent = "Total Harga Tambahan Paket: " + rupiah(grandTotal);
      }
    }

    document.getElementById("addTambahan").onclick = () => { tambahanList.push({ nama: "", qty: "1" }); renderTambahanRows(); };

    // Conditional selectors visibility
    document.getElementById("anestesiSel").addEventListener("change", function() {
      document.getElementById("anestesiLainnyaWrap").style.display = this.value === "Lainnya" ? "block" : "none";
    });
    document.getElementById("lokasiTindakanSel").addEventListener("change", function() {
      document.getElementById("lokasiLainnyaWrap").style.display = this.value === "Lainnya" ? "block" : "none";
    });
    document.getElementById("asalPasienSel").addEventListener("change", function() {
      document.getElementById("asalLainnyaWrap").style.display = this.value === "Lainnya" ? "block" : "none";
    });
    document.getElementById("ruangPascaSel").addEventListener("change", function() {
      document.getElementById("ruangPascaLainnyaWrap").style.display = this.value === "Lainnya" ? "block" : "none";
    });
    document.getElementById("penjaminSel").addEventListener("change", function() {
      document.getElementById("guarantorWrap").style.display = this.value === "Asuransi" ? "block" : "none";
    });

    // Form Submission (saves as Draft)
    document.getElementById("caseForm").addEventListener("submit", function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      
      fetch('{{ route("cases.store") }}', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken
        },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          toast(data.message, "success");
          setTimeout(() => {
            window.location.href = `/cases/${data.id}`;
          }, 1500);
        } else {
          toast(data.message, "error");
        }
      })
      .catch(err => {
        toast("Terjadi kesalahan sistem", "error");
      });
    });

    // Submit Direct (Saves as draft and then submits immediately)
    document.getElementById("btnSubmitDirect").addEventListener("click", function() {
      const form = document.getElementById("caseForm");
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const formData = new FormData(form);
      const btn = this;
      btn.disabled = true;
      const originalText = btn.textContent;
      btn.textContent = "Sedang mengirim...";

      fetch('{{ route("cases.store") }}', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken
        },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Trigger submit immediately
          fetch(`/cases/${data.id}/submit`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Content-Type': 'application/json'
            }
          })
          .then(res => res.json())
          .then(submitData => {
            if (submitData.success) {
              toast("Kasus berhasil diajukan", "success");
              setTimeout(() => {
                window.location.href = `/cases/${data.id}`;
              }, 1500);
            } else {
              toast("Kasus disimpan sebagai draft, tetapi gagal diajukan: " + submitData.message, "error");
              setTimeout(() => {
                window.location.href = `/cases/${data.id}`;
              }, 1500);
            }
          })
          .catch(err => {
            toast("Kasus disimpan sebagai draft, tetapi gagal diajukan karena kesalahan sistem", "error");
            setTimeout(() => {
              window.location.href = `/cases/${data.id}`;
            }, 1500);
          });
        } else {
          toast(data.message, "error");
          btn.disabled = false;
          btn.textContent = originalText;
        }
      })
      .catch(err => {
        toast("Terjadi kesalahan sistem saat menyimpan kasus", "error");
        btn.disabled = false;
        btn.textContent = originalText;
      });
    });
  </script>
@endsection
