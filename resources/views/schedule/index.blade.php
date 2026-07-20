@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Jadwal Operasi')
@section('page_title', 'Jadwal Operasi')

@section('styles')
<style>
  .schedule-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 30px;
  }
  
  .grid-card {
    background: var(--white);
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
  }

  .grid-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
  }

  .grid-nav-controls {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .grid-nav-btn {
    background: var(--slate-100);
    border: 1px solid var(--slate-200);
    color: var(--slate-700);
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .grid-nav-btn:hover {
    background: var(--primary-50);
    border-color: var(--primary-300);
    color: var(--primary-700);
  }

  .grid-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-900);
  }

  .timetable-wrapper {
    overflow-x: auto;
    border: 1px solid var(--slate-200);
    border-radius: 8px;
  }

  .timetable-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px; /* Force minimum width to align columns nicely */
    background: var(--white);
  }

  .timetable-table th, .timetable-table td {
    border: 1px solid var(--slate-200);
    padding: 6px;
    text-align: center;
    vertical-align: top;
  }

  .timetable-table th {
    background: var(--slate-50);
    font-weight: 700;
    color: var(--slate-600);
    font-size: 12px;
    position: sticky;
    top: 0;
    z-index: 10;
  }

  .time-col {
    background: var(--slate-50);
    font-weight: 700;
    color: var(--slate-700);
    width: 80px;
    font-size: 12.5px;
    vertical-align: middle !important;
  }

  .grid-schedule-card {
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    color: #1E40AF;
    padding: 8px;
    border-radius: 6px;
    font-size: 11px;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
    margin-bottom: 6px;
    line-height: 1.3;
    display: block;
    word-break: break-word;
  }

  .grid-schedule-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    background: #DBEAFE;
  }

  .grid-schedule-card.active-card {
    background: var(--primary-600) !important;
    border-color: var(--primary-700) !important;
    color: var(--white) !important;
  }

  .grid-schedule-card.active-card div {
    color: rgba(255, 255, 255, 0.9) !important;
  }

  .detail-panel {
    background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(248,250,252,0.95));
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    transition: opacity 0.3s ease, transform 0.3s ease;
  }

  .detail-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--slate-100);
    padding-bottom: 12px;
    margin-bottom: 16px;
  }

  .detail-panel-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary-800);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .detail-label {
    font-size: 11px;
    color: var(--slate-400);
    font-weight: 700;
    text-transform: uppercase;
  }

  .detail-val {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--slate-800);
  }

  .status-badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 9px;
    font-weight: 700;
    border-radius: 4px;
    margin-top: 4px;
  }

  .status-badge.terjadwal {
    background: #DCFCE7;
    color: #15803D;
  }

  .status-badge.pengajuan {
    background: #EFF6FF;
    color: #1D4ED8;
  }

  .grid-schedule-card.active-card .status-badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: var(--white) !important;
  }
</style>
@endsection

@section('content')
<div class="schedule-container">
  
  <!-- GRID CARD (Timetable) -->
  <div class="grid-card">
    <div class="grid-header">
      <div class="grid-title">Jadwal Harian Operasi</div>
      <div class="grid-nav-controls">
        <button class="grid-nav-btn" id="prevDayBtn">&laquo; Hari Sebelumnya</button>
        <input type="date" id="scheduleDateSelect" class="form-control" style="width:160px; display:inline-block; font-weight:600; padding:6px 10px;">
        <button class="grid-nav-btn" id="nextDayBtn">Hari Berikutnya &raquo;</button>
      </div>
    </div>
    
    <div class="timetable-wrapper">
      <table class="timetable-table">
        <thead>
          <tr>
            <th style="width: 80px;">Jam</th>
            <th>1</th>
            <th>2</th>
            <th>3</th>
            <th>4</th>
            <th>5</th>
            <th>6</th>
            <th>HYBRID</th>
            <th>COT LT 5</th>
            <th>IGD</th>
            <th>CATHLAB</th>
            <th>ICU</th>
          </tr>
        </thead>
        <tbody id="timetableBody">
          <!-- Timetable rows will be dynamically populated by JavaScript -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- DETAILS PANEL -->
  <div class="detail-panel" id="detailPanel">
    <div class="detail-panel-header">
      <div class="detail-panel-title">
        <span>📅</span> Detail Keterangan Jadwal
      </div>
      <div id="detailCaseId" style="font-size:12px; color:var(--slate-500); font-weight:700;"></div>
    </div>
    
    <div id="detailContentPlaceholder" style="text-align:center; padding:30px; color:var(--slate-400); font-weight:600; font-size:13.5px;">
      Pilih salah satu jadwal operasi pada grid harian di atas untuk melihat rincian kasus di sini.
    </div>

    <div id="detailContent" style="display:none;">
      <div class="detail-grid">
        <div class="detail-item">
          <span class="detail-label">Nama Pasien</span>
          <span class="detail-val" id="detPasien"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">No. Rekam Medis (RM)</span>
          <span class="detail-val" id="detRm"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Tanggal / Waktu</span>
          <span class="detail-val" id="detWaktu"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Ruang / Kamar</span>
          <span class="detail-val" id="detRuang"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Tindakan</span>
          <span class="detail-val" id="detTindakan"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Operator</span>
          <span class="detail-val" id="detOperator"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Golongan / Kelas</span>
          <span class="detail-val" id="detGolonganKelas"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Jaminan / Administrasi</span>
          <span class="detail-val" id="detJaminan"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Estimasi Rawat Inap</span>
          <span class="detail-val" id="detRawat"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Estimasi Lama Operasi</span>
          <span class="detail-val" id="detLama"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Anestesi</span>
          <span class="detail-val" id="detAnestesi"></span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Estimasi Total Biaya</span>
          <span class="detail-val" id="detBiaya" style="color:var(--primary-700); font-weight:700;"></span>
        </div>
      </div>
      <div style="margin-top:16px; border-top:1px solid var(--slate-100); padding-top:12px;">
        <span class="detail-label">Diagnosis</span>
        <p style="margin:4px 0 0 0; font-size:13px; color:var(--slate-700); line-height:1.5;" id="detDiagnosis"></p>
      </div>
      <div style="margin-top:12px; display:flex; justify-content:flex-end;">
        <a id="detDetailLink" href="#" class="btn btn-sm" style="text-decoration:none;">Buka Halaman Detail Case ➜</a>
      </div>
    </div>
  </div>

</div>
@endsection

@section('scripts')
<script>
  // Schedules injected from backend controller
  const schedules = @json($schedules);

  // Time slots matching requests
  const timeSlots = [
    "07:00", "07:30", "08:00", "08:30", "09:00", "09:30", "10:00", "10:30",
    "11:00", "11:30", "12:00", "12:30", "13:00", "13:30", "14:00", "14:30",
    "15:00", "15:30", "16:00", "16:30", "17:00", "17:30", "18:00", "18:30",
    "19:00", "19:30", "20:00", "20:30", "21:00", "21:30", "22:00", "22:30",
    "23:00", "23:30", "00:00"
  ];

  // Room columns
  const rooms = ["1", "2", "3", "4", "5", "6", "HYBRID", "COT LT 5", "IGD", "CATHLAB", "ICU"];

  // Initialize selected date to today or first schedule date if available
  let selectedDateStr = new Date().toISOString().split('T')[0];
  if (schedules.length > 0 && schedules[0].tanggal_raw) {
    selectedDateStr = schedules[0].tanggal_raw;
  }

  // Parse a time string to nearest 30 min slot
  function getNearestTimeSlot(timeStr) {
    if (!timeStr || timeStr === '-') return null;
    const parts = timeStr.split(':');
    if (parts.length < 2) return null;
    let hour = parseInt(parts[0]);
    let min = parseInt(parts[1]);
    
    if (min < 15) {
      min = 0;
    } else if (min >= 15 && min < 45) {
      min = 30;
    } else {
      min = 0;
      hour = (hour + 1) % 24;
    }
    
    return `${String(hour).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
  }

  function renderTimetable() {
    const tbody = document.getElementById("timetableBody");
    tbody.innerHTML = "";

    // Filter schedules for the selected date
    const dateSchedules = schedules.filter(s => s.tanggal_raw === selectedDateStr);

    timeSlots.forEach(slot => {
      const row = document.createElement("tr");
      
      // Time Slot Header Column
      const timeTd = document.createElement("td");
      timeTd.className = "time-col";
      timeTd.textContent = slot.replace(":", ".");
      row.appendChild(timeTd);

      // Create cells for each room
      rooms.forEach(room => {
        const td = document.createElement("td");
        
        // Find matching schedules in this slot & room
        const cellSchedules = dateSchedules.filter(s => {
          const sSlot = getNearestTimeSlot(s.jam);
          return sSlot === slot && String(s.mapped_room) === room;
        });

        cellSchedules.forEach(s => {
          const card = document.createElement("div");
          card.className = "grid-schedule-card";
          card.dataset.id = s.id;
          
          card.innerHTML = `
            <div style="font-weight:700;">${s.pasien_nama}</div>
            <div style="font-size:10px; color:var(--slate-500); margin-top:2px;">RM: ${s.pasien_rm}</div>
            <div style="font-size:10px; font-style:italic; color:var(--slate-600); margin-top:2px;">Op: ${s.operator}</div>
            <span class="status-badge ${s.status_tindakan === 'Terjadwal' ? 'terjadwal' : 'pengajuan'}">${s.status_tindakan}</span>
          `;

          card.addEventListener("click", (e) => {
            e.stopPropagation();
            document.querySelectorAll(".grid-schedule-card").forEach(c => c.classList.remove("active-card"));
            card.classList.add("active-card");
            showDetails(s);
          });

          td.appendChild(card);
        });

        row.appendChild(td);
      });

      tbody.appendChild(row);
    });
  }

  function showDetails(s) {
    document.getElementById("detailContentPlaceholder").style.display = "none";
    document.getElementById("detailContent").style.display = "block";

    document.getElementById("detailCaseId").textContent = s.id;
    document.getElementById("detPasien").textContent = s.pasien_nama;
    document.getElementById("detRm").textContent = s.pasien_rm;
    document.getElementById("detWaktu").textContent = `${s.tanggal_formatted} • ${s.jam}`;
    document.getElementById("detRuang").textContent = s.ruang;
    document.getElementById("detTindakan").textContent = s.tindakan;
    document.getElementById("detOperator").textContent = s.operator;
    document.getElementById("detGolonganKelas").textContent = s.golongan_kelas;
    document.getElementById("detJaminan").textContent = s.status_administrasi;
    document.getElementById("detRawat").textContent = s.details.estimasi_rawat;
    document.getElementById("detLama").textContent = s.details.estimasi_lama;
    document.getElementById("detAnestesi").textContent = s.details.anestesi;
    document.getElementById("detBiaya").textContent = s.details.estimasi_biaya;
    document.getElementById("detDiagnosis").textContent = s.details.diagnosis;
    
    document.getElementById("detDetailLink").href = `/cases/${s.id}`;
    
    // Smooth scroll detail panel
    document.getElementById("detailPanel").scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function showEmptyDetails() {
    document.getElementById("detailContent").style.display = "none";
    document.getElementById("detailContentPlaceholder").style.display = "block";
  }

  // Date selectors setup
  const dateInput = document.getElementById("scheduleDateSelect");
  dateInput.value = selectedDateStr;

  dateInput.addEventListener("change", function() {
    selectedDateStr = this.value;
    showEmptyDetails();
    renderTimetable();
  });

  document.getElementById("prevDayBtn").addEventListener("click", () => {
    const d = new Date(selectedDateStr);
    d.setDate(d.getDate() - 1);
    selectedDateStr = d.toISOString().split('T')[0];
    dateInput.value = selectedDateStr;
    showEmptyDetails();
    renderTimetable();
  });

  document.getElementById("nextDayBtn").addEventListener("click", () => {
    const d = new Date(selectedDateStr);
    d.setDate(d.getDate() + 1);
    selectedDateStr = d.toISOString().split('T')[0];
    dateInput.value = selectedDateStr;
    showEmptyDetails();
    renderTimetable();
  });

  // Initial load
  document.addEventListener("DOMContentLoaded", () => {
    renderTimetable();
    
    // Auto-select first schedule for this day if exists
    const daySchedules = schedules.filter(s => s.tanggal_raw === selectedDateStr);
    if (daySchedules.length > 0) {
      showDetails(daySchedules[0]);
      setTimeout(() => {
        const card = document.querySelector(`.grid-schedule-card[data-id="${daySchedules[0].id}"]`);
        if (card) card.classList.add("active-card");
      }, 100);
    }
  });
</script>
@endsection
