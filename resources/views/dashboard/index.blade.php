@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Dashboard')
@section('page_title', 'Dashboard')

@section('content')
  <div class="stat-grid">
    <div class="stat-card">
      <div class="num">{{ $total }}</div>
      <div class="lbl">Total Case</div>
    </div>
    <div class="stat-card">
      <div class="num">{{ $aktif }}</div>
      <div class="lbl">Sedang Berjalan</div>
    </div>
    <div class="stat-card">
      <div class="num">{{ $returned }}</div>
      <div class="lbl">Returned / Revisi</div>
    </div>
    <div class="stat-card">
      <div class="num">{{ $selesai }}</div>
      <div class="lbl">Completed</div>
    </div>
    <div class="stat-card">
      <div class="num">{{ $byPenjamin['Asuransi'] }} / {{ $byPenjamin['Umum'] }}</div>
      <div class="lbl">Asuransi / Umum</div>
    </div>
  </div>

  <!-- JADWAL TINDAKAN & PRIORITAS -->
  <div class="card" style="border-left: 5px solid var(--danger-600); margin-bottom: 20px; background: #FFF5F5;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
      <span style="font-size:20px;">🚨</span>
      <h3 style="margin:0; color:#9B2C2C;">Jadwal Tindakan &amp; Prioritas (7 Hari / Terlewat)</h3>
    </div>
    <p style="margin: 0 0 12px 0; font-size: 13px; color: #7B341E; font-weight: 500;">
      Kasus aktif yang sudah terjadwal dalam 7 hari ke depan, jadwalnya terlewat, atau belum mendapat tindakan/jadwal tetap setelah 7 hari sejak diajukan.
    </p>
    <div style="overflow-x:auto;">
      <table style="width:100%;">
        <thead>
          <tr style="background:#FED7D7; color:#9B2C2C; font-weight:700;">
            <th>Case ID</th>
            <th>Pasien</th>
            <th>Tindakan</th>
            <th>Jadwal Tindakan</th>
            <th>Tanggal Pengajuan</th>
            <th>Modul Aktif</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($priorityCases as $c)
            @php
              $hasFixDate = $c->adminCot && $c->adminCot->tanggal_fix;
              $fixDate = $hasFixDate ? \Carbon\Carbon::parse($c->adminCot->tanggal_fix) : null;
              $pilihanDate = $c->tanggal_pilihan1 ? \Carbon\Carbon::parse($c->tanggal_pilihan1) : null;
              $jadwalText = $hasFixDate 
                ? 'Fix: ' . $fixDate->format('d M Y') . ($c->adminCot->jam_fix ? ' • ' . $c->adminCot->jam_fix : '')
                : ($c->tanggal_pilihan1 ? 'Pilihan 1: ' . $pilihanDate->format('d M Y') : 'Belum Terjadwal');
              
              // Determine if overdue or priority alert style
              $isOverdue = false;
              if ($hasFixDate && $fixDate->isPast() && $c->status !== 'Completed') {
                  $isOverdue = true;
              } elseif (!$hasFixDate && $c->created_at->diffInDays(now()) >= 7) {
                  $isOverdue = true;
              }
            @endphp
            <tr style="cursor:pointer; background: {{ $isOverdue ? '#FFF5F5' : '#FFFDF5' }}; border-bottom: 1px solid #FEB2B2;" onclick="window.location.href='{{ route('cases.show', $c->id) }}'">
              <td>
                <strong style="color:var(--primary-800);">{{ $c->id }}</strong>
                @if($isOverdue)
                  <br><span class="badge-status st-Returned" style="font-size:10px; padding:2px 6px;">TERLEWAT / OVERDUE</span>
                @endif
              </td>
              <td>
                {{ $c->nama }}<br>
                <span class="footer-hint">RM {{ $c->rm }}</span>
              </td>
              <td>
                {{ implode(', ', $c->tindakan_list) ?: '-' }}
              </td>
              <td style="font-weight: 600; color: {{ $isOverdue ? '#C53030' : '#B7791F' }};">
                {{ $jadwalText }}
              </td>
              <td>
                {{ $c->created_at->format('d M Y') }}<br>
                <span class="footer-hint">({{ $c->created_at->diffForHumans() }})</span>
              </td>
              <td>
                <span class="chip chip-flow">{{ $c->current_flow }}</span>
              </td>
              <td>
                <span class="badge-status {{ $c->status_badge_class }}">{{ $c->status_label }}</span>
              </td>
            </tr>
          @empty
            <tr class="empty-row">
              <td colspan="7" style="text-align:center; color:#7B341E; padding: 16px;">Tidak ada kasus prioritas atau jadwal terlewat saat ini.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
      <h3 style="margin:0;">Timeline Semua Pasien</h3>
      <form method="GET" action="{{ route('dashboard') }}" id="filterForm">
        <select name="status" onchange="document.getElementById('filterForm').submit();" class="form-control" style="width:180px; padding:6px 12px; border-radius:6px; border:1px solid var(--slate-300); font-size:13px; font-weight:500;">
          <option value="All" {{ $status === 'All' ? 'selected' : '' }}>Semua Status</option>
          <option value="Draft" {{ $status === 'Draft' ? 'selected' : '' }}>Draft</option>
          <option value="Submitted" {{ $status === 'Submitted' ? 'selected' : '' }}>Submitted</option>
          <option value="InProgress" {{ $status === 'InProgress' ? 'selected' : '' }}>In Progress</option>
          <option value="Returned" {{ $status === 'Returned' ? 'selected' : '' }}>Returned/Revisi</option>
          <option value="Completed" {{ $status === 'Completed' ? 'selected' : '' }}>Completed</option>
          <option value="Cancelled" {{ $status === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
      </form>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Case ID</th>
            <th>Pasien</th>
            <th>DPJP</th>
            <th>Tindakan</th>
            <th>Penjamin</th>
            <th>Lokasi</th>
            <th>Modul Aktif</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($timelineCases as $c)
            <tr style="cursor:pointer;" onclick="window.location.href='{{ route('cases.show', $c->id) }}'">
              <td>
                <strong>{{ $c->id }}</strong><br>
                <span class="footer-hint">{{ $c->created_at->format('d M Y H:i') }}</span>
              </td>
              <td>
                {{ $c->nama }}<br>
                <span class="footer-hint">RM {{ $c->rm }}</span>
              </td>
              <td>
                {{ implode(', ', $c->dpjp_list) ?: '-' }}
              </td>
              <td>
                {{ implode(', ', $c->tindakan_list) ?: '-' }}
              </td>
              <td>
                <span class="chip">{{ $c->penjamin }}</span>
              </td>
              <td>
                {{ $c->lokasi_tindakan === 'Lainnya' ? $c->lokasi_tindakan_lainnya : $c->lokasi_tindakan }}
              </td>
              <td>
                <span class="chip chip-flow">{{ $c->current_flow }}</span>
              </td>
              <td>
                <span class="badge-status {{ $c->status_badge_class }}">{{ $c->status_label }}</span>
              </td>
            </tr>
          @empty
            <tr class="empty-row">
              <td colspan="8">Belum ada data case.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
