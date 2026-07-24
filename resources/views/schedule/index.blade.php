@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Monitoring Penjadwalan COT')
@section('page_title', 'Monitoring Penjadwalan COT')

@section('styles')
<style>
  .gantt-table {
    border-collapse: collapse;
    width: 100%;
    min-width: 900px;
    background: var(--white);
  }
  .gantt-table th {
    background: var(--slate-50, #f8fafc);
    border: 1px solid var(--slate-200);
    padding: 8px 6px;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--slate-500);
    position: sticky;
    top: 0;
    z-index: 10;
  }
  .gantt-hour {
    border: 1px solid var(--slate-200);
    background: var(--slate-50, #f8fafc);
    font-size: 11.5px;
    font-weight: 700;
    color: var(--slate-500);
    text-align: center;
    vertical-align: top;
    padding: 6px 4px;
    white-space: nowrap;
    width: 60px;
  }
  .gantt-cell {
    border: 1px solid var(--slate-200);
    vertical-align: top;
    padding: 4px;
    min-width: 130px;
    height: 100%;
    transition: background 0.1s ease, outline 0.1s ease;
  }
  .gantt-cell.drag-over {
    background: var(--teal-50, #e6fcf5) !important;
    outline: 2px dashed var(--teal-500);
    outline-offset: -2px;
  }
  .op-block {
    background: #E6FFFA;
    border: 1px solid #319795;
    color: #234E52;
    border-radius: 6px;
    padding: 6px 7px;
    margin-bottom: 5px;
    cursor: pointer;
    font-size: 11px;
    line-height: 1.3;
    transition: transform 0.1s ease, box-shadow 0.1s ease;
    text-align: left;
  }
  .op-block.op-draggable {
    cursor: grab;
  }
  .op-block.op-draggable:active {
    cursor: grabbing;
  }
  .op-block.op-dragging {
    opacity: 0.4;
  }
  .op-block:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
  }
  .op-block.op-conflict {
    background: #FFF5F5 !important;
    border-color: #E53E3E !important;
    color: #742A2A !important;
  }
  .op-block.op-lock {
    background: repeating-linear-gradient(45deg, #f7fafc, #f7fafc 8px, #edf2f7 8px, #edf2f7 16px) !important;
    border: 1px dashed #718096 !important;
    color: #4a5568 !important;
  }
  .op-block-time {
    font-weight: 800;
  }
  .op-block-name {
    font-weight: 700;
    margin-top: 2px;
  }
  .op-block-sub {
    color: var(--slate-500);
    margin-top: 1px;
  }
  .note-inline {
    font-size: 10px;
    margin-top: 4px;
    line-height: 1.3;
    text-align: left;
    word-break: break-word;
  }
  .unit-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
  }
  .unit-status-card {
    background: var(--white);
    border: 1px solid var(--slate-200);
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
  }
  .unit-status-card .u-name {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--slate-400);
    margin-bottom: 4px;
    font-weight: 700;
  }
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    backdrop-filter: blur(4px);
  }
  .modal {
    background: var(--white);
    border-radius: 12px;
    max-width: 600px;
    width: 100%;
    padding: 24px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    max-height: 90vh;
    overflow-y: auto;
  }
  .modal h3 {
    margin-top: 0;
    font-weight: 700;
    color: var(--slate-800);
  }
  .modal table td {
    padding: 6px 8px;
    font-size: 13px;
    border-bottom: 1px solid var(--slate-100);
  }
  .active-tab-btn {
    background: var(--primary-600) !important;
    color: var(--white) !important;
    border-color: var(--primary-700) !important;
  }
  .permission-note {
    background: var(--slate-50);
    border-left: 4px solid var(--slate-300);
    padding: 8px 12px;
    font-size: 12px;
    line-height: 1.4;
    color: var(--slate-600);
    border-radius: 0 6px 6px 0;
    margin-bottom: 12px;
  }
</style>
@endsection

@php
  $realRole = Auth::user()->role;
  $activeRole = session('role', $realRole);
@endphp

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; margin-bottom:40px;">
  
  <!-- Tab Controls -->
  <div class="card">
    <h3 style="margin-bottom:8px;">
      Monitoring Penjadwalan COT
      <span id="diagnosticTab" style="font-size:12px; font-weight:normal; background:#E2E8F0; color:#334155; padding:2px 8px; border-radius:4px; margin-left:8px;">Loading...</span>
    </h3>
    <div class="permission-note" id="viewExplanation">
      Tampilan tabel untuk aksi cepat sehari-hari.
    </div>
    
    @if($activeRole === 'AdminCOT')
      <div class="permission-note" style="border-left-color: var(--primary-500); background: #EFF6FF; color: #1E40AF;">
        ⚙️ <strong>Pengaturan Sistem (Admin COT)</strong> — Turn Over Time (TOT): jeda antar operasi untuk pembersihan kamar &amp; alat.
        <select id="totSetting" style="margin-left:8px; padding:4px 8px; border-radius:4px; font-weight:600;">
          <option value="30" {{ $totMinutes == 30 ? 'selected' : '' }}>30 menit</option>
          <option value="45" {{ $totMinutes == 45 ? 'selected' : '' }}>45 menit</option>
          <option value="60" {{ $totMinutes == 60 ? 'selected' : '' }}>60 menit</option>
          <option value="90" {{ $totMinutes == 90 ? 'selected' : '' }}>90 menit</option>
        </select>
      </div>
    @endif

    <div class="btn-row" style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
      <a href="javascript:void(0)" class="btn btn-primary" id="viewTabelBtn" onclick="switchTab('tabel')" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">📋 Tampilan Tabel</a>
      <a href="javascript:void(0)" class="btn" id="viewTimelineBtn" onclick="switchTab('timeline')" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">🗓️ Tampilan Timeline</a>
      <a href="javascript:void(0)" class="btn" id="viewResourceBtn" onclick="switchTab('resource')" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">🔧 Resource Monitor</a>
      <a href="javascript:void(0)" class="btn" id="viewKonfigurasiBtn" onclick="switchTab('konfigurasi')" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">🗂️ Konfigurasi Alokasi Slot</a>
    </div>

    <!-- Filters -->
    <div class="form-grid" id="monFilterGrid" style="margin-top:16px;">
      <div class="field" id="filterTanggalField" style="opacity: 0.5;">
        <label>Tanggal <span class="hint">(tidak berlaku di tampilan tabel)</span></label>
        <input type="date" id="monTanggal" value="{{ date('Y-m-d') }}" onchange="updateMonitorFilter('tanggal', this.value)" disabled>
      </div>
      <div class="field">
        <label>Ruang Operasi</label>
        <select id="monRuang" onchange="updateMonitorFilter('ruang', this.value)">
          <option value="">Semua Ruang</option>
          <option value="OT 1">OT 1</option>
          <option value="OT 2">OT 2</option>
          <option value="OT 3">OT 3</option>
          <option value="OT 4">OT 4</option>
          <option value="OT 5">OT 5</option>
          <option value="OT 6">OT 6</option>
          <option value="Hybrid">Hybrid</option>
          <option value="OT lt 5">OT lt 5</option>
          <option value="IGD">IGD</option>
          <option value="Cathlab">Cathlab</option>
          <option value="ICU">ICU</option>
        </select>
      </div>
      <div class="field">
        <label>Status Operasi</label>
        <select id="monStatus" onchange="updateMonitorFilter('opStatus', this.value)">
          <option value="">Semua Status</option>
          <option value="Belum Mulai">Belum Mulai</option>
          <option value="Berlangsung">Berlangsung</option>
          <option value="Selesai">Selesai</option>
          <option value="Ditunda">Ditunda</option>
          <option value="Reschedule">Reschedule</option>
          <option value="Batal">Batal</option>
        </select>
      </div>
      <div class="field">
        <label>Penjamin</label>
        <select id="monPenjamin" onchange="updateMonitorFilter('penjamin', this.value)">
          <option value="">Semua</option>
          <option value="Asuransi">Asuransi</option>
          <option value="Umum">Umum</option>
          <option value="BPJS">BPJS Kesehatan</option>
        </select>
      </div>
      <div class="field full">
        <label>Cari Pasien / RM / Operator / Diagnosis / Tindakan</label>
        <input type="text" id="monSearch" placeholder="Ketik untuk mencari..." oninput="updateMonitorFilter('search', this.value)">
      </div>
    </div>
  </div>

  <!-- Dynamic Content Body -->
  <div id="monBody"></div>

</div>

<!-- Modal Container -->
<div id="modalRoot"></div>
@endsection

@section('scripts')
<script>
  // Global error boundary to capture and display any hidden JavaScript runtime exceptions
  window.onerror = function(message, source, lineno, colno, error) {
    alert("JS Run Error: " + message + "\nLine: " + lineno + "\nSource: " + source);
    return false;
  };

  // Bootstrap data from controller
  let cases = Array.isArray(@json($cases)) ? @json($cases) : [];
  let slotConfigsData = Array.isArray(@json($slotConfigs)) ? @json($slotConfigs) : [];
  let resourceMasterData = Array.isArray(@json($resourceMaster)) ? @json($resourceMaster) : [];
  let doctors = Array.isArray(@json($doctors)) ? @json($doctors) : [];
  let alkesKhusus = Array.isArray(@json($alkesKhusus ?? [])) ? @json($alkesKhusus) : [];
  let totMinutesData = {{ $totMinutes }} || 45;

  const OK_ROOM_LIST = ["OT 1", "OT 2", "OT 3", "OT 4", "OT 5", "OT 6", "Hybrid", "OT lt 5", "IGD", "Cathlab", "ICU"];
  const SPECIALTIES = ["Bedah Saraf", "Orthopaedi", "Bedah Anak", "Digestif", "Obgyn", "Urologi", "Bedah Umum", "Mata", "Lain-lain"];
  const SLOT_CONFIG_STATUS = ["Prioritas Spesialis", "Standby / Buffer", "Tidak Digunakan"];

  const state = {
    role: '{{ $activeRole }}',
    monitorFilter: {
      view: 'tabel', // default tab
      tanggal: '{{ date("Y-m-d") }}',
      ruang: '',
      opStatus: '',
      penjamin: '',
      search: ''
    }
  };

  window.switchTab = function(viewName) {
    state.monitorFilter.view = viewName;
    renderMonitoringPage();
  };

  window.updateMonitorFilter = function(key, val) {
    state.monitorFilter[key] = val;
    renderMonitoringPage();
  };

  window.generateRecapReport = function() {
    const tglMulai = document.getElementById("rekapTglMulai").value;
    const tglSelesai = document.getElementById("rekapTglSelesai").value;
    const dokterName = document.getElementById("rekapDokter").value;

    if (!tglMulai || !tglSelesai) {
      alert("Pilih tanggal mulai dan tanggal selesai terlebih dahulu.");
      return;
    }

    const container = document.getElementById("rekapResultContainer");
    if (!container) return;

    const allScheduled = cases.filter(c => c && c.adminCot && c.adminCot.finalDone && c.adminCot.jadwal && c.adminCot.jadwal.tanggal);

    const filtered = allScheduled.filter(c => {
      const t = c.adminCot.jadwal.tanggal;
      const dateIn = t >= tglMulai && t <= tglSelesai;
      if (!dateIn) return false;

      if (dokterName) {
        const isOperator = (c.operatorList || []).some(o => String(o).toLowerCase().trim() === dokterName.toLowerCase().trim());
        const isDpjp = (c.dpjpList || []).some(d => String(d).toLowerCase().trim() === dokterName.toLowerCase().trim());
        if (!isOperator && !isDpjp) return false;
      }
      return true;
    });

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="footer-hint" style="text-align:center; padding:20px; border:1px dashed var(--slate-300); border-radius:6px; background:var(--white);">
          Tidak ada data jadwal operasi ditemukan untuk kriteria filter ini.
        </div>
      `;
      return;
    }

    filtered.sort((a, b) => {
      const ta = `${a.adminCot.jadwal.tanggal} ${a.adminCot.jadwal.jam || ""}`;
      const tb = `${b.adminCot.jadwal.tanggal} ${b.adminCot.jadwal.jam || ""}`;
      return ta.localeCompare(tb);
    });

    const rows = filtered.map((c, idx) => {
      const st = deriveOpStatus(c);
      const formattedDate = new Date(c.adminCot.jadwal.tanggal + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
      return `
        <tr style="border-bottom:1px solid var(--slate-100);">
          <td style="padding:10px;">${idx + 1}</td>
          <td style="padding:10px;">
            <strong>${esc(c.nama)}</strong><br>
            <span class="footer-hint" style="font-size:11px;">RM ${esc(c.rm)}</span>
          </td>
          <td style="padding:10px;">
            ${esc(formattedDate)}<br>
            <span class="footer-hint" style="font-size:11px;">⏰ ${esc(c.adminCot.jadwal.jam || '-')}</span>
          </td>
          <td style="padding:10px;"><span class="badge" style="background:#E2E8F0; color:#334155; padding:2px 6px; border-radius:4px; font-size:11.5px;">${esc(c.adminCot.jadwal.ruang || '-')}</span></td>
          <td style="padding:10px; max-width:200px; font-size:12px; line-height:1.4;">${esc((c.tindakanList || []).join(", ") || "-")}</td>
          <td style="padding:10px; font-size:12px; line-height:1.4;">${esc((c.operatorList || []).join(", ") || "-")}</td>
          <td style="padding:10px; font-size:12px; white-space:nowrap;">Gol. ${esc(c.golongan || "-")} / ${esc(c.kelasPerawatan || "-")}</td>
          <td style="padding:10px;"><span class="badge-status ${OP_STATUS_COLOR[st] || 'st-Menunggu'}" style="font-size:10px; padding:2px 6px; border-radius:4px;">${esc(st)}</span></td>
          <td style="padding:10px; font-size:12px;">${esc(c.penjamin || "-")}</td>
        </tr>
      `;
    }).join("");

    container.innerHTML = `
      <div style="margin-top:16px; border:1px solid var(--slate-200); border-radius:8px; background:#F8FAFC; padding:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
          <h4 style="margin:0; color:var(--primary-900); font-weight:700;">Hasil Rekapitulasi: ${filtered.length} Jadwal Operasi</h4>
          <button type="button" class="btn btn-sm" onclick="exportRecapToExcel()" style="background:#16A34A; color:var(--white); border-color:#16A34A; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            <span>📥</span> Ekspor ke Excel
          </button>
        </div>
        <div style="overflow-x:auto;">
          <table id="rekapTableElement" style="width:100%; border-collapse:collapse; background:var(--white); border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <thead>
              <tr style="background:var(--slate-50); border-bottom:2px solid var(--slate-200); color:var(--slate-700); font-size:12px; text-transform:uppercase; letter-spacing:0.3px;">
                <th style="padding:10px; text-align:left;">#</th>
                <th style="padding:10px; text-align:left;">Pasien</th>
                <th style="padding:10px; text-align:left;">Jadwal</th>
                <th style="padding:10px; text-align:left;">Ruang</th>
                <th style="padding:10px; text-align:left;">Tindakan</th>
                <th style="padding:10px; text-align:left;">Operator</th>
                <th style="padding:10px; text-align:left;">Gol/Kelas</th>
                <th style="padding:10px; text-align:left;">Status</th>
                <th style="padding:10px; text-align:left;">Penjamin</th>
              </tr>
            </thead>
            <tbody>
              ${rows}
            </tbody>
          </table>
        </div>
      </div>
    `;
  };

  window.exportRecapToExcel = function() {
    const table = document.getElementById("rekapTableElement");
    if (!table) {
      alert("Tidak ada data tabel untuk diekspor.");
      return;
    }

    const tglMulai = document.getElementById("rekapTglMulai").value;
    const tglSelesai = document.getElementById("rekapTglSelesai").value;
    const filename = `Rekap_Jadwal_Operasi_${tglMulai}_sd_${tglSelesai}.xls`;

    // Create spreadsheet template with styling
    const html = table.outerHTML;
    const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8;" });

    const link = document.createElement("a");
    if (link.download !== undefined) {
      const url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute("download", filename);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  };

  function loadSlotConfig() {
    return slotConfigsData || [];
  }
  
  function loadResourceMaster() {
    return resourceMasterData || [];
  }
  
  function getTOT() {
    return totMinutesData || 45;
  }

  // Color mappings
  const OP_STATUS_COLOR = {
    "Belum Mulai": "st-Menunggu",
    "Berlangsung": "st-op-hijau",
    "Selesai": "st-op-biru",
    "Ditunda": "st-op-kuning",
    "Reschedule": "st-Reschedule",
    "Batal": "st-op-merah",
  };

  // Helper formatting functions
  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function listOrDash(arr) {
    if (!arr || !arr.length) return '-';
    return arr.map(esc).join(', ');
  }

  function rupiah(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
  }

  function todayISO() {
    const d = new Date();
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }

  // Estimation Helpers
  function getEffectiveDurasiJam(c) {
    const n = Number(c.adminCot && c.adminCot.estimasiJam);
    if (n && n > 0) return Math.min(n, 12);
    return 2.0; // fallback default
  }

  function getSlotMinutes(c) {
    return getEffectiveDurasiJam(c) * 60 + getTOT();
  }

  function slotRange(c) {
    const j = (c.adminCot && c.adminCot.jadwal) || {};
    const start = new Date(`${j.tanggal || "1970-01-01"}T${(j.jam || "00:00")}:00`).getTime();
    return [start, start + getSlotMinutes(c) * 60000];
  }

  function operationRange(c) {
    const j = (c.adminCot && c.adminCot.jadwal) || {};
    const start = new Date(`${j.tanggal || "1970-01-01"}T${(j.jam || "00:00")}:00`).getTime();
    return [start, start + getEffectiveDurasiJam(c) * 3600000];
  }

  function fmtHHMM(ms) {
    const d = new Date(ms);
    return String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0");
  }

  function deriveOpStatus(c) {
    if (c.status === "Cancelled") return "Batal";
    if (c.adminCot && c.adminCot.tindakanSelesai) return "Selesai";
    if (c.adminCot && c.adminCot.decision === "DalamKonfirmasi") return "Ditunda";
    try {
      const [start, end] = operationRange(c);
      const now = Date.now();
      if (now >= start && now <= end) return "Berlangsung";
      if (now > end) return "Selesai";
    } catch (e) {}
    return "Belum Mulai";
  }

  // Conflict computing
  function computeConflicts(list) {
    const conflicts = {};
    const addC = (id, msg) => { 
      if (!conflicts[id]) conflicts[id] = []; 
      if (!conflicts[id].includes(msg)) conflicts[id].push(msg); 
    };
    const overlap = (a, b) => a[0] < b[1] && b[0] < a[1];
    
    for (let i = 0; i < list.length; i++) {
      for (let k = i + 1; k < list.length; k++) {
        const a = list[i], b = list[k];
        let ra, rb;
        try { ra = slotRange(a); rb = slotRange(b); } catch (e) { continue; }
        if (!overlap(ra, rb)) continue;
        
        if (a.adminCot.jadwal.ruang && a.adminCot.jadwal.ruang === b.adminCot.jadwal.ruang) {
          addC(a.id, `⚠️ Bentrok ruang ${a.adminCot.jadwal.ruang} dengan ${b.nama} (${b.adminCot.jadwal.jam})`);
          addC(b.id, `⚠️ Bentrok ruang ${b.adminCot.jadwal.ruang} dengan ${a.nama} (${a.adminCot.jadwal.jam})`);
        }
        const opsA = (a.operatorList || []).map(o => (o || "").trim().toLowerCase()).filter(Boolean);
        const opsB = (b.operatorList || []).map(o => (o || "").trim().toLowerCase()).filter(Boolean);
        const sharedOp = opsA.find(o => opsB.includes(o));
        if (sharedOp) {
          addC(a.id, `⚠️ Operator "${sharedOp}" bentrok jadwal dengan ${b.nama} (${b.adminCot.jadwal.ruang || "-"}, ${b.adminCot.jadwal.jam})`);
          addC(b.id, `⚠️ Operator "${sharedOp}" bentrok jadwal dengan ${a.nama} (${a.adminCot.jadwal.ruang || "-"}, ${a.adminCot.jadwal.jam})`);
        }
        const anesA = (a.dokterAnestesi || "").trim().toLowerCase();
        const anesB = (b.dokterAnestesi || "").trim().toLowerCase();
        if (anesA && anesA === anesB) {
          addC(a.id, `⚠️ Dokter Anestesi "${a.dokterAnestesi}" bentrok jadwal dengan ${b.nama} (${b.adminCot.jadwal.ruang || "-"}, ${b.adminCot.jadwal.jam})`);
          addC(b.id, `⚠️ Dokter Anestesi "${b.dokterAnestesi}" bentrok jadwal dengan ${a.nama} (${a.adminCot.jadwal.ruang || "-"}, ${a.adminCot.jadwal.jam})`);
        }
        const alatA = (a.adminCot.alat || []).map(x => String(x).trim().toLowerCase()).filter(Boolean);
        const alatB = (b.adminCot.alat || []).map(x => String(x).trim().toLowerCase()).filter(Boolean);
        const sharedAlat = alatA.find(x => alatB.includes(x));
        if (sharedAlat) {
          addC(a.id, `⚠️ Alat "${sharedAlat}" dibutuhkan bersamaan dengan ${b.nama} (${b.adminCot.jadwal.ruang || "-"}, ${b.adminCot.jadwal.jam})`);
          addC(b.id, `⚠️ Alat "${sharedAlat}" dibutuhkan bersamaan dengan ${a.nama} (${a.adminCot.jadwal.ruang || "-"}, ${a.adminCot.jadwal.jam})`);
        }
      }
    }
    return conflicts;
  }

  function getSlotConfigsFor(ruang, tanggal) {
    return loadSlotConfig().filter(cfg => cfg.ruang === ruang && cfg.tanggalMulai <= tanggal && tanggal <= (cfg.tanggalSelesai || cfg.tanggalMulai));
  }

  function getAlokasiAlatUntukRuang(ruang, tanggal) {
    const norm = s => String(s || "").toLowerCase().trim();
    const set = new Set();
    getSlotConfigsFor(ruang, tanggal).filter(c => c.status !== "Tidak Digunakan").forEach(c => (c.alat || []).forEach(a => a && set.add(norm(a))));
    return set;
  }

  // Hard Slot Validation against configuration limits
  function validasiAlokasiSlot(ruang, tanggal, alatNamesNeeded, jamMulai, durasiSlotMenit, excludeCaseId) {
    const norm = s => String(s || "").toLowerCase().trim();
    const needed = (alatNamesNeeded || []).map(norm).filter(Boolean);

    // Check Alkes Khusus room restrictions
    if (needed.length && typeof alkesKhusus !== 'undefined' && Array.isArray(alkesKhusus)) {
      for (const n of needed) {
        const found = alkesKhusus.find(ak => ak.nama.toLowerCase().trim() === n);
        if (found && Array.isArray(found.allowed_rooms)) {
          const isAllowed = found.allowed_rooms.some(r => r.toLowerCase().trim() === ruang.toLowerCase().trim());
          if (!isAllowed) {
            return { ok: false, alasan: `Alat khusus "${found.nama}" tidak diperbolehkan digunakan di ruangan "${ruang}".` };
          }
        }
      }
    }

    const cfgs = getSlotConfigsFor(ruang, tanggal);
    if (!cfgs.length) return { ok: false, alasan: `Ruang ${ruang} belum memiliki Konfigurasi Alokasi Slot Operasi pada ${tanggal}.` };
    const cfgAktif = cfgs.filter(c => c.status !== "Tidak Digunakan");
    if (!cfgAktif.length) return { ok: false, alasan: `Ruang ${ruang} berstatus "Tidak Digunakan" pada ${tanggal}.` };
    if (needed.length) {
      const allocated = getAlokasiAlatUntukRuang(ruang, tanggal);
      const missing = needed.filter(n => !allocated.has(n));
      if (missing.length) return { ok: false, alasan: `Alat ${missing.join(", ")} tidak dialokasikan ke ruang ${ruang} pada ${tanggal} sesuai Konfigurasi Alokasi Slot Operasi.` };
    }
    const start = new Date(`${tanggal}T${jamMulai}:00`).getTime();
    const end = start + durasiSlotMenit * 60000;
    const withinWindow = cfgAktif.some(c => start >= new Date(`${tanggal}T${c.jamMulai}:00`).getTime() && end <= new Date(`${tanggal}T${c.jamSelesai}:00`).getTime());
    if (!withinWindow) return { ok: false, alasan: `Jam ${jamMulai} berada di luar jam operasional yang dikonfigurasi untuk ruang ${ruang} pada ${tanggal}.` };
    
    const clash = cases.some(c => {
      if (!c.adminCot || !c.adminCot.finalDone || !c.adminCot.jadwal || c.id === excludeCaseId || c.adminCot.jadwal.tanggal !== tanggal || c.adminCot.jadwal.ruang !== ruang) return false;
      try { const [s, e] = slotRange(c); return start < e && s < end; } catch (e) { return false; }
    });
    if (clash) return { ok: false, alasan: `Slot ${jamMulai} di ruang ${ruang} pada ${tanggal} sudah terpakai case lain (konflik jadwal).` };
    return { ok: true };
  }

  function cariSlotDiRuang(ruang, tanggal, alatNamesNeeded, jamPreferred, durasiSlotMenit, excludeCaseId) {
    const cfgs = getSlotConfigsFor(ruang, tanggal);
    if (!cfgs.length) return { ok: false, alasan: "belum ada Konfigurasi Alokasi Slot Operasi" };
    const cfgAktif = cfgs.filter(c => c.status !== "Tidak Digunakan");
    if (!cfgAktif.length) return { ok: false, alasan: "berstatus Tidak Digunakan" };
    
    let sebabWaktu = null;
    for (const cfg of cfgAktif.slice().sort((a, b) => a.jamMulai.localeCompare(b.jamMulai))) {
      const windowStart = new Date(`${tanggal}T${cfg.jamMulai}:00`).getTime();
      const windowEnd = new Date(`${tanggal}T${cfg.jamSelesai}:00`).getTime();
      const preferredMs = jamPreferred ? new Date(`${tanggal}T${jamPreferred}:00`).getTime() : windowStart;
      let cursor = Math.max(windowStart, preferredMs);
      let adaRuangWaktu = false;
      while (cursor + durasiSlotMenit * 60000 <= windowEnd) {
        adaRuangWaktu = true;
        const candEnd = cursor + durasiSlotMenit * 60000;
        const clash = cases.some(c => {
          if (!c.adminCot || !c.adminCot.finalDone || !c.adminCot.jadwal || c.id === excludeCaseId || c.adminCot.jadwal.tanggal !== tanggal || c.adminCot.jadwal.ruang !== ruang) return false;
          try { const [s, e] = slotRange(c); return cursor < e && s < candEnd; } catch (e) { return false; }
        });
        if (!clash) return { ok: true, jam: fmtHHMM(cursor) };
        sebabWaktu = "konflik jadwal — slot sudah terpakai case lain";
        cursor += 30 * 60000;
      }
    }
    return { ok: false, alasan: sebabWaktu || "slot tidak tersedia" };
  }

  // Drag & Drop Reschedule execution
  function performDragReschedule(caseId, ruang, jamStr) {
    const cc = cases.find(x => x.id === caseId);
    if (!cc || !cc.adminCot || !cc.adminCot.finalDone) return;
    if (cc.adminCot.jadwal && ruang === cc.adminCot.jadwal.ruang && jamStr === cc.adminCot.jadwal.jam) return;
    
    const tanggal = state.monitorFilter.tanggal;
    const estimasiJam = getEffectiveDurasiJam(cc);
    const durasiSlotMenit = estimasiJam * 60 + getTOT();
    const alatNames = (cc.adminCot.alat || []).map(a => typeof a === 'string' ? a : a.nama).filter(Boolean);
    
    const v = validasiAlokasiSlot(ruang, tanggal, alatNames, jamStr, durasiSlotMenit, cc.id);
    const clashOnly = !v.ok && /terpakai case lain/.test(v.alasan || "");
    
    if (!v.ok && !clashOnly) {
      toast(`⛔ Tidak bisa memindah ke ${ruang} ${jamStr}: ${v.alasan}`, "error");
      return;
    }

    if (v.ok) {
      if (!confirm(`Pindahkan jadwal ${cc.nama} ke ${ruang} pukul ${jamStr}?`)) return;
      saveDragRescheduleToServer(caseId, tanggal, jamStr, ruang);
      return;
    }

    // Clash only - offer auto-reschedule shift logic
    const startMs = new Date(`${tanggal}T${jamStr}:00`).getTime();
    const endMs = startMs + durasiSlotMenit * 60000;
    const bentrok = cases.filter(x => {
      if (x.id === cc.id || !x.adminCot.finalDone || x.adminCot.jadwal.tanggal !== tanggal || x.adminCot.jadwal.ruang !== ruang) return false;
      try { const [s, e] = slotRange(x); return startMs < e && s < endMs; } catch (er) { return false; }
    });
    
    const namaBentrok = bentrok.map(x => x.nama).join(", ") || "case lain";
    if (!confirm(`Slot ${ruang} ${jamStr} bentrok dengan: ${namaBentrok}.\n\nGeser otomatis SELURUH jadwal yang bertabrakan ke slot kosong berikutnya di ruang yang sama?\n\nKlik Batal untuk membatalkan.`)) return;
    
    // Save master case
    saveDragRescheduleToServer(caseId, tanggal, jamStr, ruang, function() {
      // cascade shifting
      let iter = 0;
      const antrean = bentrok.slice();
      let shiftSuccessCount = 0;
      let totalToShift = antrean.length;

      function shiftNext() {
        if (!antrean.length || iter >= 30) {
          toast(`Jadwal ${cc.nama} dipindahkan. ${shiftSuccessCount}/${totalToShift} case bentrok berhasil digeser otomatis.`, "success");
          return;
        }
        iter++;
        const cur = antrean.shift();
        const curEstimasi = getEffectiveDurasiJam(cur);
        const curDurasi = curEstimasi * 60 + getTOT();
        const curAlat = (cur.adminCot.alat || []).map(a => typeof a === 'string' ? a : a.nama).filter(Boolean);
        const r = cariSlotDiRuang(ruang, tanggal, curAlat, jamStr, curDurasi, cur.id);
        
        if (r.ok) {
          saveDragRescheduleToServer(cur.id, tanggal, r.jam, ruang, function() {
            shiftSuccessCount++;
            shiftNext();
          }, false); // don't show individual toast
        } else {
          toast(`Gagal geser otomatis ${cur.nama}: ${r.alasan || 'tidak ada slot kosong'}`, 'error');
          shiftNext();
        }
      }
      shiftNext();
    });
  }

  function saveDragRescheduleToServer(caseId, tanggal, jam, ruang, callback = null, showToast = true) {
    fetch(`/schedule/drag-reschedule/${caseId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ tanggal, jam, ruang })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (showToast) toast(data.message, 'success');
        // Update local state
        const idx = cases.findIndex(x => x.id === caseId);
        if (idx !== -1) {
          cases[idx] = data.case;
        }
        renderMonitoringPage();
        if (callback) callback();
      } else {
        toast(data.message, 'error');
      }
    })
    .catch(err => {
      toast('Terjadi kesalahan koneksi.', 'error');
    });
  }

  // AJAX settings updates
  function saveSettingsToServer() {
    fetch('{{ route("schedule.settings.save") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({
        totMinutes: totMinutesData,
        slotConfigs: slotConfigsData,
        resourceMaster: resourceMasterData
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        toast('Pengaturan berhasil disimpan.', 'success');
      } else {
        toast('Gagal menyimpan pengaturan.', 'error');
      }
    })
    .catch(err => {
      toast('Koneksi terputus saat menyimpan pengaturan.', 'error');
    });
  }

  // AJAX workflow completions
  function markTindakanSelesai(caseId) {
    if (!confirm('Tandai tindakan operasi sudah dilakukan?')) return;
    
    fetch(`/schedule/tindakan-selesai/${caseId}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken
      }
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        toast(data.message, 'success');
        // Update local cases tindakanSelesai flag
        const idx = cases.findIndex(x => x.id === caseId);
        if (idx !== -1) {
          cases[idx].adminCot.tindakanSelesai = true;
        }
        document.getElementById("modalRoot").innerHTML = "";
        renderMonitoringPage();
      } else {
        toast(data.message, 'error');
      }
    })
    .catch(err => {
      toast('Terjadi kesalahan koneksi.', 'error');
    });
  }

  function cancelTindakan(caseId) {
    const note = prompt('Masukkan alasan pembatalan tindakan:');
    if (note === null) return;
    if (!note.trim()) {
      toast('Alasan pembatalan wajib diisi.', 'error');
      return;
    }

    fetch(`/schedule/batal-tindakan/${caseId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ note })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        toast(data.message, 'success');
        const idx = cases.findIndex(x => x.id === caseId);
        if (idx !== -1) {
          cases[idx].status = 'Cancelled';
        }
        document.getElementById("modalRoot").innerHTML = "";
        renderMonitoringPage();
      } else {
        toast(data.message, 'error');
      }
    })
    .catch(err => {
      toast('Terjadi kesalahan koneksi.', 'error');
    });
  }

  // --- Rendering UI views ---

  function renderMonitoringPage() {
    try {
      const f = state.monitorFilter;
      const allScheduled = cases.filter(c => c && c.adminCot && c.adminCot.finalDone && c.adminCot.jadwal && c.adminCot.jadwal.tanggal);

      const diag = document.getElementById("diagnosticTab");
      if (diag) {
        diag.textContent = "View: " + f.view + " | Cases: " + cases.length + " | Scheduled: " + allScheduled.length;
      }

      // Setup active state tab buttons — use btn-primary like the mockup
      const btnTabel = document.getElementById("viewTabelBtn");
      if (btnTabel) btnTabel.className = "btn" + (f.view === 'tabel' ? ' btn-primary' : '');
      const btnTimeline = document.getElementById("viewTimelineBtn");
      if (btnTimeline) btnTimeline.className = "btn" + (f.view === 'timeline' ? ' btn-primary' : '');
      const btnResource = document.getElementById("viewResourceBtn");
      if (btnResource) btnResource.className = "btn" + (f.view === 'resource' ? ' btn-primary' : '');
      const btnKonfigurasi = document.getElementById("viewKonfigurasiBtn");
      if (btnKonfigurasi) btnKonfigurasi.className = "btn" + (f.view === 'konfigurasi' ? ' btn-primary' : '');

      const monBody    = document.getElementById("monBody");
      const explanation = document.getElementById("viewExplanation");
      const dateField  = document.getElementById("filterTanggalField");
      const monTanggal = document.getElementById("monTanggal");
      const monRuang   = document.getElementById("monRuang");
      const monStatus  = document.getElementById("monStatus");
      const monPenjamin= document.getElementById("monPenjamin");
      const monSearch  = document.getElementById("monSearch");

      if (f.view === 'tabel') {
        if (explanation) explanation.textContent = "Tampilan tabel untuk aksi cepat sehari-hari. Filter Tanggal tidak berlaku di tampilan ini (pakai Ruang/Status/Penjamin/Cari).";
        if (dateField) dateField.style.opacity = "0.5";
        if (monTanggal) monTanggal.disabled = true;
        if (monRuang)   { monRuang.disabled = false;   monRuang.style.opacity = '1'; }
        if (monStatus)  { monStatus.disabled = false;  monStatus.style.opacity = '1'; }
        if (monPenjamin){ monPenjamin.disabled = false; monPenjamin.style.opacity = '1'; }
        if (monSearch)  { monSearch.disabled = false;  monSearch.style.opacity = '1'; }
        if (monBody) monBody.innerHTML = buildTableView(allScheduled);
      } else if (f.view === 'timeline') {
        if (explanation) explanation.textContent = "Dashboard interaktif jadwal operasi. Klik blok operasi pada timeline untuk detail lengkap.";
        if (dateField) dateField.style.opacity = "1";
        if (monTanggal) monTanggal.disabled = false;
        if (monRuang)   { monRuang.disabled = false;   monRuang.style.opacity = '1'; }
        if (monStatus)  { monStatus.disabled = false;  monStatus.style.opacity = '1'; }
        if (monPenjamin){ monPenjamin.disabled = false; monPenjamin.style.opacity = '1'; }
        if (monSearch)  { monSearch.disabled = false;  monSearch.style.opacity = '1'; }
        if (monBody) monBody.innerHTML = buildTimelineView(allScheduled);
        wireDragAndDrop();
      } else if (f.view === 'resource') {
        if (explanation) explanation.textContent = "Visualisasi ketersediaan peralatan khusus COT secara real-time. Filter Ruang/Status/Penjamin tidak berlaku di tampilan ini.";
        if (dateField) dateField.style.opacity = "1";
        if (monTanggal) monTanggal.disabled = false;
        if (monRuang)   { monRuang.disabled = true;    monRuang.style.opacity = '0.5'; }
        if (monStatus)  { monStatus.disabled = true;   monStatus.style.opacity = '0.5'; }
        if (monPenjamin){ monPenjamin.disabled = true;  monPenjamin.style.opacity = '0.5'; }
        if (monSearch)  { monSearch.disabled = false;  monSearch.style.opacity = '1'; }
        if (monBody) monBody.innerHTML = buildResourceView(allScheduled);
        wireResourceEditor();
      } else if (f.view === 'konfigurasi') {
        if (explanation) explanation.textContent = "Pengaturan operasional untuk alokasi ketersediaan ruang operasi dan alat pendukung per tanggal.";
        if (dateField) dateField.style.opacity = "0.5";
        if (monTanggal) monTanggal.disabled = true;
        if (monRuang)   { monRuang.disabled = true;    monRuang.style.opacity = '0.5'; }
        if (monStatus)  { monStatus.disabled = true;   monStatus.style.opacity = '0.5'; }
        if (monPenjamin){ monPenjamin.disabled = true;  monPenjamin.style.opacity = '0.5'; }
        if (monSearch)  { monSearch.disabled = true;   monSearch.style.opacity = '0.5'; }
        if (monBody) monBody.innerHTML = buildKonfigurasiView();
        wireConfigEditor();
      }
    } catch (e) {
      alert("Render Error: " + e.message + "\nStack: " + e.stack);
    }
  }

  // Tab 1: TABLE VIEW
  function buildTableView(allScheduled) {
    const f = state.monitorFilter;
    const isAdminCot = (state.role === 'AdminCOT');

    let list = allScheduled.filter(c => {
      if (f.ruang && (!c.adminCot || !c.adminCot.jadwal || c.adminCot.jadwal.ruang !== f.ruang)) return false;
      if (f.penjamin && c.penjamin !== f.penjamin) return false;
      if (f.opStatus && deriveOpStatus(c) !== f.opStatus) return false;
      if (f.search) {
        const q = f.search.toLowerCase();
        const hay = [c.nama, c.rm, c.penjamin, ...(c.operatorList || []), ...(c.tindakanList || [])].join(" ").toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    list.sort((a, b) => {
      const doneA = (a.adminCot && a.adminCot.tindakanSelesai) ? 1 : 0;
      const doneB = (b.adminCot && b.adminCot.tindakanSelesai) ? 1 : 0;
      if (doneA !== doneB) return doneA - doneB;
      const ta = (a.adminCot && a.adminCot.jadwal) ? `${a.adminCot.jadwal.tanggal || ""} ${a.adminCot.jadwal.jam || ""}` : "";
      const tb = (b.adminCot && b.adminCot.jadwal) ? `${b.adminCot.jadwal.tanggal || ""} ${b.adminCot.jadwal.jam || ""}` : "";
      return ta.localeCompare(tb);
    });

    const colCount = isAdminCot ? 10 : 9;
    const rows = list.map(c => {
      const j = (c.adminCot && c.adminCot.jadwal) || {};
      const selesai = !!(c.adminCot && c.adminCot.tindakanSelesai);
      const batal = c.status === "Cancelled";
      let tglLabel = "-";
      try {
        tglLabel = j.tanggal ? new Date(j.tanggal + "T00:00:00").toLocaleDateString("id-ID", { weekday: "short", day: "2-digit", month: "short", year: "numeric" }) : "-";
      } catch (e) {
        tglLabel = j.tanggal || "-";
      }

      return `<tr data-id="${c.id}" ${(selesai || batal) ? 'style="opacity:0.65; background:#F8FAFC;"' : ''}>
        <td>${esc(tglLabel)}</td>
        <td>${esc(j.jam || "-")}</td>
        <td><span class="chip">${esc(j.ruang || "-")}</span></td>
        <td><strong>${esc(c.nama)}</strong><br><span class="footer-hint" style="font-size:11px;">RM ${esc(c.rm)}</span></td>
        <td>${listOrDash(c.tindakanList)}</td>
        <td>${listOrDash(c.operatorList)}</td>
        <td><span class="chip" style="background:#F1F5F9; color:#475569;">${esc(c.golongan)}</span> <span style="font-size:11.5px; font-weight:600; color:var(--slate-500);">${esc(c.kelasPerawatan)}</span></td>
        <td><span class="badge-status ${c.status === 'Completed' ? 'st-Disetujui' : 'st-Menunggu'}">${esc(c.status)}</span></td>
        <td>${batal ? `<span class="badge-status st-Cancelled">🛑 Batal Tindakan</span>` : selesai ? `<span class="badge-status st-Disetujui">✅ Selesai</span>` : `<span class="badge-status st-Menunggu">Belum Mulai</span>`}</td>
        ${isAdminCot ? `<td style="white-space:nowrap;">
          <button type="button" class="btn btn-sm btn-revisi" onclick="window.location.href='/cases/${c.id}'">✏️ Detail &amp; Reschedule</button>
          ${!selesai && !batal ? `<button type="button" class="btn btn-sm btn-primary" onclick="markTindakanSelesai('${c.id}')">✅ Selesai</button>` : ''}
          ${!selesai && !batal ? `<button type="button" class="btn btn-sm btn-danger" onclick="cancelTindakan('${c.id}')">🛑 Batal</button>` : ''}
        </td>` : ''}
      </tr>`;
    }).join("");

    return `
      <div class="card">
        <h3>📋 Tabel Jadwal Operasi (${list.length})</h3>
        <div class="permission-note">Menampilkan seluruh case terjadwal, diurutkan tanggal terdekat.</div>
        <div style="overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Ruang</th>
                <th>Pasien</th>
                <th>Tindakan</th>
                <th>Operator</th>
                <th>Golongan/Kelas</th>
                <th>Status Adm</th>
                <th>Status Tindakan</th>
                ${isAdminCot ? '<th>Aksi</th>' : ''}
              </tr>
            </thead>
            <tbody>
              ${rows || `<tr><td colspan="${colCount}" class="footer-hint" style="text-align:center; padding:20px;">Belum ada case yang terjadwal.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  // Tab 2: TIMELINE VIEW
  function buildTimelineView(allScheduled) {
    const f = state.monitorFilter;
    const forDate = allScheduled.filter(c => c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.tanggal === f.tanggal);
    const conflicts = computeConflicts(forDate);

    let filtered = forDate.filter(c => {
      if (f.ruang && (!c.adminCot || !c.adminCot.jadwal || c.adminCot.jadwal.ruang !== f.ruang)) return false;
      if (f.penjamin && c.penjamin !== f.penjamin) return false;
      if (f.opStatus && deriveOpStatus(c) !== f.opStatus) return false;
      if (f.search) {
        const q = f.search.toLowerCase();
        const hay = [c.nama, c.rm, c.penjamin, ...(c.operatorList || []), ...(c.tindakanList || [])].join(" ").toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    const total = forDate.length;
    const elektif = forDate.filter(c => (c.jenisOperasi || []).includes("Elektif")).length;
    const cito = forDate.filter(c => (c.jenisOperasi || []).includes("CITO")).length;
    const selesai = forDate.filter(c => deriveOpStatus(c) === "Selesai").length;
    const belumOK = cases.filter(c => c && c.status !== "Cancelled" && c.status !== "Returned" && c.adminCot && c.adminCot.required && !c.adminCot.finalDone).length;
    const konflikCount = Object.keys(conflicts).length;

    const rooms = f.ruang ? [f.ruang] : OK_ROOM_LIST;
    const slotMin = 30, startHour = 6, endHour = 22;
    const totalSlots = ((endHour - startHour) * 60) / slotMin;
    const gridStartMs = new Date(`${f.tanggal}T00:00:00`).getTime() + startHour * 3600000;
    
    const slotIndexForMs = ms => Math.round((ms - gridStartMs) / (slotMin * 60000));

    const roomBlocks = {}; 
    rooms.forEach(r => (roomBlocks[r] = []));

    filtered.forEach(c => {
      const ruang = (c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.ruang) || "";
      if (!roomBlocks[ruang]) return;
      
      let sMs, eMs;
      try { [sMs, eMs] = slotRange(c); } catch (e) { return; }
      
      let startIdx = slotIndexForMs(sMs);
      let span = Math.max(1, Math.ceil((eMs - sMs) / (slotMin * 60000)));
      if (startIdx < 0) { span += startIdx; startIdx = 0; }
      span = Math.min(span, totalSlots - startIdx);
      if (span <= 0 || startIdx >= totalSlots) return;
      
      roomBlocks[ruang].push({ startIdx, span, c });
    });

    // Merge settings configurations
    const cfgForDate = loadSlotConfig().filter(cfg => cfg.tanggalMulai <= f.tanggal && f.tanggal <= (cfg.tanggalSelesai || cfg.tanggalMulai));
    cfgForDate.forEach(cfg => {
      const room = cfg.ruang;
      if (!roomBlocks[room]) return;
      
      const sMs = new Date(`${f.tanggal}T${cfg.jamMulai}:00`).getTime();
      const eMs = new Date(`${f.tanggal}T${cfg.jamSelesai}:00`).getTime();
      let startIdx = slotIndexForMs(sMs);
      let span = Math.max(1, Math.ceil((eMs - sMs) / (slotMin * 60000)));
      if (startIdx < 0) { span += startIdx; startIdx = 0; }
      span = Math.min(span, totalSlots - startIdx);
      if (span <= 0 || startIdx >= totalSlots) return;
      
      const overlapsCase = roomBlocks[room].some(b => b.c && startIdx < b.startIdx + b.span && b.startIdx < startIdx + span);
      if (overlapsCase) return;
      
      roomBlocks[room].push({ startIdx, span, cfg });
    });

    const utilisasi = {};
    rooms.forEach(r => {
      const usedMin = roomBlocks[r].reduce((s, b) => s + (b.c ? b.span * slotMin : 0), 0);
      utilisasi[r] = Math.min(100, Math.round((usedMin / (totalSlots * slotMin)) * 100));
    });

    const coverage = {};
    rooms.forEach(r => { coverage[r] = new Array(totalSlots).fill(null); });
    Object.keys(roomBlocks).forEach(r => {
      roomBlocks[r].forEach(b => {
        for (let i = b.startIdx; i < b.startIdx + b.span && i < totalSlots; i++) {
          coverage[r][i] = i === b.startIdx ? b : "covered";
        }
      });
    });

    function cfgBlock(cfg) {
      const statusClass = cfg.status === "Tidak Digunakan" ? "st-Cancelled" : cfg.status === "Standby / Buffer" ? "st-Menunggu" : "st-Disetujui";
      return `
        <div class="op-block op-lock" title="Konfigurasi Alokasi Slot Operasi — master acuan sistem">
          <div class="op-block-time">🗂️ ${esc(cfg.jamMulai)}–${esc(cfg.jamSelesai)}</div>
          <div class="op-block-name">Slot Alokasi</div>
          <div style="margin-top:2px;"><span class="badge-status ${statusClass}" style="font-size:9px;">${esc(cfg.status)}</span></div>
          ${cfg.alat && cfg.alat.length ? `<div class="op-block-sub">Alat: ${esc(cfg.alat.join(", "))}</div>` : `<div class="op-block-sub">Tidak ada alat dialokasikan</div>`}
          ${cfg.keterangan ? `<div class="op-block-sub">${esc(cfg.keterangan)}</div>` : ""}
          ${state.role === "AdminCOT" ? `<button type="button" class="btn btn-sm btn-danger" onclick="deleteSlotConfig('${esc(cfg.id)}')" style="margin-top:4px; padding:2px 6px; font-size:10px;">Hapus</button>` : ""}
        </div>
      `;
    }

    function opBlock(c) {
      const st = deriveOpStatus(c);
      const hasConflict = !!conflicts[c.id];
      const [sMs, eMs] = slotRange(c);
      const canDrag = state.role === "AdminCOT" && st !== "Selesai" && st !== "Batal";
      const alatChips = (c.adminCot && c.adminCot.alat || []).slice(0, 3).map(a => `<span class="chip" style="font-size:9px;">${esc(a)}</span>`).join("");
      
      return `
        <div class="op-block ${hasConflict ? 'op-conflict' : ''} ${canDrag ? 'op-draggable' : ''}" 
             data-id="${c.id}" 
             ${canDrag ? 'draggable="true"' : ''} 
             onclick="openOpDetailModal('${c.id}')"
             title="Klik untuk detail${canDrag ? ' — geser (drag) blok ini untuk memindah jadwal' : ''}">
          <div class="op-block-time">${fmtHHMM(sMs)}–${fmtHHMM(eMs)} <span class="footer-hint" style="font-weight:400; font-size:9px;">(buffer ${getTOT()}m)</span></div>
          <div class="op-block-name">${esc(c.nama)}</div>
          <div class="op-block-sub">${esc((c.operatorList || [])[0] || "-")}</div>
          <div class="op-block-sub">${esc((c.tindakanList || [])[0] || "-")}</div>
          <div class="op-block-sub">${esc(c.dokterAnestesi || "-")}</div>
          <div style="margin-top:3px;"><span class="badge-status ${OP_STATUS_COLOR[st] || 'st-Menunggu'}" style="font-size:9.5px;">${esc(st)}</span></div>
          ${alatChips ? `<div style="margin-top:3px;">${alatChips}</div>` : ""}
          ${hasConflict ? conflicts[c.id].map(m => `<div class="note-inline" style="color:#E53E3E; font-weight:700;">${esc(m)}</div>`).join("") : ""}
        </div>
      `;
    }

    const ganttHeader = `<tr><th style="width:56px;">Jam</th>${rooms.map(r => `<th>${esc(r)}<div class="footer-hint" style="font-weight:400; font-size:10px; margin-top:2px;">Utilisasi ${utilisasi[r]}%</div></th>`).join("")}</tr>`;
    const ganttRows = [];
    for (let i = 0; i < totalSlots; i++) {
      const ms = gridStartMs + i * slotMin * 60000;
      const label = fmtHHMM(ms);
      const cells = rooms.map(r => {
        const cell = coverage[r][i];
        if (cell === "covered") return "";
        if (cell === null) return `<td class="gantt-cell" data-room="${esc(r)}" data-jam="${esc(label)}" style="height:24px;"></td>`;
        if (cell.cfg) return `<td class="gantt-cell" data-room="${esc(r)}" data-jam="${esc(label)}" rowspan="${cell.span}">${cfgBlock(cell.cfg)}</td>`;
        return `<td class="gantt-cell" data-room="${esc(r)}" data-jam="${esc(label)}" rowspan="${cell.span}">${opBlock(cell.c)}</td>`;
      }).join("");
      ganttRows.push(`<tr><td class="gantt-hour">${label}</td>${cells}</tr>`);
    }

    const icuList = forDate.filter(c => c.ruangPascaOperasi === "ICU");
    const icuHtml = icuList.length
      ? icuList.map(c => `<div style="margin-bottom:8px; border-bottom:1px solid var(--slate-100); padding-bottom:6px;"><strong>${esc(c.nama)}</strong> <span class="footer-hint">RM ${esc(c.rm)} — ${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.ruang || "-")} ${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.jam || "")} — ${esc((c.tindakanList || [])[0] || "-")}</span></div>`).join("")
      : `<span class="footer-hint">Tidak ada kebutuhan ICU pada tanggal ini.</span>`;

    let formattedDate = '';
    try {
      formattedDate = new Date(f.tanggal + "T00:00:00").toLocaleDateString("id-ID", { weekday: "long", day: "2-digit", month: "long", year: "numeric" });
    } catch(err) {
      formattedDate = f.tanggal;
    }

    return `
      <div class="unit-status-grid">
        <div class="unit-status-card"><div class="u-name">Total Operasi</div><div style="font-size:20px;font-weight:800;">${total}</div></div>
        <div class="unit-status-card"><div class="u-name">Elektif</div><div style="font-size:20px;font-weight:800;">${elektif}</div></div>
        <div class="unit-status-card"><div class="u-name">CITO</div><div style="font-size:20px;font-weight:800;color:var(--red-500);">${cito}</div></div>
        <div class="unit-status-card"><div class="u-name">Selesai</div><div style="font-size:20px;font-weight:800;color:var(--teal-500);">${selesai}</div></div>
        <div class="unit-status-card"><div class="u-name">Belum Dapat Jadwal</div><div style="font-size:20px;font-weight:800;">${belumOK}</div></div>
        <div class="unit-status-card"><div class="u-name">Konflik</div><div style="font-size:20px;font-weight:800;color:${konflikCount ? "var(--red-500)" : "inherit"};">${konflikCount}</div></div>
      </div>

      <div class="card">
        <h3>Timeline Ruang Operasi — ${esc(formattedDate)}</h3>
        ${state.role === "AdminCOT" ? `<div class="permission-note">🖱️ Geser (drag) blok operasi ke sel jam/ruang lain untuk memindah jadwal.</div>` : ""}
        <div style="overflow-x:auto;">
          <table class="gantt-table">
            <thead>${ganttHeader}</thead>
            <tbody>${ganttRows.join("")}</tbody>
          </table>
        </div>
        ${filtered.length === 0 ? `<div class="footer-hint" style="margin-top:10px; text-align:center;">Tidak ada operasi terjadwal sesuai filter pada tanggal ini.</div>` : ""}
      </div>
      
      <div class="card">
        <h3>🛏️ Monitoring ICU/PACU</h3>
        ${icuHtml}
      </div>

      <div class="card" style="margin-top:20px;">
        <h3 style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">📊 Rekap Laporan Jadwal Operasi</h3>
        <p class="hint" style="margin:0 0 16px 0;">Buat rekapitulasi data jadwal tindakan operasi berdasarkan rentang tanggal dan dokter operator.</p>
        
        <div class="form-grid" style="margin-top:12px; margin-bottom:16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; align-items:flex-end;">
          <div class="field" style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-weight:600; font-size:12px; color:var(--slate-700);">Tanggal Mulai</label>
            <input type="date" id="rekapTglMulai" value="${f.tanggal}" style="padding:6px 10px; border:1px solid var(--slate-300); border-radius:6px; font-size:13.5px; font-weight:600;">
          </div>
          <div class="field" style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-weight:600; font-size:12px; color:var(--slate-700);">Tanggal Selesai</label>
            <input type="date" id="rekapTglSelesai" value="${f.tanggal}" style="padding:6px 10px; border:1px solid var(--slate-300); border-radius:6px; font-size:13.5px; font-weight:600;">
          </div>
          <div class="field" style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-weight:600; font-size:12px; color:var(--slate-700);">Dokter Operator</label>
            <select id="rekapDokter" style="padding:6px 10px; border:1px solid var(--slate-300); border-radius:6px; font-size:13.5px; font-weight:600; background-color:var(--white);">
              <option value="">-- Semua Dokter --</option>
              ${doctors.map(d => `<option value="${esc(d.nama)}">${esc(d.nama_gelar || d.nama)}</option>`).join("")}
            </select>
          </div>
          <div class="field">
            <button type="button" class="btn btn-primary" onclick="generateRecapReport()" style="width:100%; height:38px; display:inline-flex; align-items:center; justify-content:center; font-weight:700;">Tampilkan Rekap Laporan</button>
          </div>
        </div>
        
        <div id="rekapResultContainer"></div>
      </div>
    `;
  }

  // Tab 3: RESOURCE MONITOR
  function buildResourceView(allScheduled) {
    const f = state.monitorFilter;
    const forDate = allScheduled.filter(c => c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.tanggal === f.tanggal);
    
    const alatMap = {};
    forDate.forEach(c => {
      (c.adminCot && c.adminCot.alat || []).forEach(a => {
        if (!a) return;
        if (!alatMap[a]) alatMap[a] = [];
        alatMap[a].push(c);
      });
    });

    const isAdminCotView = (state.role === 'AdminCOT');
    const resourceMaster = loadResourceMaster();
    const normNama = s => String(s || "").toLowerCase().trim();

    const resourceRows = resourceMaster.map((r, i) => {
      const usingCases = forDate.filter(c => (c.adminCot && c.adminCot.alat || []).some(a => normNama(a) === normNama(r.nama)));
      const inUse = usingCases.filter(c => deriveOpStatus(c) === "Berlangsung").length;
      const reserved = usingCases.filter(c => ["Belum Mulai", "Ditunda", "Reschedule"].includes(deriveOpStatus(c))).length;
      const maint = Number(r.maintenance) || 0;
      const available = Math.max(0, (Number(r.total) || 0) - inUse - reserved - maint);
      
      if (isAdminCotView) {
        return `
          <tr data-i="${i}">
            <td><input class="form-control resNama" data-i="${i}" value="${esc(r.nama)}" style="width:140px; font-size:12px; padding:4px 8px;"></td>
            <td><input class="form-control resKeterangan" data-i="${i}" value="${esc(r.keterangan || "")}" style="width:220px; font-size:12px; padding:4px 8px;" placeholder="Keterangan"></td>
            <td><input class="form-control resTotal" data-i="${i}" type="number" min="0" value="${r.total}" style="width:60px; text-align:center; font-size:12px; padding:4px 8px;"></td>
            <td><input class="form-control resHarga" data-i="${i}" type="number" min="0" value="${Number(r.harga) || 0}" style="width:120px; text-align:right; font-size:12px; padding:4px 8px;"></td>
            <td><span class="badge-status st-op-hijau">${available} Available</span></td>
            <td><span class="badge-status st-op-biru">${inUse} In Use</span></td>
            <td><span class="badge-status st-op-kuning">${reserved} Reserved</span></td>
            <td><input class="form-control resMaint" data-i="${i}" type="number" min="0" value="${maint}" style="width:60px; text-align:center; display:inline-block; font-size:12px; padding:4px 8px;"> Maint</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="deleteResource(${i})" style="padding:2px 6px; font-size:10px;">Hapus</button></td>
          </tr>
        `;
      }

      return `
        <tr>
          <td>${esc(r.nama)}</td>
          <td class="footer-hint">${esc(r.keterangan || "-")}</td>
          <td>${r.total}</td>
          <td>${rupiah(Number(r.harga) || 0)}</td>
          <td><span class="badge-status st-op-hijau">${available} Available</span></td>
          <td><span class="badge-status st-op-biru">${inUse} In Use</span></td>
          <td><span class="badge-status st-op-kuning">${reserved} Reserved</span></td>
          <td><span class="badge-status st-Cancelled">${maint} Maintenance</span></td>
        </tr>
      `;
    }).join("");

    let formattedDate = '';
    try {
      formattedDate = new Date(f.tanggal + "T00:00:00").toLocaleDateString("id-ID", { weekday: "long", day: "2-digit", month: "long", year: "numeric" });
    } catch(err) {
      formattedDate = f.tanggal;
    }

    return `
      <div class="card">
        <h3>🔧 Resource Monitor — ${esc(formattedDate)}</h3>
        <div style="overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
              <tr>
                <th>Alat</th>
                <th>Keterangan</th>
                <th>Total</th>
                <th>Harga</th>
                <th>Available</th>
                <th>In Use</th>
                <th>Reserved</th>
                <th>Maintenance</th>
                ${isAdminCotView ? '<th></th>' : ''}
              </tr>
            </thead>
            <tbody>
              ${resourceRows || '<tr><td colspan="8" class="footer-hint" style="text-align:center;">Belum ada resource data.</td></tr>'}
            </tbody>
          </table>
        </div>
        
        ${isAdminCotView ? `
          <div class="btn-row" style="margin-top:14px; display:flex; gap:8px;">
            <button type="button" class="btn btn-sm" id="resourceAddBtn">+ Tambah Alat</button>
            <button type="button" class="btn btn-sm btn-primary" id="resourceSaveBtn">Simpan Perubahan</button>
          </div>
        ` : ''}

        ${Object.keys(alatMap).length ? `
          <div class="section-lbl" style="margin-top:20px;">Detail Penggunaan Alat Hari Ini:</div>
          <div style="margin-top:8px;">
            ${Object.keys(alatMap).map(nama => `
              <div style="margin-bottom:8px; border-bottom:1px dashed var(--slate-200); padding-bottom:6px;">
                <strong>${esc(nama)}</strong>:
                <div class="footer-hint" style="font-size:11.5px; margin-top:2px;">
                  ${alatMap[nama].map(c => `${esc(c.nama)} (${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.ruang || "-")} ${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.jam || "")})`).join(" · ")}
                </div>
              </div>
            `).join("")}
          </div>
        ` : ''}
      </div>
    `;
  }

  // Tab 4: SLOT CONFIGURATION VIEW
  function buildKonfigurasiView() {
    const isAdminCotView = (state.role === 'AdminCOT');
    const allConfigs = loadSlotConfig().slice().sort((a, b) => (a.tanggalMulai + a.ruang + a.jamMulai).localeCompare(b.tanggalMulai + b.ruang + b.jamMulai));
    const statusBadgeClass = s => s === "Tidak Digunakan" ? "st-Cancelled" : s === "Standby / Buffer" ? "st-Menunggu" : "st-Disetujui";

    const cfgRows = allConfigs.length
      ? allConfigs.map(cfg => `
        <tr>
          <td><span class="chip">${esc(cfg.ruang)}</span></td>
          <td>${esc(cfg.tanggalMulai)}${cfg.tanggalSelesai && cfg.tanggalSelesai !== cfg.tanggalMulai ? ` – ${esc(cfg.tanggalSelesai)}` : ""}</td>
          <td>${esc(cfg.jamMulai)}–${esc(cfg.jamSelesai)}</td>
          <td>${cfg.alat && cfg.alat.length ? esc(cfg.alat.join(", ")) : `<span class="footer-hint">Tidak ada alat dialokasikan</span>`}</td>
          <td><span class="badge-status ${statusBadgeClass(cfg.status)}" style="font-size:10px;">${esc(cfg.status)}</span></td>
          <td>${esc(cfg.keterangan || "-")}</td>
          ${isAdminCotView ? `<td><button type="button" class="btn btn-sm btn-danger" onclick="deleteSlotConfig('${esc(cfg.id)}')" style="padding:2px 6px; font-size:10px;">Hapus</button></td>` : ""}
        </tr>
      `).join("")
      : `<tr><td colspan="${isAdminCotView ? 7 : 6}" class="footer-hint" style="text-align:center; padding:15px;">Belum ada Konfigurasi Alokasi Slot Operasi.</td></tr>`;

    return `
      <div class="card">
        <h3>🗂️ Konfigurasi Alokasi Slot Operasi</h3>
        <div class="permission-note"><strong>Ini BUKAN jadwal pasien.</strong> Ini acuan sistem saat Admin COT menjadwalkan operasi.</div>
        
        ${isAdminCotView ? `
          <div class="section-lbl" style="margin-top:16px; font-weight:700;">+ Tambah Konfigurasi Alokasi Baru</div>
          <div class="form-grid" style="margin-top:8px;">
            <div class="field full">
              <label>Ruang Operasi</label>
              <div id="cfgRuangContainer" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px;">
                <span class="chip" style="background:#E2E8F0; color:#334155; font-weight:700;">OT 1</span>
              </div>
              <button type="button" class="btn btn-sm" id="cfgAddRuangBtn">+ Tambah Ruang</button>
            </div>
            
            <div class="field"><label>Tanggal Mulai</label><input type="date" id="cfgTanggalMulai" class="form-control"></div>
            <div class="field"><label>Tanggal Selesai <span class="hint">(opsional)</span></label><input type="date" id="cfgTanggalSelesai" class="form-control"></div>
            <div class="field"><label>Jam Mulai</label><input type="time" id="cfgJamMulai" value="07:00" class="form-control"></div>
            <div class="field"><label>Jam Selesai</label><input type="time" id="cfgJamSelesai" value="21:00" class="form-control"></div>
            <div class="field"><label>Status Slot</label>
              <select id="cfgStatus" class="form-control">
                ${SLOT_CONFIG_STATUS.map(s => `<option value="${s}">${s}</option>`).join("")}
              </select>
            </div>
            
            <div class="field full">
              <label>Alat Khusus Dialokasikan</label>
              <div id="cfgAlatContainer" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px;"></div>
              <button type="button" class="btn btn-sm" id="cfgAddAlatBtn">+ Tambah Alat</button>
            </div>
            
            <div class="field full">
              <label>Keterangan</label>
              <input type="text" id="cfgKeterangan" placeholder="mis. Alokasi alat bedah saraf" class="form-control">
            </div>
          </div>
          <div class="btn-row" style="margin-top:12px;">
            <button type="button" class="btn btn-primary" id="cfgSaveBtn">+ Simpan Konfigurasi</button>
          </div>
        ` : ''}

        <div class="section-lbl" style="margin-top:20px; font-weight:700;">Daftar Konfigurasi Alokasi Aktif</div>
        <div style="overflow-x:auto; margin-top:8px;">
          <table style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Ruang</th>
                <th>Periode Tanggal</th>
                <th>Slot Waktu</th>
                <th>Alat Dialokasikan</th>
                <th>Status</th>
                <th>Keterangan</th>
                ${isAdminCotView ? '<th>Aksi</th>' : ''}
              </tr>
            </thead>
            <tbody>
              ${cfgRows}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  // --- Dynamic modal popup detailing a scheduled case ---
  window.openOpDetailModal = function(caseId) {
    const c = cases.find(x => x.id === caseId);
    if (!c) return;

    const [sMs, eMs] = slotRange(c);
    const st = deriveOpStatus(c);
    const alatList = (c.adminCot && c.adminCot.alat || []).map(a => typeof a === 'string' ? a : a.nama).filter(Boolean);
    const root = document.getElementById("modalRoot");
    const isAdminCot = (state.role === "AdminCOT");

    root.innerHTML = `
      <div class="modal-overlay" id="opDetailOverlay" onclick="if(event.target === this) this.parentNode.innerHTML=''">
        <div class="modal" style="max-width:540px;">
          <h3>${esc(c.nama)} <span class="badge-status ${OP_STATUS_COLOR[st] || "st-Menunggu"}" style="font-size:11px; margin-left:8px;">${esc(st)}</span></h3>
          <table style="width:100%; margin-top:12px; border-collapse:collapse;">
            <tr><td class="footer-hint" style="width:150px; font-weight:700;">No. RM</td><td>${esc(c.rm)}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Dokter Operator</td><td>${listOrDash(c.operatorList)}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Dokter Anestesi</td><td>${esc(c.dokterAnestesi) || "-"}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Diagnosis</td><td>${esc(c.diagnosis) || "-"}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Tindakan</td><td>${listOrDash(c.tindakanList)}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Golongan Operasi</td><td>${esc(c.golongan) || "-"}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Tanggal</td><td>${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.tanggal || "-")}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Jam Mulai</td><td>${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.jam || "-")}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Estimasi Operasi</td><td>${getEffectiveDurasiJam(c)} jam</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Turn Over Time</td><td>${getTOT()} menit</td></tr>
            <tr><td class="footer-hint" style="font-weight:700; color:var(--primary-700);">Selesai Timeline</td><td><strong>${fmtHHMM(eMs)}</strong> (${fmtHHMM(sMs)}–${fmtHHMM(eMs)} incl. TOT)</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Kamar Operasi</td><td><span class="chip">${esc(c.adminCot && c.adminCot.jadwal && c.adminCot.jadwal.ruang) || "-"}</span></td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Penjamin</td><td>${esc(c.penjamin)}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Alat Khusus</td><td>${alatList.length ? alatList.map(a => `<span class="chip">${esc(a)}</span>`).join(" ") : "-"}</td></tr>
            <tr><td class="footer-hint" style="font-weight:700;">Catatan Adm</td><td>${esc(c.adminCot && c.adminCot.decisionNote) || "-"}</td></tr>
          </table>
          
          <div class="btn-row" style="margin-top:16px; display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-sm" onclick="document.getElementById('modalRoot').innerHTML=''">Tutup</button>
            <button type="button" class="btn btn-sm btn-revisi" onclick="window.location.href='/cases/${c.id}'">✏️ Detail &amp; Reschedule</button>
            ${isAdminCot && c.adminCot && !c.adminCot.tindakanSelesai && c.status !== 'Cancelled' ? `<button type="button" class="btn btn-sm btn-primary" onclick="markTindakanSelesai('${c.id}')">✅ Selesai</button>` : ''}
          </div>
        </div>
      </div>
    `;
  };

  // Drag-and-drop wiring
  function wireDragAndDrop() {
    if (state.role !== 'AdminCOT') return;

    document.querySelectorAll(".op-block.op-draggable").forEach(el => {
      el.addEventListener('dragstart', ev => {
        ev.dataTransfer.effectAllowed = "move";
        ev.dataTransfer.setData("text/plain", el.dataset.id);
        el.classList.add("op-dragging");
      });
      el.addEventListener('dragend', () => el.classList.remove("op-dragging"));
    });

    document.querySelectorAll(".gantt-cell[data-room]").forEach(td => {
      td.addEventListener('dragover', ev => {
        ev.preventDefault();
        ev.dataTransfer.dropEffect = "move";
        td.classList.add("drag-over");
      });
      td.addEventListener('dragleave', () => td.classList.remove("drag-over"));
      td.addEventListener('drop', ev => {
        ev.preventDefault();
        td.classList.remove("drag-over");
        const caseId = ev.dataTransfer.getData("text/plain");
        if (caseId) {
          performDragReschedule(caseId, td.dataset.room, td.dataset.jam);
        }
      });
    });
  }

  // --- Resource Monitor Inline editing handlers ---
  let localResourceIdx = 0;
  function wireResourceEditor() {
    if (state.role !== 'AdminCOT') return;

    const addBtn = document.getElementById("resourceAddBtn");
    const saveBtn = document.getElementById("resourceSaveBtn");

    if (addBtn) {
      addBtn.onclick = () => {
        resourceMasterData.push({ nama: "Alat Baru", keterangan: "KSM Pengguna", total: 1, harga: 0, maintenance: 0 });
        renderMonitoringPage();
      };
    }

    if (saveBtn) {
      saveBtn.onclick = () => {
        // Collect updated values from form fields
        document.querySelectorAll(".resNama").forEach(input => {
          const idx = Number(input.dataset.i);
          resourceMasterData[idx].nama = input.value;
        });
        document.querySelectorAll(".resKeterangan").forEach(input => {
          const idx = Number(input.dataset.i);
          resourceMasterData[idx].keterangan = input.value;
        });
        document.querySelectorAll(".resTotal").forEach(input => {
          const idx = Number(input.dataset.i);
          resourceMasterData[idx].total = Number(input.value) || 0;
        });
        document.querySelectorAll(".resHarga").forEach(input => {
          const idx = Number(input.dataset.i);
          resourceMasterData[idx].harga = Number(input.value) || 0;
        });
        document.querySelectorAll(".resMaint").forEach(input => {
          const idx = Number(input.dataset.i);
          resourceMasterData[idx].maintenance = Number(input.value) || 0;
        });

        saveSettingsToServer();
      };
    }
  }

  window.deleteResource = function(idx) {
    if (!confirm('Hapus resource data ini?')) return;
    resourceMasterData.splice(idx, 1);
    saveSettingsToServer();
    renderMonitoringPage();
  };

  // --- Slot Configuration Editor ---
  let selectedCfgRuang = ["OT 1"];
  let selectedCfgAlat = [];

  function wireConfigEditor() {
    if (state.role !== 'AdminCOT') return;

    const addRuangBtn = document.getElementById("cfgAddRuangBtn");
    const addAlatBtn = document.getElementById("cfgAddAlatBtn");
    const saveBtn = document.getElementById("cfgSaveBtn");

    if (addRuangBtn) {
      addRuangBtn.onclick = () => {
        const room = prompt(`Masukkan nama ruang operasi (pilih dari: ${OK_ROOM_LIST.join(', ')}):`, "OT 2");
        if (room) {
          const cleanRoom = room.trim();
          const matchedRoom = OK_ROOM_LIST.find(r => r.toLowerCase() === cleanRoom.toLowerCase());
          if (matchedRoom) {
            if (!selectedCfgRuang.includes(matchedRoom)) {
              selectedCfgRuang.push(matchedRoom);
              renderCfgRuangChips();
            }
          } else {
            toast('Nama ruangan tidak terdaftar.', 'error');
          }
        }
      };
    }

    if (addAlatBtn) {
      addAlatBtn.onclick = () => {
        const tool = prompt("Masukkan nama alat khusus yang dialokasikan:");
        if (tool && tool.trim()) {
          const cleanTool = tool.trim();
          if (!selectedCfgAlat.includes(cleanTool)) {
            selectedCfgAlat.push(cleanTool);
            renderCfgAlatChips();
          }
        }
      };
    }

    if (saveBtn) {
      saveBtn.onclick = () => {
        const tglMulai = document.getElementById("cfgTanggalMulai").value;
        const tglSelesai = document.getElementById("cfgTanggalSelesai").value;
        const jamMulai = document.getElementById("cfgJamMulai").value;
        const jamSelesai = document.getElementById("cfgJamSelesai").value;
        const status = document.getElementById("cfgStatus").value;
        const keterangan = document.getElementById("cfgKeterangan").value;

        if (!tglMulai) {
          toast('Tanggal Mulai wajib diisi.', 'error');
          return;
        }

        selectedCfgRuang.forEach(ruang => {
          slotConfigsData.push({
            id: 'cfg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
            ruang: ruang,
            tanggalMulai: tglMulai,
            tanggalSelesai: tglSelesai || tglMulai,
            jamMulai: jamMulai || '07:00',
            jamSelesai: jamSelesai || '21:00',
            status: status,
            alat: selectedCfgAlat.slice(),
            keterangan: keterangan || ''
          });
        });

        // Reset inputs
        selectedCfgRuang = ["OT 1"];
        selectedCfgAlat = [];
        saveSettingsToServer();
        renderMonitoringPage();
      };
    }
  }

  function renderCfgRuangChips() {
    const container = document.getElementById("cfgRuangContainer");
    if (!container) return;
    container.innerHTML = selectedCfgRuang.map(r => `
      <span class="chip" style="background:#E2E8F0; color:#334155; font-weight:700; display:flex; align-items:center; gap:4px;">
        ${esc(r)}
        <span style="cursor:pointer; font-weight:bold; color:#ef4444;" onclick="removeCfgRuang('${esc(r)}')">&times;</span>
      </span>
    `).join("");
  }

  window.removeCfgRuang = function(room) {
    selectedCfgRuang = selectedCfgRuang.filter(r => r !== room);
    renderCfgRuangChips();
  };

  function renderCfgAlatChips() {
    const container = document.getElementById("cfgAlatContainer");
    if (!container) return;
    container.innerHTML = selectedCfgAlat.map(a => `
      <span class="chip" style="background:#E0F2FE; color:#0369A1; font-weight:600; display:flex; align-items:center; gap:4px;">
        ${esc(a)}
        <span style="cursor:pointer; font-weight:bold; color:#ef4444;" onclick="removeCfgAlat('${esc(a)}')">&times;</span>
      </span>
    `).join("");
  }

  window.removeCfgAlat = function(tool) {
    selectedCfgAlat = selectedCfgAlat.filter(a => a !== tool);
    renderCfgAlatChips();
  };

  window.deleteSlotConfig = function(cfgId) {
    if (!confirm('Hapus alokasi slot konfigurasi ini?')) return;
    slotConfigsData = slotConfigsData.filter(cfg => cfg.id !== cfgId);
    saveSettingsToServer();
    renderMonitoringPage();
  };

  // Wire Tab Buttons to change views
  const btnTabel = document.getElementById("viewTabelBtn");
  if (btnTabel) btnTabel.onclick = () => { state.monitorFilter.view = "tabel"; renderMonitoringPage(); };
  const btnTimeline = document.getElementById("viewTimelineBtn");
  if (btnTimeline) btnTimeline.onclick = () => { state.monitorFilter.view = "timeline"; renderMonitoringPage(); };
  const btnResource = document.getElementById("viewResourceBtn");
  if (btnResource) btnResource.onclick = () => { state.monitorFilter.view = "resource"; renderMonitoringPage(); };
  const btnKonfigurasi = document.getElementById("viewKonfigurasiBtn");
  if (btnKonfigurasi) btnKonfigurasi.onclick = () => { state.monitorFilter.view = "konfigurasi"; renderMonitoringPage(); };

  // Wire filters
  const fTanggal = document.getElementById("monTanggal");
  if (fTanggal) fTanggal.onchange = (e) => { state.monitorFilter.tanggal = e.target.value; renderMonitoringPage(); };
  const fRuang = document.getElementById("monRuang");
  if (fRuang) fRuang.onchange = (e) => { state.monitorFilter.ruang = e.target.value; renderMonitoringPage(); };
  const fStatus = document.getElementById("monStatus");
  if (fStatus) fStatus.onchange = (e) => { state.monitorFilter.opStatus = e.target.value; renderMonitoringPage(); };
  const fPenjamin = document.getElementById("monPenjamin");
  if (fPenjamin) fPenjamin.onchange = (e) => { state.monitorFilter.penjamin = e.target.value; renderMonitoringPage(); };
  const fSearch = document.getElementById("monSearch");
  if (fSearch) fSearch.oninput = (e) => { state.monitorFilter.search = e.target.value; renderMonitoringPage(); };

  const totSelect = document.getElementById("totSetting");
  if (totSelect) {
    totSelect.onchange = (e) => {
      totMinutesData = Number(e.target.value);
      toast(`Turn Over Time diatur ke ${e.target.value} menit — semua jadwal dihitung ulang`, 'success');
      saveSettingsToServer();
      renderMonitoringPage();
    };
  }

  // Initial render on load
  renderMonitoringPage();
</script>
@endsection
