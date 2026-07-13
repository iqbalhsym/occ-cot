@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Daftar Kasus')
@section('page_title', request()->query('queue') === 'mine' ? 'Antrian Saya' : 'Semua Case')

@section('content')
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
      <h3>{{ request()->query('queue') === 'mine' ? 'Kasus Yang Perlu Anda Tindaklanjuti' : 'Daftar Semua Kasus Operasi' }}</h3>
      
      <!-- Filter controls -->
      <form method="GET" action="{{ url()->current() }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        @if(request()->query('queue'))
          <input type="hidden" name="queue" value="{{ request()->query('queue') }}">
        @endif
        
        <input type="text" name="search" placeholder="Cari ID, Nama, RM..." value="{{ request()->query('search') }}" style="padding:6px 10px; border-radius:4px; border:1px solid var(--slate-200); font-size:13px;">
        
        <select name="status" onchange="this.form.submit()" style="padding:6px 10px; border-radius:4px; border:1px solid var(--slate-200); font-size:13px;">
          <option value="All" {{ request()->query('status') === 'All' ? 'selected' : '' }}>Semua Status</option>
          <option value="Draft" {{ request()->query('status') === 'Draft' ? 'selected' : '' }}>Draft</option>
          <option value="Submitted" {{ request()->query('status') === 'Submitted' ? 'selected' : '' }}>Diajukan</option>
          <option value="InProgress" {{ request()->query('status') === 'InProgress' ? 'selected' : '' }}>Dalam Proses</option>
          <option value="Returned" {{ request()->query('status') === 'Returned' ? 'selected' : '' }}>Revisi</option>
          <option value="Completed" {{ request()->query('status') === 'Completed' ? 'selected' : '' }}>Selesai</option>
          <option value="Cancelled" {{ request()->query('status') === 'Cancelled' ? 'selected' : '' }}>Batal</option>
        </select>

        <button type="submit" class="btn btn-sm" style="padding:6px 12px;">Cari</button>
        @if(request()->filled('search') || request()->filled('status'))
          <a href="{{ url()->current() }}{{ request()->query('queue') === 'mine' ? '?queue=mine' : '' }}" class="btn btn-sm btn-danger" style="padding:6px 12px; text-decoration:none; display:inline-block;">Reset</a>
        @endif
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
          @forelse($cases as $c)
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
              <td colspan="8">Tidak ada kasus yang ditemukan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($cases instanceof \Illuminate\Pagination\LengthAwarePaginator && $cases->hasPages())
      <div class="pagination-wrapper">
        <div class="pagination-info">
          Menampilkan {{ $cases->firstItem() }} - {{ $cases->lastItem() }} dari {{ $cases->total() }} data
        </div>
        <div class="pagination-links">
          {{-- Previous Page Link --}}
          @if ($cases->onFirstPage())
            <span class="disabled">&laquo;</span>
          @else
            <a href="{{ $cases->previousPageUrl() }}">&laquo;</a>
          @endif

          {{-- Pagination Elements --}}
          @foreach ($cases->getUrlRange(max(1, $cases->currentPage() - 2), min($cases->lastPage(), $cases->currentPage() + 2)) as $page => $url)
            @if ($page == $cases->currentPage())
              <span class="active">{{ $page }}</span>
            @else
              <a href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach

          {{-- Next Page Link --}}
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
