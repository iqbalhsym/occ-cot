@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Edit Case ' . $case->id)
@section('page_title', 'Edit Case ' . $case->id)

@section('content')
  @php
    $lamaJam = 0;
    $lamaMenit = 0;
    if ($case->estimasi_lama_operasi) {
        if (is_numeric($case->estimasi_lama_operasi)) {
            $lamaJam = (int)$case->estimasi_lama_operasi;
        } else {
            if (preg_match('/(\d+)\s*Jam/i', $case->estimasi_lama_operasi, $m)) {
                $lamaJam = (int)$m[1];
            }
            if (preg_match('/(\d+)\s*Menit/i', $case->estimasi_lama_operasi, $m)) {
                $lamaMenit = (int)$m[1];
            }
        }
    }
  @endphp
  <div class="card">
    <h3>Edit Form Penjadwalan Tindakan (Nurse — Edit Draft)</h3>
    <div class="permission-note">Mengedit data penjadwalan. Anda dapat mengubah data identitas, dokter, medis, penjamin, alat, dan BMHP sebelum mengajukan kembali kasus ke workflow.</div>
    
    <form id="caseForm">
      @csrf
      @method('PUT')

      <h4>A. Identitas Pasien</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">No. Rekam Medis</label>
          <div style="display:flex; gap:8px;">
            <input name="rm" id="rmInput" required style="flex:1;" value="{{ $case->rm }}" readonly>
            <button type="button" class="btn btn-sm" id="rmLookup" disabled>Cari</button>
          </div>
          <span class="hint" id="rmHint">RM tidak dapat diubah setelah draft dibuat.</span>
        </div>
        <div class="field"><label class="req">Nama Pasien</label><input name="nama" id="namaInput" required value="{{ $case->nama }}"></div>
        <div class="field"><label>Jenis Kelamin</label><select name="jenisKelamin" id="jkInput"><option value="L" {{ $case->jenis_kelamin === 'L' ? 'selected' : '' }}>Laki-laki</option><option value="P" {{ $case->jenis_kelamin === 'P' ? 'selected' : '' }}>Perempuan</option></select></div>
        
        @php
          $dobD = ''; $dobM = ''; $dobY = '';
          if ($case->tgl_lahir) {
              $parts = explode('-', $case->tgl_lahir);
              if (count($parts) === 3) {
                  $dobY = $parts[0];
                  $dobM = $parts[1];
                  $dobD = $parts[2];
              }
          }
        @endphp
        <div class="field">
          <label>Tanggal Lahir <span class="hint">(ketik: tgl / bln / thn)</span></label>
          <div style="display:flex; gap:6px;">
            <input name="dobD" id="dobD" placeholder="Tgl" maxlength="2" inputmode="numeric" style="width:56px; text-align:center;" value="{{ $dobD }}">
            <input name="dobM" id="dobM" placeholder="Bln" maxlength="2" inputmode="numeric" style="width:56px; text-align:center;" value="{{ $dobM }}">
            <input name="dobY" id="dobY" placeholder="Thn" maxlength="4" inputmode="numeric" style="width:72px; text-align:center;" value="{{ $dobY }}">
          </div>
        </div>
        <div class="field full">
          <label>Lokasi Pengajuan (Ruangan)</label>
          <input name="lokasiPengajuan" id="lokasiPengajuan" placeholder="Ketik nama ruangan / lokasi" value="{{ !empty($case->lokasi_pengajuan) ? $case->lokasi_pengajuan[0] : '' }}">
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
        <div class="field full"><label class="req">Diagnosis</label><textarea name="diagnosis" required>{{ $case->diagnosis }}</textarea></div>
        <div class="field full">
          <label class="req">Tindakan <span class="hint">(pilih dari daftar paket, atau ketik bebas untuk non-paket)</span></label>
          <div id="tindakanEditor"></div>
          <button type="button" class="btn btn-sm" id="addTindakan" style="margin-top:8px;">+ Tambah Tindakan</button>
        </div>
      </div>
      <div id="tindakanAutofill"></div>

      <h4>D. Jenis Operasi (bisa dipilih lebih dari satu)</h4>
      @php
        $jo = $case->jenis_operasi ?? [];
      @endphp
      <div class="checkbox-grid" id="jenisOperasiBox">
        <label><input type="checkbox" name="jenisOperasi[]" class="jenisOperasiChk" value="CITO" {{ in_array('CITO', $jo) ? 'checked' : '' }}> CITO</label>
        <label><input type="checkbox" name="jenisOperasi[]" class="jenisOperasiChk" value="Elektif" {{ in_array('Elektif', $jo) ? 'checked' : '' }}> Elektif</label>
        <label><input type="checkbox" name="jenisOperasi[]" class="jenisOperasiChk" value="ODC" {{ in_array('ODC', $jo) ? 'checked' : '' }}> ODC</label>
      </div>

      <h4>E. Jenis Anestesi</h4>
      <div class="form-grid">
        <div class="field">
          <label>Jenis Anestesi</label>
          <select name="anestesi" id="anestesiSel">
            <option value="Lokal" {{ $case->anestesi === 'Lokal' ? 'selected' : '' }}>Lokal</option>
            <option value="Spinal" {{ $case->anestesi === 'Spinal' ? 'selected' : '' }}>Spinal</option>
            <option value="Epidural" {{ $case->anestesi === 'Epidural' ? 'selected' : '' }}>Epidural</option>
            <option value="General" {{ $case->anestesi === 'General' ? 'selected' : '' }}>General</option>
            <option value="Sedasi" {{ $case->anestesi === 'Sedasi' ? 'selected' : '' }}>Sedasi</option>
            <option value="Dalam Konfirmasi" {{ $case->anestesi === 'Dalam Konfirmasi' ? 'selected' : '' }}>Dalam Konfirmasi</option>
            <option value="Lainnya" {{ $case->anestesi === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
          </select>
        </div>
        <div class="field" id="anestesiLainnyaWrap" style="{{ $case->anestesi === 'Lainnya' ? '' : 'display:none;' }}">
          <label>Sebutkan Jenis Anestesi</label>
          <input name="anestesiLainnya" placeholder="mis. TIVA / kombinasi" value="{{ $case->anestesi_lainnya }}">
        </div>
      </div>

      <h4>F. Penjadwalan Operasi</h4>
      <div class="form-grid">
        <div class="field"><label>Tanggal Operasi — Pilihan 1</label><input type="date" name="tanggalPilihan1" value="{{ $case->tanggal_pilihan1 ? $case->tanggal_pilihan1->format('Y-m-d') : '' }}"></div>
        <div class="field"><label>Tanggal Operasi — Pilihan 2</label><input type="date" name="tanggalPilihan2" value="{{ $case->tanggal_pilihan2 ? $case->tanggal_pilihan2->format('Y-m-d') : '' }}"></div>
        <div class="field"><label>Jam Operasi</label><input type="time" name="jamOperasi" value="{{ $case->jam_operasi ?: '' }}"></div>
        <div class="field">
          <label class="req">Estimasi Lama Operasi</label>
          <div style="display:flex; gap:6px; align-items:center;">
            <input type="number" name="estimasiLamaOperasiJam" min="0" max="24" required placeholder="0" class="form-control" style="width:70px; display:inline-block;" value="{{ $lamaJam }}">
            <span style="font-size:13px; color:var(--slate-600);">Jam</span>
            <input type="number" name="estimasiLamaOperasiMenit" min="0" max="59" required placeholder="0" class="form-control" style="width:70px; display:inline-block;" value="{{ $lamaMenit }}">
            <span style="font-size:13px; color:var(--slate-600);">Menit</span>
          </div>
        </div>
      </div>

      <h4>G. Lokasi Tindakan (wajib)</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Lokasi Tindakan</label>
          <select name="lokasiTindakan" required id="lokasiTindakanSel">
            <option value="COT" {{ $case->lokasi_tindakan === 'COT' ? 'selected' : '' }}>COT</option>
            <option value="OT IGD" {{ $case->lokasi_tindakan === 'OT IGD' ? 'selected' : '' }}>OT IGD</option>
            <option value="Cathlab" {{ $case->lokasi_tindakan === 'Cathlab' ? 'selected' : '' }}>Cathlab</option>
            <option value="Endoskopi" {{ $case->lokasi_tindakan === 'Endoskopi' ? 'selected' : '' }}>Endoskopi</option>
            <option value="Lainnya" {{ $case->lokasi_tindakan === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
          </select>
        </div>
        <div class="field" id="lokasiLainnyaWrap" style="{{ $case->lokasi_tindakan === 'Lainnya' ? '' : 'display:none;' }}">
          <label>Sebutkan Lokasi Lainnya</label>
          <input name="lokasiTindakanLainnya" value="{{ $case->lokasi_tindakan_lainnya }}">
        </div>
      </div>

      <h4>H. Asal Pasien (wajib)</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Asal Pasien</label>
          <select name="asalPasien" required id="asalPasienSel">
            <option value="Rawat Inap" {{ $case->asal_pasien === 'Rawat Inap' ? 'selected' : '' }}>Rawat Inap</option>
            <option value="IGD" {{ $case->asal_pasien === 'IGD' ? 'selected' : '' }}>IGD</option>
            <option value="ICU" {{ $case->asal_pasien === 'ICU' ? 'selected' : '' }}>ICU</option>
            <option value="Rawat Jalan" {{ $case->asal_pasien === 'Rawat Jalan' ? 'selected' : '' }}>Rawat Jalan</option>
            <option value="Lainnya" {{ $case->asal_pasien === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
          </select>
        </div>
        <div class="field" id="asalLainnyaWrap" style="{{ $case->asal_pasien === 'Lainnya' ? '' : 'display:none;' }}">
          <label>Sebutkan Asal Lainnya</label>
          <input name="asalPasienLainnya" value="{{ $case->asal_pasien_lainnya }}">
        </div>
      </div>

      <h4>I. Ruang Pasca Operasi</h4>
      <div class="form-grid">
        <div class="field">
          <label>Ruang Pasca Operasi</label>
          <select name="ruangPascaOperasi" id="ruangPascaSel">
            <option value="Rawat Inap" {{ $case->ruang_pasca_operasi === 'Rawat Inap' ? 'selected' : '' }}>Rawat Inap</option>
            <option value="ICU" {{ $case->ruang_pasca_operasi === 'ICU' ? 'selected' : '' }}>ICU</option>
            <option value="Lainnya" {{ $case->ruang_pasca_operasi === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
          </select>
        </div>
        <div class="field" id="ruangPascaLainnyaWrap" style="{{ $case->ruang_pasca_operasi === 'Lainnya' ? '' : 'display:none;' }}">
          <label>Sebutkan Lainnya</label>
          <input name="ruangPascaOperasiLainnya" value="{{ $case->ruang_pasca_operasi_lainnya }}">
        </div>
      </div>

      <h4>J. Estimasi Rawat Inap</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Estimasi Rawat Inap (Hari)</label>
          <input type="number" min="0" max="365" name="estimasiRawatInap" required placeholder="mis. 3" class="form-control" value="{{ $case->estimasi_rawat_inap }}">
        </div>
      </div>

      <h4>K. Penjamin (wajib)</h4>
      <div class="form-grid">
        <div class="field">
          <label class="req">Penjamin</label>
          <select name="penjamin" required id="penjaminSel">
            <option value="Umum" {{ $case->penjamin === 'Umum' ? 'selected' : '' }}>Umum</option>
            <option value="BPJS Kesehatan" {{ $case->penjamin === 'BPJS Kesehatan' ? 'selected' : '' }}>BPJS Kesehatan</option>
            <option value="Asuransi" {{ $case->penjamin === 'Asuransi' ? 'selected' : '' }}>Asuransi Swasta / Lainnya</option>
          </select>
        </div>
        <div class="field" id="guarantorWrap" style="{{ $case->penjamin === 'Asuransi' ? '' : 'display:none;' }}">
          <label>Nama Guarantor / Asuransi</label>
          <input name="namaGuarantor" id="guarantorInput" value="{{ $case->nama_guarantor }}">
        </div>
      </div>

      <h4>M. Golongan Tindakan</h4>
      <div class="form-grid">
        <div class="field">
          <label>Golongan <span class="hint">(otomatis dari tindakan/spesialisasi)</span></label>
          <input type="text" id="golonganSelDisplay" readonly value="{{ $case->golongan ?: 'NON GOLONGAN' }}" class="form-control" style="background:var(--slate-100); cursor:not-allowed;">
          <input type="hidden" name="golongan" id="golonganSel" value="{{ $case->golongan ?: 'NON GOLONGAN' }}">
        </div>
        <div class="field">
          <label>Spesialisasi Operator</label>
          <input name="spesialisasiOp" id="spesialisasiInput" placeholder="mis. Ortopedi (SpOT)" value="{{ $case->spesialisasi_op }}">
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
        <button type="submit" class="btn btn-primary">Simpan Draft</button>
        <a href="{{ route('cases.show', $case->id) }}" class="btn" style="text-decoration:none;">Batal</a>
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

    // Pre-populate arrays from PHP
    let dpjpList = {!! json_encode($case->dpjp->pluck('nama')->toArray()) !!};
    if (dpjpList.length === 0) dpjpList = [""];
    
    let opList = {!! json_encode($case->operators->pluck('nama')->toArray()) !!};
    let opSpesList = {!! json_encode($case->operators->pluck('spesialisasi')->toArray()) !!};
    if (opList.length === 0) {
      opList = [""];
      opSpesList = [""];
    }

    let tindakanList = {!! json_encode($case->tindakan->pluck('nama')->toArray()) !!};
    if (tindakanList.length === 0) tindakanList = [""];
    
    let alatList = {!! json_encode($case->alat->pluck('nama')->toArray()) !!};
    
    let tambahanList = {!! json_encode($case->tambahanBmhp->where('jenis', 'tambahan')->map(fn($t) => ['nama' => $t->nama, 'qty' => $t->qty])->values()->toArray()) !!};

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
      applyTindakan(); // run once to load initial BMHP/golongan info
    }

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

    // Form Submission (updates existing Draft)
    document.getElementById("caseForm").addEventListener("submit", function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      
      fetch('{{ route("cases.update", $case->id) }}', {
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
  </script>
@endsection
