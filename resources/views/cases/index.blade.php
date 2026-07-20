@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Daftar Kasus')
@section('page_title', request()->query('queue') === 'mine' ? 'Antrian Saya' : 'Semua Case')

@section('content')
@php
  $currentUser = Auth::user();
  $activeRole = session('role', $currentUser ? $currentUser->role : 'Viewer');
  $isMine = request()->query('queue') === 'mine';
@endphp

<style>
  .case-card {
    background: var(--white);
    border: 1px solid var(--slate-200);
    border-left: 5px solid var(--slate-300);
    border-radius: 10px;
    padding: 0;
    margin-bottom: 18px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
  }
  .case-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.10);
  }
  .case-card.status-Draft         { border-left-color: var(--slate-400); }
  .case-card.status-Submitted     { border-left-color: #3B82F6; }
  .case-card.status-InProgress    { border-left-color: #F59E0B; }
  .case-card.status-Returned      { border-left-color: #EF4444; }
  .case-card.status-Completed     { border-left-color: #10B981; }
  .case-card.status-Cancelled     { border-left-color: #374151; }

  .case-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 12px 16px 8px;
    border-bottom: 1px solid var(--slate-100);
    gap: 8px;
  }
  .case-card-header .case-id {
    font-size: 13px;
    font-weight: 800;
    color: var(--primary-700);
    letter-spacing: 0.5px;
  }
  .case-card-header .case-date {
    font-size: 11px;
    color: var(--slate-400);
    margin-top: 2px;
  }
  .case-card-body {
    padding: 10px 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 12px;
  }
  @media (max-width: 640px) {
    .case-card-body { grid-template-columns: 1fr; }
  }
  .case-info-row {
    display: flex;
    flex-direction: column;
    font-size: 12px;
  }
  .case-info-row .lbl {
    color: var(--slate-400);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .case-info-row .val {
    color: var(--slate-700);
    font-weight: 600;
    font-size: 12.5px;
    margin-top: 1px;
  }
  .case-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 16px;
    background: var(--slate-50);
    border-top: 1px solid var(--slate-100);
    flex-wrap: wrap;
    gap: 6px;
  }
  .case-card-footer .actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
  }
  .btn-action {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .btn-action:hover { opacity: 0.85; transform: scale(1.02); }
  .btn-action.view   { background: var(--primary-600); color: #fff; }
  .btn-action.edit   { background: #F59E0B; color: #fff; }
  .btn-action.submit { background: #10B981; color: #fff; }
  .btn-action.cancel { background: #EF4444; color: #fff; }
  .btn-action.confirm { background: #6366F1; color: #fff; }

  /* Filter nav tabs */
  .filter-nav-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }
  .filter-tab {
    padding: 5px 14px;
    border-radius: 20px;
    border: 1px solid var(--slate-200);
    background: var(--white);
    color: var(--slate-500);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
  }
  .filter-tab:hover, .filter-tab.active {
    background: var(--primary-600);
    color: #fff;
    border-color: var(--primary-600);
    box-shadow: 0 4px 10px rgba(99,102,241,0.25);
    text-decoration: none;
  }
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
    align-items: start;
  }
  .flow-chip {
    display: inline-block;
    background: #EDE9FE;
    color: #5B21B6;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
  }
  .empty-cards {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--slate-400);
    font-size: 14px;
  }
  .empty-cards .icon {
    font-size: 48px;
    display: block;
    margin-bottom: 12px;
    opacity: 0.4;
  }
</style>

<div class="card">
  <!-- Top toolbar -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
    <div>
      <h3 style="margin:0 0 4px 0;">{{ $isMine ? 'Kasus Yang Perlu Anda Tindaklanjuti' : 'Daftar Semua Kasus Operasi' }}</h3>
      <div style="font-size:12px; color:var(--slate-400);">
        {{ $isMine ? 'Hanya menampilkan kasus yang membutuhkan aksi dari role Anda saat ini.' : 'Menampilkan semua kasus aktif yang terdaftar di sistem.' }}
      </div>
    </div>
    <form method="GET" action="{{ url()->current() }}" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      @if($isMine)
        <input type="hidden" name="queue" value="mine">
      @endif
      @if(request()->filled('status'))
        <input type="hidden" name="status" value="{{ request()->query('status') }}">
      @endif

      <!-- Penjamin Filter -->
      <select name="penjamin" onchange="this.form.submit()" style="padding:6px 10px; border-radius:6px; border:1px solid var(--slate-200); font-size:13px; background:var(--white);">
        <option value="All" {{ request()->query('penjamin', 'All') === 'All' ? 'selected' : '' }}>Semua Penjamin</option>
        <option value="Umum" {{ request()->query('penjamin') === 'Umum' ? 'selected' : '' }}>Umum</option>
        <option value="BPJS Kesehatan" {{ request()->query('penjamin') === 'BPJS Kesehatan' ? 'selected' : '' }}>BPJS Kesehatan</option>
        <option value="Asuransi" {{ request()->query('penjamin') === 'Asuransi' ? 'selected' : '' }}>Asuransi Swasta / Lainnya</option>
      </select>

      <!-- Lokasi Filter -->
      <select name="lokasi" onchange="this.form.submit()" style="padding:6px 10px; border-radius:6px; border:1px solid var(--slate-200); font-size:13px; background:var(--white);">
        <option value="All" {{ request()->query('lokasi', 'All') === 'All' ? 'selected' : '' }}>Semua Lokasi</option>
        <option value="COT" {{ request()->query('lokasi') === 'COT' ? 'selected' : '' }}>COT</option>
        <option value="OT IGD" {{ request()->query('lokasi') === 'OT IGD' ? 'selected' : '' }}>OT IGD</option>
        <option value="Cathlab" {{ request()->query('lokasi') === 'Cathlab' ? 'selected' : '' }}>Cathlab</option>
        <option value="Endoskopi" {{ request()->query('lokasi') === 'Endoskopi' ? 'selected' : '' }}>Endoskopi</option>
        <option value="Lainnya" {{ request()->query('lokasi') === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
      </select>

      <!-- Modul Aktif Filter -->
      <select name="flow" onchange="this.form.submit()" style="padding:6px 10px; border-radius:6px; border:1px solid var(--slate-200); font-size:13px; background:var(--white);">
        <option value="All" {{ request()->query('flow', 'All') === 'All' ? 'selected' : '' }}>Semua Modul Aktif</option>
        <option value="Nurse" {{ request()->query('flow') === 'Nurse' ? 'selected' : '' }}>Nurse</option>
        <option value="VA" {{ request()->query('flow') === 'VA' ? 'selected' : '' }}>VA</option>
        <option value="Kasir" {{ request()->query('flow') === 'Kasir' ? 'selected' : '' }}>Kasir</option>
        <option value="ADRUCOT" {{ request()->query('flow') === 'ADRUCOT' ? 'selected' : '' }}>ADRU COT</option>
        <option value="Farmasi" {{ request()->query('flow') === 'Farmasi' ? 'selected' : '' }}>Farmasi</option>
        <option value="AdminCOT" {{ request()->query('flow') === 'AdminCOT' ? 'selected' : '' }}>Admin COT</option>
        <option value="CaseManager" {{ request()->query('flow') === 'CaseManager' ? 'selected' : '' }}>Case Manager</option>
        <option value="CS" {{ request()->query('flow') === 'CS' ? 'selected' : '' }}>CS</option>
        <option value="Selesai" {{ request()->query('flow') === 'Selesai' ? 'selected' : '' }}>Selesai</option>
      </select>

      <input type="text" name="search" placeholder="Cari ID, Nama, RM..." value="{{ request()->query('search') }}" style="padding:7px 11px; border-radius:6px; border:1px solid var(--slate-200); font-size:13px; width:150px; background:var(--white);">
      <button type="submit" class="btn btn-sm" style="padding:7px 14px;">🔍 Cari</button>
      @if(request()->filled('search') || request()->filled('penjamin') || request()->filled('lokasi') || request()->filled('flow'))
        <a href="{{ url()->current() }}{{ $isMine ? '?queue=mine' : '' }}" class="btn btn-sm btn-danger" style="padding:7px 12px; text-decoration:none;">Reset</a>
      @endif
    </form>
  </div>

  <!-- Status Filter Tabs -->
  @php
    $currentStatus = request()->query('status', 'All');
    $filterBase = url()->current() . ($isMine ? '?queue=mine&' : '?');
    $queryParams = request()->except(['status', 'page']);
    if (!empty($queryParams)) {
        $filterBase .= http_build_query($queryParams) . '&';
    }
  @endphp
  <div class="filter-nav-tabs">
    <a href="{{ $filterBase }}status=All" class="filter-tab {{ $currentStatus === 'All' ? 'active' : '' }}">Semua</a>
    @if($activeRole === 'Nurse')
      <a href="{{ $filterBase }}status=Draft" class="filter-tab {{ $currentStatus === 'Draft' ? 'active' : '' }}">📝 Draft</a>
    @endif
    <a href="{{ $filterBase }}status=Submitted" class="filter-tab {{ $currentStatus === 'Submitted' ? 'active' : '' }}">📤 Diajukan</a>
    <a href="{{ $filterBase }}status=InProgress" class="filter-tab {{ $currentStatus === 'InProgress' ? 'active' : '' }}">⚙️ Dalam Proses</a>
    <a href="{{ $filterBase }}status=Returned" class="filter-tab {{ $currentStatus === 'Returned' ? 'active' : '' }}">↩️ Revisi</a>
    <a href="{{ $filterBase }}status=Completed" class="filter-tab {{ $currentStatus === 'Completed' ? 'active' : '' }}">✅ Selesai</a>
    <a href="{{ $filterBase }}status=Cancelled" class="filter-tab {{ $currentStatus === 'Cancelled' ? 'active' : '' }}">🚫 Batal</a>
  </div>

  <!-- Cards Grid -->
  <div class="cards-grid">
    @forelse($cases as $c)
      @php
        $vaActive = ($c->penjamin === 'Asuransi');
        $adminCotRequired = ($c->lokasi_tindakan === 'COT');
        $canEdit = ($activeRole === 'Nurse' && in_array($c->status, ['Draft', 'Returned']));
        $canSubmit = ($activeRole === 'Nurse' && in_array($c->status, ['Draft', 'Returned']));
        $canCancel = ($activeRole === 'Nurse' && in_array($c->status, ['Draft', 'Returned']));
      @endphp
      <div class="case-card status-{{ $c->status }}">
        <div class="case-card-header">
          <div>
            <div class="case-id">{{ $c->id }}</div>
            <div class="case-date">{{ $c->created_at->format('d M Y · H:i') }}</div>
          </div>
          <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
            <span class="badge-status {{ $c->status_badge_class }}">{{ $c->status_label }}</span>
            @if($c->penjamin === 'Asuransi')
              <span class="chip" style="background:#DBEAFE; color:#1E40AF; font-size:10px; padding:2px 7px;">Asuransi</span>
            @endif
          </div>
        </div>
        <div class="case-card-body">
          <div class="case-info-row">
            <span class="lbl">Pasien</span>
            <span class="val">{{ $c->nama }}</span>
          </div>
          <div class="case-info-row">
            <span class="lbl">No. RM</span>
            <span class="val">{{ $c->rm }}</span>
          </div>
          <div class="case-info-row">
            <span class="lbl">DPJP</span>
            <span class="val">{{ implode(', ', $c->dpjp_list) ?: '-' }}</span>
          </div>
          <div class="case-info-row">
            <span class="lbl">Tindakan</span>
            <span class="val">{{ implode(', ', $c->tindakan_list) ?: '-' }}</span>
          </div>
          <div class="case-info-row">
            <span class="lbl">Jadwal Pilihan 1</span>
            <span class="val">{{ $c->tanggal_pilihan1 ? $c->tanggal_pilihan1->format('d M Y') : '-' }}</span>
          </div>
          <div class="case-info-row">
            <span class="lbl">Lokasi / Anestesi</span>
            <span class="val">{{ $c->lokasi_tindakan === 'Lainnya' ? $c->lokasi_tindakan_lainnya : $c->lokasi_tindakan }} · {{ $c->anestesi ?: 'Konfirmasi' }}</span>
          </div>
        </div>
        <div class="case-card-footer">
          <div style="display:flex; flex-direction:column; gap:2px;">
            <span class="flow-chip">{{ $c->current_flow }}</span>
            <span style="font-size:10px; color:var(--slate-400); margin-top:3px;">Golongan: {{ $c->golongan ?: '-' }}</span>
          </div>
          <div class="actions">
            <a href="{{ route('cases.show', $c->id) }}" class="btn-action view">👁 Lihat</a>
            @if($canEdit)
              <a href="{{ route('cases.edit', $c->id) }}" class="btn-action edit">✏️ Edit</a>
            @endif
            @if($canSubmit)
              <button type="button" class="btn-action submit js-submit-case" data-id="{{ $c->id }}">📤 Submit</button>
            @endif
            @if($canCancel)
              <button type="button" class="btn-action cancel js-cancel-case" data-id="{{ $c->id }}">✕ Batal</button>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div class="empty-cards">
        <span class="icon">📋</span>
        <div style="font-weight:600; margin-bottom:6px;">Tidak ada kasus yang ditemukan</div>
        @if($isMine)
          <div style="font-size:12px;">Tidak ada kasus yang memerlukan tindakan dari role Anda saat ini.</div>
        @else
          <div style="font-size:12px;">Coba ubah filter status atau kata kunci pencarian.</div>
        @endif
      </div>
    @endforelse
  </div>

  @if($cases instanceof \Illuminate\Pagination\LengthAwarePaginator && $cases->hasPages())
    <div class="pagination-wrapper" style="margin-top:20px;">
      <div class="pagination-info">
        Menampilkan {{ $cases->firstItem() }} - {{ $cases->lastItem() }} dari {{ $cases->total() }} data
      </div>
      <div class="pagination-links">
        @if ($cases->onFirstPage())
          <span class="disabled">&laquo;</span>
        @else
          <a href="{{ $cases->previousPageUrl() }}">&laquo;</a>
        @endif

        @foreach ($cases->getUrlRange(max(1, $cases->currentPage() - 2), min($cases->lastPage(), $cases->currentPage() + 2)) as $page => $url)
          @if ($page == $cases->currentPage())
            <span class="active">{{ $page }}</span>
          @else
            <a href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach

        @if ($cases->hasMorePages())
          <a href="{{ $cases->nextPageUrl() }}">&raquo;</a>
        @else
          <span class="disabled">&raquo;</span>
        @endif
      </div>
    </div>
  @endif
</div>

@endsection

@section('scripts')
<script>
  // Quick submit from card (same API as show page submitBtn)
  document.querySelectorAll('.js-submit-case').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id;
      if (!confirm('Submit pengajuan kasus ' + id + '? Kasus akan diteruskan ke unit terkait.')) return;
      this.disabled = true;
      this.textContent = 'Mengirim...';

      fetch(`/cases/${id}/submit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Show brief success then reload
          const card = btn.closest('.case-card');
          card.style.opacity = '0.5';
          card.style.transition = 'opacity 0.1s';
          setTimeout(() => window.location.reload(), 100);
        } else {
          alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
          btn.disabled = false;
          btn.textContent = '📤 Submit';
        }
      })
      .catch(() => {
        alert('Koneksi gagal. Silakan coba lagi.');
        btn.disabled = false;
        btn.textContent = '📤 Submit';
      });
    });
  });

  // Quick cancel from card
  document.querySelectorAll('.js-cancel-case').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id;
      const reason = prompt('Alasan pembatalan kasus ' + id + ' (opsional):');
      if (reason === null) return; // user pressed Cancel on prompt
      this.disabled = true;

      fetch(`/cases/${id}/cancel`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ reason: reason })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const card = btn.closest('.case-card');
          card.style.opacity = '0.5';
          setTimeout(() => window.location.reload(), 100);
        } else {
          alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
          btn.disabled = false;
        }
      })
      .catch(() => {
        alert('Koneksi gagal. Silakan coba lagi.');
        btn.disabled = false;
      });
    });
  });
</script>
@endsection
