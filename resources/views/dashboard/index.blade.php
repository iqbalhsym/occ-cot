@extends('layouts.app')

@section('title', 'HAI Care COT — Dashboard')
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

  <div class="card">
    <h3>Timeline Semua Pasien</h3>
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
