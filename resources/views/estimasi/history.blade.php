@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — History Estimasi')
@section('page_title', 'History Estimasi')

@section('styles')
<style>
  .history-table th, .history-table td {
    padding: 10px;
    border-bottom: 1px solid var(--slate-200);
    text-align: left;
    font-size: 13px;
  }
  .history-table th {
    background: var(--slate-100);
    font-weight: 700;
  }
  .modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 1000;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
  }
  .modal-overlay.active {
    opacity: 1; pointer-events: auto;
  }
  .modal-card {
    background: var(--white); border-radius: 12px; width: 640px; max-width: 90%;
    padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    max-height: 90vh; overflow-y: auto;
  }
  @media print {
    body * { display: none !important; }
    #printArea, #printArea * { display: block !important; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
  }
</style>
@endsection

@section('content')
<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h3>🕘 History Estimasi</h3>
    @if($history->isNotEmpty())
      <button type="button" class="btn btn-danger" id="clearAllBtn" style="background: #FEE2E2; color: #991B1B; border-color: #FCA5A5;">
        🗑️ Hapus Semua Riwayat
      </button>
    @endif
  </div>

  @if($history->isEmpty())
    <div style="text-align: center; padding: 40px; color: var(--slate-400);">
      <span style="font-size: 40px;">📂</span>
      <p style="margin-top: 10px; font-weight: 600;">Belum ada riwayat estimasi mandiri yang disimpan.</p>
    </div>
  @else
    <div style="overflow-x:auto;">
      <table class="table history-table" style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Pasien</th>
            <th>Diagnosis & Tindakan</th>
            <th>Jaminan</th>
            <th>Golongan / Kelas</th>
            <th style="text-align: right;">Total Estimasi</th>
            <th style="text-align: center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($history as $item)
            <tr data-id="{{ $item->id }}">
              <td>
                <div style="font-weight:600;">{{ $item->created_at->format('d M Y') }}</div>
                <div style="font-size:10px; color:var(--slate-400);">{{ $item->created_at->format('H:i') }}</div>
              </td>
              <td>
                <div style="font-weight:700; color:var(--primary-800);">{{ $item->nama ?: 'Tanpa Nama' }}</div>
                <div style="font-size:11px; color:var(--slate-500);">RM: {{ $item->rm ?: '-' }}</div>
              </td>
              <td>
                <div style="font-weight: 600; font-size:12.5px;">{{ $item->tindakan ?: '-' }}</div>
                <div style="font-size:11px; color:var(--slate-500); font-style:italic;">Diag: {{ $item->rm ? 'Pencarian RM' : 'Ketik Bebas' }}</div>
              </td>
              <td>
                <span class="badge-status {{ $item->penjamin === 'Umum' ? 'st-Disetujui' : 'st-Pending' }}" style="font-size:10px;">
                  {{ $item->penjamin }}
                </span>
                @if($item->guarantor)
                  <div style="font-size: 11px; color: var(--slate-500); margin-top: 2px;">{{ $item->guarantor }}</div>
                @endif
              </td>
              <td>
                <strong>Gol: {{ $item->golongan ?: '-' }}</strong>
                <div style="font-size: 11px; color: var(--slate-500);">Kelas: {{ $item->kelas ?: '-' }}</div>
              </td>
              <td style="text-align: right; font-weight: 700; color: var(--primary-700); font-size: 14px;">
                Rp {{ number_format($item->total_estimasi, 0, ',', '.') }}
              </td>
              <td style="text-align: center; vertical-align: middle;">
                <div style="display: flex; gap: 6px; justify-content: center;">
                  <button type="button" class="btn btn-sm viewDetailsBtn" data-id="{{ $item->id }}" style="background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200);">
                    Detail
                  </button>
                  <button type="button" class="btn btn-sm btn-danger deleteBtn" data-id="{{ $item->id }}" style="padding: 5px 10px;">
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

{{-- DETAILS MODAL --}}
<div class="modal-overlay" id="detailsModal">
  <div class="modal-card">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--slate-200); padding-bottom:12px; margin-bottom:16px;">
      <h3 style="margin:0; color:var(--primary-900);">📋 Rincian Estimasi Biaya Mandiri</h3>
      <button type="button" id="closeModalBtn" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--slate-400); font-weight:700;">&times;</button>
    </div>
    
    <div id="modalContent">
      <!-- Injected by JS -->
    </div>

    <div style="margin-top:20px; border-top:1px solid var(--slate-100); padding-top:16px; display:flex; justify-content:flex-end; gap:8px;">
      <button type="button" class="btn" id="printModalBtn" style="background:var(--slate-100); color:var(--slate-700); border-color:var(--slate-300);">🖨️ Cetak PDF</button>
      <button type="button" class="btn btn-secondary" id="closeModalBtn2">Tutup</button>
    </div>
  </div>
</div>

{{-- AREA UNTUK CETAK --}}
<div id="printArea" style="display:none;"></div>
@endsection

@section('scripts')
<script>
  const historyData = @json($history);
  const modal = document.getElementById("detailsModal");
  const modalContent = document.getElementById("modalContent");
  
  // Open details helper
  document.querySelectorAll(".viewDetailsBtn").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.dataset.id;
      const item = historyData.find(x => String(x.id) === id);
      if (!item) return;

      let rincianRows = '';
      if (item.rincian && item.rincian.length) {
        item.rincian.forEach((r, idx) => {
          rincianRows += `
            <tr>
              <td style="border:1px solid var(--slate-200); padding:6px; font-size:12px;">${r.komponen}</td>
              <td style="border:1px solid var(--slate-200); padding:6px; text-align:right; font-size:12px;">Rp ${formatRupiah(r.nilai)}</td>
            </tr>`;
        });
      } else {
        rincianRows = `<tr><td colspan="2" style="border:1px solid var(--slate-200); padding:10px; text-align:center; color:var(--slate-400);">Belum ada rincian komponen.</td></tr>`;
      }

      modalContent.innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:12.5px; background:var(--slate-50); padding:12px; border-radius:6px; border:1px solid var(--slate-200); margin-bottom:16px;">
          <div><strong>RM:</strong> ${item.rm || '-'}</div>
          <div><strong>Nama Pasien:</strong> ${item.nama || '-'}</div>
          <div><strong>Penjamin:</strong> ${item.penjamin} ${item.guarantor ? '(' + item.guarantor + ')' : ''}</div>
          <div><strong>Golongan / Kelas:</strong> ${item.golongan || '-'} / ${item.kelas || '-'}</div>
          <div style="grid-column: span 2;"><strong>Diagnosis / Tindakan:</strong> ${item.tindakan || '-'}</div>
        </div>
        
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
          <thead>
            <tr style="background:var(--slate-100);">
              <th style="border:1px solid var(--slate-200); padding:6px; text-align:left; font-size:12px;">Komponen Biaya</th>
              <th style="border:1px solid var(--slate-200); padding:6px; text-align:right; font-size:12px; width:150px;">Nilai</th>
            </tr>
          </thead>
          <tbody>
            ${rincianRows}
            <tr style="font-weight:700; background:var(--primary-50); color:var(--primary-900);">
              <td style="border:1px solid var(--slate-200); padding:8px; font-size:13px;">GRAND TOTAL</td>
              <td style="border:1px solid var(--slate-200); padding:8px; text-align:right; font-size:13px; color:var(--primary-700);">Rp ${formatRupiah(item.total_estimasi)}</td>
            </tr>
          </tbody>
        </table>
      `;

      // Set print area content
      document.getElementById("printArea").innerHTML = `
        <div style="font-family:'Times New Roman',serif; font-size:13px; padding:24px;">
          <div style="font-weight:bold;">RUMAH SAKIT UNIVERSITAS INDONESIA</div>
          <div style="font-size:11px; margin-bottom:6px;">Central Operating Theatre (COT) - Perkiraan Mandiri</div>
          <hr style="border:0; border-top:1px solid #333; margin-bottom:15px;">
          <h2 style="text-align:center; font-size:16px; text-transform:uppercase; margin-bottom:15px;">Perkiraan Biaya Tindakan Mandiri</h2>
          
          <table style="width:100%; margin-bottom:15px; font-size:12px; line-height:1.4;">
            <tr><td style="width:150px;">Nama Pasien</td><td>: ${item.nama || '-'}</td></tr>
            <tr><td>No. Rekam Medis</td><td>: ${item.rm || '-'}</td></tr>
            <tr><td>Penjamin</td><td>: ${item.penjamin} ${item.guarantor ? ' — ' + item.guarantor : ''}</td></tr>
            <tr><td>Golongan / Kelas</td><td>: ${item.golongan || '-'} / ${item.kelas || '-'}</td></tr>
            <tr><td>Tindakan / Diagnosis</td><td>: ${item.tindakan || '-'}</td></tr>
          </table>

          <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
              <tr style="background:#eee;">
                <th style="border:1px solid #333; padding:5px 8px; text-align:left;">Komponen Biaya</th>
                <th style="border:1px solid #333; padding:5px 8px; text-align:right; width:150px;">Nilai (Rp)</th>
              </tr>
            </thead>
            <tbody>
              ${rincianRows.replaceAll('var(--slate-200)', '#333')}
              <tr style="font-weight:bold; background:#eee;">
                <td style="border:1px solid #333; padding:6px 8px; text-align:right;">TOTAL PERKIRAAN BIAYA</td>
                <td style="border:1px solid #333; padding:6px 8px; text-align:right;">${formatRupiah(item.total_estimasi)}</td>
              </tr>
            </tbody>
          </table>

          <div style="font-size:11px; margin-top:20px; font-style:italic;">
            *Catatan: Dokumen ini dicetak dari sistem Command Center COT (Perkiraan Mandiri) dan tidak mengikat secara administratif.
          </div>
        </div>
      `;

      modal.classList.add("active");
    });
  });

  // Close modal
  function closeModal() {
    modal.classList.remove("active");
  }
  document.getElementById("closeModalBtn").addEventListener("click", closeModal);
  document.getElementById("closeModalBtn2").addEventListener("click", closeModal);
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

  // Print helper
  document.getElementById("printModalBtn").addEventListener("click", () => {
    window.print();
  });

  // Format rupiah helper
  function formatRupiah(num) {
    return new Intl.NumberFormat("id-ID").format(num);
  }

  // Delete single history item
  document.querySelectorAll(".deleteBtn").forEach(btn => {
    btn.addEventListener("click", (e) => {
      if (!confirm("Apakah Anda yakin ingin menghapus riwayat estimasi ini?")) return;
      const id = btn.dataset.id;
      
      fetch(`/api/estimasi-history/${id}`, {
        method: "DELETE",
        headers: {
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast("Riwayat berhasil dihapus.", "success");
          btn.closest("tr").remove();
          if (document.querySelectorAll(".history-table tbody tr").length === 0) {
            setTimeout(() => location.reload(), 500);
          }
        }
      })
      .catch(err => showToast("Gagal menghapus riwayat.", "error"));
    });
  });

  // Clear all history
  const clearAllBtn = document.getElementById("clearAllBtn");
  if (clearAllBtn) {
    clearAllBtn.addEventListener("click", () => {
      if (!confirm("Apakah Anda yakin ingin menghapus SELURUH riwayat estimasi mandiri? Tindakan ini tidak dapat dibatalkan.")) return;
      
      fetch("/api/estimasi-history/clear", {
        method: "POST",
        headers: {
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast("Semua riwayat berhasil dikosongkan.", "success");
          setTimeout(() => location.reload(), 800);
        }
      })
      .catch(err => showToast("Gagal mengosongkan riwayat.", "error"));
    });
  }

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
