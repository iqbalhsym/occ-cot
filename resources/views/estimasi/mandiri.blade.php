@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Estimasi Mandiri')
@section('page_title', 'Estimasi Mandiri')

@section('styles')
<style>
  .em-container {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 24px;
    margin-bottom: 40px;
  }
  @media (max-width: 1024px) {
    .em-container { grid-template-columns: 1fr; }
  }
  .form-card {
    background: var(--white);
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
  }
  .section-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--primary-800);
    border-bottom: 2px solid var(--primary-100);
    padding-bottom: 6px;
    margin-top: 24px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .section-title:first-of-type { margin-top: 0; }
  .editor-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
  }
  .editor-list {
    margin-bottom: 12px;
  }
  .summary-box {
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    padding: 16px;
    margin-top: 15px;
  }
  .summary-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13.5px;
    border-bottom: 1px dashed var(--slate-200);
  }
  .summary-row:last-child {
    border-bottom: none;
    font-weight: 700;
    font-size: 16px;
    color: var(--primary-900);
    padding-top: 12px;
  }
</style>
@endsection

@section('content')
<div class="em-container">
  
  <!-- LEFT: FORM INPUT -->
  <div class="form-card">
    <div style="font-size: 13px; color: var(--slate-600); margin-bottom: 20px; line-height: 1.5; background: var(--primary-50); border: 1px solid var(--primary-200); padding: 12px; border-radius: 8px;">
      💡 <strong>Kalkulator Estimasi Biaya COT Mandiri</strong>: Halaman simulasi ini berdiri sendiri dan tidak terhubung ke alur case aktif (tidak mengubah data pengajuan apa pun).
    </div>

    <!-- A. Identitas Pasien -->
    <div class="section-title">👤 A. Identitas Pasien</div>
    <div class="form-grid">
      <div class="field">
        <label>No. Rekam Medis (RM) <span class="hint">(opsional)</span></label>
        <div style="display:flex; gap:8px;">
          <input type="text" id="emRm" class="form-control" style="flex:1;">
          <button type="button" class="btn" id="emRmLookup" style="padding:0 14px;">Cari</button>
        </div>
        <span class="hint" id="emRmHint" style="font-weight:600; color:var(--primary-700);"></span>
      </div>
      <div class="field">
        <label>Nama Pasien</label>
        <input type="text" id="emNama" class="form-control">
      </div>
      <div class="field">
        <label>Jenis Kelamin</label>
        <select id="emJk" class="form-control">
          <option value="" selected>Pilih jenis kelamin…</option>
          <option value="L">Laki-laki</option>
          <option value="P">Perempuan</option>
        </select>
      </div>
      <div class="field">
        <label>Tanggal Lahir</label>
        <input type="date" id="emDob" class="form-control">
      </div>
    </div>
    <div class="form-grid" style="margin-top: 10px;">
      <div class="field">
        <label>Asal Pasien</label>
        <select id="emAsal" class="form-control">
          <option value="Rawat Jalan" selected>Rawat Jalan</option>
          <option value="Rawat Inap">Rawat Inap</option>
          <option value="IGD">IGD</option>
          <option value="ICU">ICU</option>
          <option value="Lainnya">Lainnya</option>
        </select>
      </div>
      <div class="field">
        <label>Penjamin</label>
        <select id="emPenjamin" class="form-control">
          <option value="Umum" selected>Umum</option>
          <option value="BPJS Kesehatan">BPJS Kesehatan</option>
          <option value="Asuransi">Asuransi / Jaminan Lain</option>
        </select>
      </div>
      <div class="field" id="emGuarantorWrap" style="display:none;">
        <label>Nama Asuransi / Guarantor</label>
        <input type="text" id="emGuarantor" class="form-control" placeholder="misal: Inhealth">
        <span class="hint" id="emGuarantorMappingHint" style="font-weight:700; color:var(--primary-700);"></span>
      </div>
    </div>

    <!-- B. Data Dokter -->
    <div class="section-title">🩺 B. Keterangan Dokter</div>
    <div class="form-grid">
      <div class="field" style="grid-column: span 2;">
        <label>DPJP Utama</label>
        <input type="text" id="emDpjp" class="form-control" placeholder="Ketik nama DPJP utama...">
      </div>
    </div>

    <!-- C. Data Tindakan & Medis -->
    <div class="section-title">📊 C. Tindakan Bedah & Diagnosa</div>
    <div class="form-grid">
      <div class="field" style="grid-column: span 2;">
        <label>Tindakan Utama <span class="hint">(Autocomplete)</span></label>
        <div class="autocomplete-wrapper">
          <input type="text" id="emTindakan" class="form-control" placeholder="Ketik nama tindakan bedah...">
          <div class="autocomplete-menu" id="tindakanMenu"></div>
        </div>
      </div>
      <div class="field">
        <label>Golongan Tindakan</label>
        <select id="emGolongan" class="form-control">
          <option value="KECIL">KECIL</option>
          <option value="SEDANG">SEDANG</option>
          <option value="BESAR">BESAR</option>
          <option value="KHUSUS A">KHUSUS A</option>
          <option value="KHUSUS B">KHUSUS B</option>
          <option value="KHUSUS C">KHUSUS C</option>
          <option value="CATHLAB RINGAN">CATHLAB RINGAN</option>
          <option value="CATHLAB SEDANG">CATHLAB SEDANG</option>
          <option value="CATHLAB BERAT">CATHLAB BERAT</option>
          <option value="CATHLAB KHUSUS A">CATHLAB KHUSUS A</option>
          <option value="CATHLAB KHUSUS B">CATHLAB KHUSUS B</option>
          <option value="BEDAH JANTUNG">BEDAH JANTUNG</option>
          <option value="NON GOLONGAN" selected>NON GOLONGAN (Input Manual)</option>
        </select>
      </div>
      <div class="field">
        <label>Kelas Perawatan</label>
        <select id="emKelas" class="form-control">
          <option value="Kelas 3" selected>Kelas 3</option>
          <option value="Kelas 2">Kelas 2</option>
          <option value="Kelas 1">Kelas 1</option>
          <option value="VIP">VIP</option>
          <option value="VVIP">VVIP</option>
        </select>
      </div>
    </div>

    <!-- D. Kondisi Tambahan -->
    <div class="section-title">⚡ D. Kondisi Operasi</div>
    <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:15px;">
      <label style="display:flex; align-items:center; gap:6px; font-weight:700; cursor:pointer;">
        <input type="checkbox" id="emCito" style="width:18px; height:18px;"> CITO (+25%)
      </label>
      <label style="display:flex; align-items:center; gap:6px; font-weight:700; cursor:pointer;">
        <input type="checkbox" id="emPenyulit" style="width:18px; height:18px;"> Penyulit / Khusus
      </label>
      <label style="display:flex; align-items:center; gap:6px; font-weight:700; cursor:pointer;">
        <input type="checkbox" id="emOdc" style="width:18px; height:18px;"> One Day Care (ODC - Kelas 3)
      </label>
      <label style="display:flex; align-items:center; gap:6px; font-weight:700; cursor:pointer;">
        <input type="checkbox" id="emOpII" style="width:18px; height:18px;" checked> Melibatkan Dokter Operator II
      </label>
    </div>

    <!-- E. Alat Khusus & BMHP Tambahan -->
    <div class="section-title">⚙️ E. Alat Khusus & BMHP Tambahan</div>
    
    <div style="font-weight:700; margin-bottom:8px; font-size:13px; color:var(--primary-800);">Alat Khusus Khusus</div>
    <div class="editor-list" id="alatList"></div>
    <button type="button" class="btn btn-sm" id="addAlatBtn" style="margin-bottom:15px;">+ Tambah Alat</button>

    <div style="font-weight:700; margin-bottom:8px; font-size:13px; color:var(--primary-800);">BMHP / Obat Khusus</div>
    <div class="editor-list" id="bmhpList"></div>
    <button type="button" class="btn btn-sm" id="addBmhpBtn">+ Tambah BMHP</button>
  </div>

  <!-- RIGHT: LIVE ESTIMASI PREVIEW -->
  <div>
    <div class="form-card" style="position: sticky; top: 20px;">
      <h3 style="margin-top: 0; color: var(--primary-900);">💰 Perkiraan Biaya</h3>
      
      <div style="overflow-y:auto; max-height:400px; border:1px solid var(--slate-200); border-radius:6px; margin-bottom:15px;">
        <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
          <thead>
            <tr style="background:var(--slate-100); border-bottom:1px solid var(--slate-200);">
              <th style="padding:8px; text-align:left;">Komponen</th>
              <th style="padding:8px; text-align:right; width:120px;">Nilai</th>
            </tr>
          </thead>
          <tbody id="estimasiTbody">
            <!-- Dynamic rows -->
          </tbody>
        </table>
      </div>

      <div class="summary-box">
        <div class="summary-row">
          <span>Jasa Medis & Sewa Kamar:</span>
          <span id="sumJasa">Rp 0</span>
        </div>
        <div class="summary-row">
          <span>Alat Khusus Khusus:</span>
          <span id="sumAlat">Rp 0</span>
        </div>
        <div class="summary-row">
          <span>BMHP & Obat Tambahan:</span>
          <span id="sumBmhp">Rp 0</span>
        </div>
        <div class="summary-row">
          <span>GRAND TOTAL:</span>
          <span id="sumGrand">Rp 0</span>
        </div>
      </div>

      <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
        <button type="button" class="btn btn-primary" id="saveHistoryBtn" style="width:100%;">
          💾 Simpan Ke History
        </button>
      </div>
    </div>
  </div>

</div>
@endsection

@section('scripts')
<script>
  // Dynamic datasets injected from backend parsed from HTML seeder
  const db = @json($db);
  const mappings = @json($mappings);

  const MASTER_TARIF_DB = db.masterTarifDb || {};
  const COT_DB = db.cotDb || {};
  const PAKET_BMHP_DB = db.paketBmhpDb || [];
  const NONPAKET_BMHP_DB = db.nonpaketBmhpDb || [];

  // Master lists for auto-completes
  let masterTindakan = [];
  let masterAlat = [];
  let masterBmhp = [];

  // Local active states
  let activeAlatItems = [];
  let activeBmhpItems = [];
  let calculatedRincian = [];
  let totalJasa = 0;
  let totalAlat = 0;
  let totalBmhp = 0;
  let grandTotal = 0;

  // Initialize Autocompletes
  document.addEventListener("DOMContentLoaded", () => {
    fetch("/api/master-data")
      .then(res => res.json())
      .then(data => {
        masterTindakan = data.tindakan || [];
        masterAlat = data.alat_details || [];
        masterBmhp = data.paket_bmhp || [];

        initTindakanAutocomplete();
        calculateCost();
      });
  });

  // Autocomplete for Tindakan
  function initTindakanAutocomplete() {
    const input = document.getElementById("emTindakan");
    const menu = document.getElementById("tindakanMenu");

    input.addEventListener("input", () => {
      const q = input.value.toLowerCase().trim();
      menu.innerHTML = "";
      if (!q) { menu.style.display = "none"; return; }

      const filtered = masterTindakan.filter(t => t.toLowerCase().includes(q)).slice(0, 10);
      if (!filtered.length) { menu.style.display = "none"; return; }

      filtered.forEach(t => {
        const item = document.createElement("div");
        item.className = "autocomplete-item";
        item.textContent = t;
        item.addEventListener("click", () => {
          input.value = t;
          menu.style.display = "none";
          
          // Lookup Golongan
          fetch(`/api/tindakan/lookup?nama=${encodeURIComponent(t)}`)
            .then(res => res.json())
            .then(resData => {
              if (resData.success && resData.golongan) {
                document.getElementById("emGolongan").value = resData.golongan.toUpperCase();
              }
              // If there are pre-filled BMHP packages, load them
              if (resData.success && resData.bmhp && resData.bmhp.length) {
                activeBmhpItems = [];
                resData.bmhp.forEach(b => {
                  activeBmhpItems.push({ nama: b.n, qty: b.q || 1, harga: b.h || 0 });
                });
                renderBmhpRows();
              }
              calculateCost();
            });
        });
        menu.appendChild(item);
      });
      menu.style.display = "block";
    });

    document.addEventListener("click", (e) => {
      if (e.target !== input) menu.style.display = "none";
    });
  }

  // Resolve Kelas Mapping
  const KELAS_KEY_MAP = {
    "KELAS 3": "k3",
    "KELAS 2": "k2",
    "KELAS 1": "k1",
    "VIP": "vip",
    "VVIP": "vvip"
  };

  // Resolve Guarantor Tariff Group
  function resolveKelompokTarif(guarantorName) {
    const nama = String(guarantorName || "").toLowerCase().trim();
    if (!nama) return { kelompokTarif: "2026", cob: false };

    // Find match in dynamic mappings
    for (const m of mappings) {
      if (nama.includes(String(m.pola).toLowerCase())) {
        return { kelompokTarif: m.kelompok_tarif, cob: !!m.cob };
      }
    }
    return { kelompokTarif: "2026", cob: false };
  }

  // Recalculate Jasa Medis (core math synced with prototype)
  function calculateCost() {
    const penjamin = document.getElementById("emPenjamin").value;
    const guarantor = document.getElementById("emGuarantor").value;
    const golongan = document.getElementById("emGolongan").value;
    const kelasRaw = document.getElementById("emKelas").value;
    const isCito = document.getElementById("emCito").checked;
    const isPenyulit = document.getElementById("emPenyulit").checked;
    const isOdc = document.getElementById("emOdc").checked;
    const hasOpII = document.getElementById("emOpII").checked;

    calculatedRincian = [];
    totalJasa = 0;

    if (golongan !== "NON GOLONGAN") {
      let resolvedGroup = "2026";
      let isCob = false;

      if (penjamin === "Umum" || penjamin === "BPJS Kesehatan") {
        resolvedGroup = "UMUM";
      } else {
        const resolved = resolveKelompokTarif(guarantor);
        resolvedGroup = resolved.kelompokTarif;
        isCob = resolved.cob;
      }

      // Determine correct tariff key inside groups (e.g. KECIL CITO or KECIL PENYULIT)
      let tariffKey = golongan;
      if (isCito) {
        tariffKey = `${golongan} CITO`;
      } else if (isPenyulit) {
        tariffKey = `${golongan} PENYULIT`;
      }

      const ratesGroup = MASTER_TARIF_DB[resolvedGroup];
      let resolvedKelas = kelasRaw;
      if (isOdc) {
        resolvedKelas = "Kelas 3"; // ODC forces class 3 pricing
      }

      const kelasCol = KELAS_KEY_MAP[resolvedKelas.toUpperCase()] || "k3";

      if (ratesGroup && ratesGroup[tariffKey]) {
        ratesGroup[tariffKey].forEach(row => {
          // Skip Operator II component if disabled
          if (!hasOpII && /operator\s*ii\b/i.test(row.komponen)) return;

          let val = isCob ? Number(row.cob || 0) : Number(row[kelasCol] || 0);
          
          // ODC Sewa Kamar adjustment: set to Rp 500,000 if ODC & Sewa Kamar
          if (isOdc && /sewa kamar/i.test(row.komponen)) {
            val = 500000;
          }

          if (val > 0) {
            calculatedRincian.push({ komponen: row.komponen, nilai: val });
            totalJasa += val;
          }
        });
      }
    }

    renderEstimasiPreview();
  }

  // Render Estimasi Table
  function renderEstimasiPreview() {
    const tbody = document.getElementById("estimasiTbody");
    tbody.innerHTML = "";

    calculatedRincian.forEach(r => {
      tbody.appendChild(createRow(r.komponen, r.nilai));
    });

    // Calculate Alat
    totalAlat = 0;
    activeAlatItems.forEach(item => {
      totalAlat += (item.harga || 0);
      tbody.appendChild(createRow(`[Alat] ${item.nama}`, item.harga));
    });

    // Calculate BMHP
    totalBmhp = 0;
    activeBmhpItems.forEach(item => {
      const lineCost = (item.qty || 1) * (item.harga || 0);
      totalBmhp += lineCost;
      tbody.appendChild(createRow(`[BMHP] ${item.nama} (x${item.qty})`, lineCost));
    });

    grandTotal = totalJasa + totalAlat + totalBmhp;

    document.getElementById("sumJasa").textContent = `Rp ${formatRupiah(totalJasa)}`;
    document.getElementById("sumAlat").textContent = `Rp ${formatRupiah(totalAlat)}`;
    document.getElementById("sumBmhp").textContent = `Rp ${formatRupiah(totalBmhp)}`;
    document.getElementById("sumGrand").textContent = `Rp ${formatRupiah(grandTotal)}`;
  }

  function createRow(compName, price) {
    const tr = document.createElement("tr");
    tr.style.borderBottom = "1px solid var(--slate-100)";
    tr.innerHTML = `
      <td style="padding:8px; font-weight:600; color:var(--slate-700);">${compName}</td>
      <td style="padding:8px; text-align:right; font-weight:700;">Rp ${formatRupiah(price)}</td>
    `;
    return tr;
  }

  function formatRupiah(num) {
    return new Intl.NumberFormat("id-ID").format(num);
  }

  // Handle Penjamin select
  document.getElementById("emPenjamin").addEventListener("change", function() {
    const wrap = document.getElementById("emGuarantorWrap");
    if (this.value === "Asuransi") {
      wrap.style.display = "";
    } else {
      wrap.style.display = "none";
      document.getElementById("emGuarantor").value = "";
      document.getElementById("emGuarantorMappingHint").textContent = "";
    }
    calculateCost();
  });

  // Handle Guarantor mapping search hint
  document.getElementById("emGuarantor").addEventListener("input", function() {
    const resolved = resolveKelompokTarif(this.value);
    const hint = document.getElementById("emGuarantorMappingHint");
    if (this.value.trim()) {
      hint.textContent = `Pola terdeteksi: Kelompok Tarif ${resolved.kelompokTarif} ${resolved.cob ? '(COB)' : ''}`;
    } else {
      hint.textContent = "";
    }
    calculateCost();
  });

  // Events listener for variables
  ["emGolongan", "emKelas", "emCito", "emPenyulit", "emOdc", "emOpII"].forEach(id => {
    document.getElementById(id).addEventListener("change", calculateCost);
  });

  // Add Alat Row
  document.getElementById("addAlatBtn").addEventListener("click", () => {
    const el = document.getElementById("alatList");
    const idx = activeAlatItems.length;

    const row = document.createElement("div");
    row.className = "editor-row";
    row.dataset.i = idx;

    let optionsHtml = '<option value="" selected>Pilih alat khusus...</option>';
    masterAlat.forEach(a => {
      optionsHtml += `<option value="${a.nama}" data-price="${a.tarif}">${a.nama} — Rp ${formatRupiah(a.tarif)}</option>`;
    });

    row.innerHTML = `
      <select class="form-control alatSel" style="flex:1;">
        ${optionsHtml}
      </select>
      <input type="number" class="form-control alatPrice" placeholder="Harga" style="width:120px; text-align:right;" readonly>
      <button type="button" class="btn btn-sm btn-danger removeAlatBtn">&times;</button>
    `;

    el.appendChild(row);
    activeAlatItems.push({ nama: "", harga: 0 });

    const sel = row.querySelector(".alatSel");
    const priceInput = row.querySelector(".alatPrice");

    sel.addEventListener("change", () => {
      const opt = sel.options[sel.selectedIndex];
      const price = Number(opt.dataset.price) || 0;
      priceInput.value = price;
      
      activeAlatItems[idx] = { nama: sel.value, harga: price };
      calculateCost();
    });

    row.querySelector(".removeAlatBtn").addEventListener("click", () => {
      activeAlatItems.splice(idx, 1);
      row.remove();
      // Re-index remaining rows to prevent offsets
      document.querySelectorAll("#alatList .editor-row").forEach((r, i) => r.dataset.i = i);
      calculateCost();
    });
  });

  // Add BMHP Row
  document.getElementById("addBmhpBtn").addEventListener("click", () => {
    const el = document.getElementById("bmhpList");
    const idx = activeBmhpItems.length;

    const row = document.createElement("div");
    row.className = "editor-row";
    row.dataset.i = idx;

    let optionsHtml = '<option value="" selected>Pilih BMHP/Obat...</option>';
    masterBmhp.forEach(b => {
      optionsHtml += `<option value="${b.nama}" data-price="${b.tarif}">${b.nama} — Rp ${formatRupiah(b.tarif)}</option>`;
    });

    row.innerHTML = `
      <select class="form-control bmhpSel" style="flex:1;">
        ${optionsHtml}
      </select>
      <input type="number" class="form-control bmhpQty" value="1" min="1" placeholder="Qty" style="width:60px; text-align:center;">
      <input type="number" class="form-control bmhpPrice" placeholder="Harga" style="width:120px; text-align:right;" readonly>
      <button type="button" class="btn btn-sm btn-danger removeBmhpBtn">&times;</button>
    `;

    el.appendChild(row);
    activeBmhpItems.push({ nama: "", qty: 1, harga: 0 });

    const sel = row.querySelector(".bmhpSel");
    const qtyInp = row.querySelector(".bmhpQty");
    const priceInput = row.querySelector(".bmhpPrice");

    const updateLine = () => {
      const opt = sel.options[sel.selectedIndex];
      const price = Number(opt.dataset.price) || 0;
      priceInput.value = price;
      
      activeBmhpItems[idx] = { 
        nama: sel.value, 
        qty: Number(qtyInp.value) || 1, 
        harga: price 
      };
      calculateCost();
    };

    sel.addEventListener("change", updateLine);
    qtyInp.addEventListener("input", updateLine);

    row.querySelector(".removeBmhpBtn").addEventListener("click", () => {
      activeBmhpItems.splice(idx, 1);
      row.remove();
      document.querySelectorAll("#bmhpList .editor-row").forEach((r, i) => r.dataset.i = i);
      calculateCost();
    });
  });

  // Render prefilled BMHP lines (for tindakan packages)
  function renderBmhpRows() {
    const el = document.getElementById("bmhpList");
    el.innerHTML = "";

    activeBmhpItems.forEach((b, idx) => {
      const row = document.createElement("div");
      row.className = "editor-row";
      row.dataset.i = idx;

      row.innerHTML = `
        <input class="form-control bmhpSel" style="flex:1; font-weight:600;" value="${b.nama}" readonly>
        <input type="number" class="form-control bmhpQty" value="${b.qty}" min="1" placeholder="Qty" style="width:60px; text-align:center;">
        <input type="number" class="form-control bmhpPrice" value="${b.harga}" placeholder="Harga" style="width:120px; text-align:right;" readonly>
        <button type="button" class="btn btn-sm btn-danger removeBmhpBtn">&times;</button>
      `;

      el.appendChild(row);

      const qtyInp = row.querySelector(".bmhpQty");
      qtyInp.addEventListener("input", () => {
        activeBmhpItems[idx].qty = Number(qtyInp.value) || 1;
        calculateCost();
      });

      row.querySelector(".removeBmhpBtn").addEventListener("click", () => {
        activeBmhpItems.splice(idx, 1);
        row.remove();
        document.querySelectorAll("#bmhpList .editor-row").forEach((r, i) => r.dataset.i = i);
        calculateCost();
      });
    });
  }

  // RM Autocomplete Lookup
  document.getElementById("emRmLookup").addEventListener("click", () => {
    const rmVal = document.getElementById("emRm").value.trim();
    const hint = document.getElementById("emRmHint");
    if (!rmVal) return;

    fetch(`/api/patients/${rmVal}`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.patient) {
          document.getElementById("emNama").value = data.patient.nama;
          document.getElementById("emJk").value = data.patient.jenis_kelamin;
          if (data.patient.tgl_lahir) {
            document.getElementById("emDob").value = data.patient.tgl_lahir;
          }
          hint.textContent = "✓ Data pasien terisi otomatis.";
        } else {
          hint.textContent = "Pasien Baru (Lengkapi data manual)";
        }
      })
      .catch(err => {
        hint.textContent = "Gagal memuat data.";
      });
  });

  // Save history via AJAX
  document.getElementById("saveHistoryBtn").addEventListener("click", () => {
    const rm = document.getElementById("emRm").value.trim();
    const name = document.getElementById("emNama").value.trim();
    const tindakan = document.getElementById("emTindakan").value.trim() || "Simulasi Tindakan Mandiri";
    const penjamin = document.getElementById("emPenjamin").value;
    const guarantor = document.getElementById("emGuarantor").value;
    const golongan = document.getElementById("emGolongan").value;
    const kelas = document.getElementById("emKelas").value;

    const rincianList = [];
    calculatedRincian.forEach(r => rincianList.push(r));
    activeAlatItems.forEach(a => {
      if (a.nama) rincianList.push({ komponen: `Alat: ${a.nama}`, nilai: a.harga });
    });
    activeBmhpItems.forEach(b => {
      if (b.nama) rincianList.push({ komponen: `BMHP: ${b.nama} (x${b.qty})`, nilai: b.harga * b.qty });
    });

    fetch("/api/estimasi-history", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
      },
      body: JSON.stringify({
        rm: rm,
        nama: name,
        tindakan: tindakan,
        penjamin: penjamin,
        guarantor: guarantor,
        golongan: golongan,
        kelas: kelas,
        total_estimasi: grandTotal,
        rincian: rincianList
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast("Perhitungan berhasil disimpan ke database.", "success");
      }
    })
    .catch(err => {
      showToast("Terjadi kesalahan jaringan.", "error");
    });
  });

  function showToast(message, type = 'success') {
    let wrap = document.getElementById("toastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "toastWrap";
      wrap.className = "toast-wrap";
      document.body.appendChild(wrap);
    }
    const t = document.createElement("div");
    t.className = `toast ${type === 'error' ? 'error' : 'success'}`;
    t.textContent = message;
    wrap.appendChild(t);
    setTimeout(() => { t.style.opacity = "0"; setTimeout(() => t.remove(), 400); }, 3000);
  }
</script>
@endsection
