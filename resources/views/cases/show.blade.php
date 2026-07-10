@extends('layouts.app')

@section('title', 'Operation Command Center — Detail Case ' . $case->id)
@section('page_title', 'Detail Case ' . $case->id)

@section('content')
  @php
    $currentUser = Auth::user();
    $activeRole = session('role', $currentUser ? $currentUser->role : 'Viewer');
    $isViewer = ($activeRole === 'Viewer');
    // Make $rolesList available locally for action panel header
    $rolesList = [
      ['id' => 'Nurse',      'label' => 'Nurse (Pengaju)'],
      ['id' => 'VA',         'label' => 'VA (Verifikator Asuransi)'],
      ['id' => 'Kasir',      'label' => 'Kasir (Billing)'],
      ['id' => 'ADRUCOT',    'label' => 'ADRU COT (Estimator)'],
      ['id' => 'Farmasi',    'label' => 'Farmasi (BMHP/Obat)'],
      ['id' => 'AdminCOT',   'label' => 'Admin COT (Penjadwal & Alat)'],
      ['id' => 'CaseManager','label' => 'Case Manager (CM)'],
      ['id' => 'CS',         'label' => 'Customer Service (CS)'],
      ['id' => 'Viewer',     'label' => 'Viewer (Semua Data)'],
    ];
    
    // Evaluate workflow stages matching JS stepStatusFor function
    // stepper statuses: done, active, next, skip, warning, returned
    $statusMap = [
        'Nurse' => 'done',
        'VA' => 'next',
        'Kasir' => 'next',
        'ADRU' => 'next',
        'Farmasi' => 'next',
        'CM' => 'next',
        'CS' => 'next',
        'Admin' => 'next',
        'Selesai' => 'next'
    ];

    if ($case->status === 'Draft') {
        $statusMap['Nurse'] = 'active';
    } elseif ($case->status === 'Returned') {
        $statusMap['Nurse'] = 'returned';
    } else {
        $statusMap['Nurse'] = 'done';
    }

    $vaActive = ($case->penjamin === 'Asuransi');
    $adminCotRequired = ($case->lokasi_tindakan === 'COT');

    // VA stage
    if ($vaActive) {
        $statusMap['Kasir'] = 'skip';
        $statusMap['ADRU'] = 'skip';
        if ($case->status === 'Draft' || $case->status === 'Returned' && !$case->va->estimasi_total) {
            $statusMap['VA'] = 'next';
        } elseif (!$case->va->done) {
            $statusMap['VA'] = 'active';
        } else {
            $statusMap['VA'] = 'done';
        }
    } else {
        $statusMap['VA'] = 'skip';
        if ($case->status === 'Draft' || $case->status === 'Returned') {
            $statusMap['Kasir'] = 'next';
            $statusMap['ADRU'] = 'next';
        } else {
            $statusMap['Kasir'] = $case->kasir->done ? 'done' : 'active';
            $statusMap['ADRU'] = $case->adru->done ? 'done' : 'active';
        }
    }

    // Farmasi stage
    if ($case->status === 'Draft') {
        $statusMap['Farmasi'] = 'next';
    } else {
        $statusMap['Farmasi'] = $case->farmasi->done ? 'done' : 'active';
    }

    // CM stage
    if ($case->status === 'Draft') {
        $statusMap['CM'] = 'next';
    } else {
        $stage1Done = $vaActive ? ($case->va->estimasi_total > 0) : ($case->kasir->done && $case->adru->done);
        $cmGateReady = $stage1Done && $case->farmasi->done && (!$adminCotRequired || $case->adminCot->prelim_done);
        if ($case->caseManager->done) {
            $statusMap['CM'] = 'done';
        } elseif ($cmGateReady) {
            $statusMap['CM'] = 'active';
        } else {
            $statusMap['CM'] = 'next';
        }
    }

    // CS stage
    if ($vaActive) {
        if ($case->cs->done) {
            $statusMap['CS'] = 'done';
        } elseif ($case->va->done) {
            $statusMap['CS'] = 'active';
        } else {
            $statusMap['CS'] = 'next';
        }
    } else {
        $statusMap['CS'] = 'skip';
    }

    // Admin COT stage
    if ($adminCotRequired) {
        if ($case->adminCot->final_done) {
            $statusMap['Admin'] = 'done';
        } elseif ($case->adminCot->prelim_done) {
            $routeDone = $vaActive ? $case->cs->done : ($case->kasir->done && $case->adru->done);
            $statusMap['Admin'] = $routeDone ? 'active' : 'next'; // Active for final scheduling
        } else {
            $statusMap['Admin'] = ($case->status !== 'Draft') ? 'active' : 'next'; // Active for prelim tools
        }
    } else {
        $statusMap['Admin'] = 'skip';
    }

    // Selesai stage
    if ($case->status === 'Completed') {
        $statusMap['Selesai'] = 'done';
    } else {
        $statusMap['Selesai'] = 'next';
    }
  @endphp

  <div class="case-header">
    <div>
      <h2>{{ $case->id }} — {{ $case->nama }}</h2>
      <div class="meta">
        Dibuat {{ $case->created_at->format('d M Y H:i') }} &middot; 
        Modul aktif: <span class="chip chip-flow">{{ $case->current_flow }}</span> &middot; 
        Status: <span class="badge-status {{ $case->status_badge_class }}">{{ $case->status_label }}</span>
      </div>
    </div>
    <div class="btn-row">
      <a href="{{ route('cases.index') }}" class="btn">&larr; Kembali</a>
    </div>
  </div>

  <!-- Stepper Timeline -->
  <div class="card">
    <h3>Progres Alur Kerja</h3>
    <div class="stepper">
      <div class="step {{ $statusMap['Nurse'] }}">Nurse (Awal)</div>
      @if($vaActive)
        <div class="step {{ $statusMap['VA'] }}">VA (Estimasi)</div>
      @else
        <div class="step {{ $statusMap['Kasir'] }}">Kasir</div>
        <div class="step {{ $statusMap['ADRU'] }}">ADRU COT</div>
      @endif
      <div class="step {{ $statusMap['Farmasi'] }}">Farmasi</div>
      @if($adminCotRequired)
        <div class="step {{ $statusMap['Admin'] }}">Admin COT (Alat)</div>
      @endif
      <div class="step {{ $statusMap['CM'] }}">Case Manager</div>
      @if($vaActive)
        <div class="step {{ $statusMap['CS'] }}">CS (Konfirmasi)</div>
      @endif
      @if($adminCotRequired)
        <div class="step {{ $statusMap['Admin'] }}">Admin COT (Final)</div>
      @endif
      <div class="step {{ $statusMap['Selesai'] }}">Selesai</div>
    </div>
  </div>

  <!-- Unit Status Grid -->
  <div class="card">
    <h3>Status Tiap Unit</h3>
    <div class="unit-status-grid">
      @if($vaActive)
        <div class="unit-status-card">
          <div class="u-name">VA</div>
          @if($case->va->done)
            <span class="badge-status st-Approved">Selesai</span>
          @elseif($case->case_manager_done)
            <span class="badge-status st-{{ $case->va->decision ?: 'Menunggu' }}">{{ $case->va->decision ?: 'Menunggu' }}</span>
          @elseif($case->va->estimasi_total > 0)
            <span class="badge-status st-Approved">Approved</span>
          @else
            <span class="badge-status st-Menunggu">Menunggu</span>
          @endif
        </div>
      @else
        <div class="unit-status-card">
          <div class="u-name">Kasir</div>
          <span class="badge-status st-{{ $case->kasir->done ? 'Approved' : 'Menunggu' }}">{{ $case->kasir->done ? 'Selesai' : 'Menunggu' }}</span>
        </div>
        <div class="unit-status-card">
          <div class="u-name">ADRU COT</div>
          <span class="badge-status st-{{ $case->adru->done ? 'Approved' : 'Menunggu' }}">{{ $case->adru->done ? 'Selesai' : 'Menunggu' }}</span>
        </div>
      @endif
      
      <div class="unit-status-card">
        <div class="u-name">Farmasi</div>
        <span class="badge-status st-{{ $case->farmasi->done ? 'Approved' : 'Menunggu' }}">{{ $case->farmasi->done ? 'Approved' : 'Menunggu' }}</span>
      </div>

      <div class="unit-status-card">
        <div class="u-name">Admin COT</div>
        @if(!$case->adminCot->required)
          <span class="badge-status st-default">Tidak Diperlukan</span>
        @elseif($case->adminCot->final_done)
          <span class="badge-status st-{{ $case->adminCot->decision ?: 'Terjadwal' }}">{{ $case->adminCot->decision ?: 'Terjadwal' }}</span>
        @elseif($case->adminCot->prelim_done)
          <span class="badge-status st-DalamKonfirmasi">Dalam Konfirmasi</span>
        @else
          <span class="badge-status st-Menunggu">Menunggu</span>
        @endif
      </div>

      <div class="unit-status-card">
        <div class="u-name">Case Manager</div>
        @if($case->caseManager->done)
          <span class="badge-status st-Disetujui">Disetujui</span>
        @elseif($case->caseManager->decision)
          <span class="badge-status st-{{ $case->caseManager->decision }}">{{ $case->caseManager->decision }}</span>
        @else
          <span class="badge-status st-Menunggu">Menunggu</span>
        @endif
      </div>

      @if($vaActive)
        <div class="unit-status-card">
          <div class="u-name">CS</div>
          <span class="badge-status st-{{ $case->cs->done ? 'Approved' : ($case->cs->decision ?: 'Menunggu') }}">{{ $case->cs->done ? 'Selesai' : ($case->cs->decision ?: 'Menunggu') }}</span>
        </div>
      @endif
    </div>
  </div>

  <!-- Two Column Layout: Details + Actions vs Audit Log -->
  <div class="two-col">
    <div>
      <div class="card">
        <h3>Data Case</h3>
        <dl class="info-grid">
          <div><dt>No. RM</dt><dd>{{ $case->rm }}</dd></div>
          <div><dt>Jenis Kelamin / Tgl Lahir</dt><dd>{{ $case->jenis_kelamin }} / {{ $case->tgl_lahir ?: '-' }}</dd></div>
          <div><dt>DPJP</dt><dd>{{ implode(', ', $case->dpjp_list) ?: '-' }}</dd></div>
          <div><dt>Operator</dt><dd>{{ implode(', ', $case->operator_list) ?: '-' }}</dd></div>
          <div><dt>Diagnosis</dt><dd>{{ $case->diagnosis ?: '-' }}</dd></div>
          <div><dt>Tindakan</dt><dd>{{ implode(', ', $case->tindakan_list) ?: '-' }}</dd></div>
          <div><dt>Golongan Tindakan</dt><dd>{{ $case->golongan ?: '-' }}</dd></div>
          <div><dt>Spesialisasi Operator</dt><dd>{{ $case->spesialisasi_op ?: '-' }}</dd></div>
          <div><dt>Jenis Operasi</dt><dd>{{ $case->jenis_operasi ? implode(', ', $case->jenis_operasi) : '-' }}</dd></div>
          <div><dt>Anestesi</dt><dd>{{ $case->anestesi ?: 'Dalam Konfirmasi' }} {{ $case->anestesi === 'Lainnya' ? '('.$case->anestesi_lainnya.')' : '' }}</dd></div>
          <div><dt>Jadwal (Pilihan 1 / 2)</dt><dd>{{ $case->tanggal_pilihan1 ? $case->tanggal_pilihan1->format('d M Y') : '-' }} / {{ $case->tanggal_pilihan2 ? $case->tanggal_pilihan2->format('d M Y') : '-' }} {{ $case->jam_operasi ? ' • ' . $case->jam_operasi : '' }}</dd></div>
          <div><dt>Estimasi Lama Operasi</dt><dd>{{ $case->estimasi_lama_operasi ?: '-' }}</dd></div>
          <div><dt>Lokasi Tindakan</dt><dd>{{ $case->lokasi_tindakan === 'Lainnya' ? $case->lokasi_tindakan_lainnya : $case->lokasi_tindakan }}</dd></div>
          <div><dt>Asal Pasien</dt><dd>{{ $case->asal_pasien === 'Lainnya' ? $case->asal_pasien_lainnya : $case->asal_pasien }}</dd></div>
          <div><dt>Ruang Pasca Operasi</dt><dd>{{ $case->ruang_pasca_operasi === 'Lainnya' ? $case->ruang_pasca_operasi_lainnya : $case->ruang_pasca_operasi }}</dd></div>
          <div><dt>Estimasi Rawat Inap</dt><dd>{{ $case->estimasi_rawat_inap ?: '-' }}</dd></div>
          <div><dt>Penjamin</dt><dd>{{ $case->penjamin }} {{ $case->penjamin === 'Asuransi' ? ' — ' . $case->nama_guarantor : '' }}</dd></div>
          <div><dt>Kelas Perawatan</dt><dd>{{ $case->kelas_perawatan ?: '-' }}</dd></div>
          <div>
            <dt>Estimasi Biaya Jasa Medis</dt>
            <dd>
              @if($case->va && $case->va->estimasi_total > 0)
                Rp {{ number_format($case->va->estimasi_total, 0, ',', '.') }}
              @elseif($case->adru && $case->adru->estimasi)
                {{ $case->adru->estimasi }}
              @else
                -
              @endif
            </dd>
          </div>
        </dl>

        @if($case->va && $case->va->estimasi_total > 0)
          <div class="section-lbl" style="margin-top:18px;">Rincian Jasa Medis (VA) — Golongan {{ $case->va->golongan }} / Kelas {{ $case->va->kelas }}</div>
          <table class="af-table" style="margin-top:8px;">
            <thead>
              <tr style="font-weight:700;"><td>Komponen Jasa Medis</td><td>Nilai (Rp)</td></tr>
            </thead>
            <tbody>
              @foreach($case->va->estimasi_rincian as $r)
                <tr><td>{{ $r['komponen'] }}</td><td>Rp {{ number_format($r['nilai'], 0, ',', '.') }}</td></tr>
              @endforeach
              <tr style="font-weight:800;"><td>TOTAL</td><td>Rp {{ number_format($case->va->estimasi_total, 0, ',', '.') }}</td></tr>
            </tbody>
          </table>
          <div style="margin-top:12px;">
            <a href="{{ route('cases.download-estimasi', $case->id) }}" target="_blank" class="btn btn-sm">🖨️ Print Estimasi Biaya</a>
          </div>
        @endif

        <div class="section-lbl" style="margin-top:18px;">Alat Khusus</div>
        <div style="margin-top:6px;">
          @forelse($case->alat as $a)
            <span class="chip">{{ $a->nama }}</span>
          @empty
            <span class="footer-hint">Belum ada</span>
          @endforelse
        </div>

        <div class="section-lbl" style="margin-top:18px;">Tambahan di Luar Paket</div>
        <div style="margin-top:6px;">
          @forelse($case->tambahanBmhp as $t)
            <span class="chip">{{ $t->nama }} (Qty: {{ $t->qty }})</span>
          @empty
            <span class="footer-hint">Tidak ada</span>
          @endforelse
        </div>

        @if($case->adminCot->final_done)
          <div class="section-lbl" style="margin-top:18px;">Jadwal Operasi Final (Admin COT)</div>
          <div style="font-weight:600; font-size:14px; margin-top:8px;">
            {{ $case->adminCot->tanggal_fix->format('d M Y') }} • {{ $case->adminCot->jam_fix }} • Ruang {{ $case->adminCot->kamar_operasi }}
          </div>
        @endif
      </div>

      <!-- Actions Panel -->
      <div class="card" id="actionCard">
        <h3>Aksi Role: {{ $rolesList[array_search($activeRole, array_column($rolesList, 'id'))]['label'] }}</h3>
        <div id="actionArea">
          @if($isViewer)
            <div class="locked-note">Mode <strong>Viewer</strong> — hanya melihat. Anda tidak dapat melakukan aksi.</div>
          @elseif($case->status === 'Cancelled')
            <div class="locked-note">Case ini sudah dibatalkan.</div>
          @elseif($case->status === 'Completed')
            <div class="locked-note">Case ini sudah Completed.</div>
          @else
            <!-- Action Form/Buttons based on role -->
            
            <!-- NURSE ACTION -->
            @if($activeRole === 'Nurse')
              @if($case->status === 'Draft' || $case->status === 'Returned')
                <div class="btn-row">
                  <a href="{{ route('cases.edit', $case->id) }}" class="btn btn-primary">Edit Draft</a>
                  <button type="button" class="btn btn-primary" id="submitBtn">Submit Pengajuan</button>
                  <button type="button" class="btn btn-danger" id="cancelBtn">Batalkan Case</button>
                </div>
              @else
                <div class="locked-note">Kasus telah diajukan. Nurse hanya memantau progres alur kerja.</div>
              @endif
            @endif

            <!-- VA ACTION -->
            @if($activeRole === 'VA' && $vaActive)
              @if(!$case->va->done)
                @if($case->va->estimasi_total == 0)
                  <!-- VA Stage 1: Susun Estimasi -->
                  <h4>Susun Estimasi Jasa Medis</h4>
                  <div class="field" style="margin-bottom:12px;">
                    <label>Golongan Tindakan</label>
                    <select id="vaGolongan" class="form-control">
                      <option value="KECIL" {{ $case->golongan === 'KECIL' ? 'selected' : '' }}>KECIL</option>
                      <option value="SEDANG" {{ $case->golongan === 'SEDANG' ? 'selected' : '' }}>SEDANG</option>
                      <option value="BESAR" {{ $case->golongan === 'BESAR' ? 'selected' : '' }}>BESAR</option>
                      <option value="KHUSUS A" {{ $case->golongan === 'KHUSUS A' ? 'selected' : '' }}>KHUSUS A</option>
                      <option value="KHUSUS B" {{ $case->golongan === 'KHUSUS B' ? 'selected' : '' }}>KHUSUS B</option>
                      <option value="KHUSUS C" {{ $case->golongan === 'KHUSUS C' ? 'selected' : '' }}>KHUSUS C</option>
                    </select>
                  </div>
                  <div class="field" style="margin-bottom:12px;">
                    <label>Kelas Perawatan</label>
                    <select id="vaKelas" class="form-control">
                      <option value="Kelas 3" {{ $case->kelas_perawatan === 'Kelas 3' ? 'selected' : '' }}>Kelas 3</option>
                      <option value="Kelas 2" {{ $case->kelas_perawatan === 'Kelas 2' ? 'selected' : '' }}>Kelas 2</option>
                      <option value="Kelas 1" {{ $case->kelas_perawatan === 'Kelas 1' ? 'selected' : '' }}>Kelas 1</option>
                      <option value="VIP" {{ $case->kelas_perawatan === 'VIP' ? 'selected' : '' }}>VIP</option>
                      <option value="VVIP" {{ $case->kelas_perawatan === 'VVIP' ? 'selected' : '' }}>VVIP</option>
                    </select>
                  </div>

                  <div id="vaEstimasiBox" style="margin-bottom:15px;"></div>

                  <div class="field" style="margin-bottom:12px;">
                    <label>Catatan VA</label>
                    <textarea id="vaNote" style="width:100%;" placeholder="Catatan estimasi..."></textarea>
                  </div>

                  <div class="btn-row">
                    <button type="button" class="btn btn-primary" id="vaAjukanBtn">Ajukan ke Case Manager</button>
                    <button type="button" class="btn btn-danger" id="vaRevisiBtn">Minta Revisi ke Nurse</button>
                  </div>
                @elseif($case->caseManager->done)
                  <!-- VA Stage 2: Verifikasi & Finalisasi Asuransi -->
                  <h4>Proses Verifikasi Asuransi</h4>
                  <div class="permission-note" style="margin-bottom:12px;">Case Manager telah menyetujui dokumen estimasi awal. Silakan upload berkas kelayakan asuransi, mulai verifikasi, dan submit keputusan final asuransi.</div>
                  
                  <div class="field" style="margin-bottom:12px;">
                    <label>Catatan/Keputusan Asuransi</label>
                    <textarea id="vaFinalNote" style="width:100%;" placeholder="Catatan/No. Surat Jaminan..."></textarea>
                  </div>

                  <div class="btn-row">
                    <button type="button" class="btn" id="vaMulaiBtn">Mulai Verifikasi Asuransi</button>
                    <button type="button" class="btn btn-danger" id="vaBelumLengkapBtn">Tandai Berkas Belum Lengkap</button>
                    <button type="button" class="btn btn-primary" id="vaSetujuBtn">Disetujui (Surat Jaminan Terbit)</button>
                  </div>
                  <div class="btn-row" style="margin-top:10px;">
                    <button type="button" class="btn" id="vaPendingBtn">Pending (Asuransi)</button>
                    <button type="button" class="btn btn-danger" id="vaTolakBtn">Ditolak (Asuransi)</button>
                  </div>
                @else
                  <div class="locked-note">Estimasi awal telah diajukan ke Case Manager. Menunggu verifikasi dokumen oleh Case Manager.</div>
                @endif
              @else
                <div class="locked-note">Proses verifikasi asuransi telah selesai. CS akan menindaklanjuti.</div>
              @endif
            @endif

            <!-- KASIR ACTION -->
            @if($activeRole === 'Kasir' && !$vaActive)
              @if(!$case->kasir->done)
                <h4>Verifikasi Administrasi Awal (Umum)</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan Kasir</label>
                  <textarea id="kasirNote" style="width:100%;" placeholder="Catatan..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn" id="kasirMulaiBtn">Mulai Administrasi</button>
                  <button type="button" class="btn btn-primary" id="kasirSelesai1Btn">Selesaikan Tahap Awal</button>
                  <button type="button" class="btn btn-danger" id="kasirRevisiBtn">Revisi Ke Nurse</button>
                </div>
              @elseif($case->caseManager->done)
                <h4>Verifikasi Administrasi Akhir (Umum)</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan Pembayaran Akhir</label>
                  <textarea id="kasirFinalNote" style="width:100%;" placeholder="Catatan..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn btn-primary" id="kasirSelesai2Btn">Validasi &amp; Selesaikan</button>
                </div>
              @else
                <div class="locked-note">Administrasi awal diselesaikan. Menunggu persetujuan Case Manager.</div>
              @endif
            @endif

            <!-- ADRU COT ACTION -->
            @if($activeRole === 'ADRUCOT' && !$vaActive)
              @if(!$case->adru->done)
                <h4>Estimasi Biaya Tindakan (Umum)</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Nilai Perkiraan Biaya (Rp)</label>
                  <input id="adruEstimasi" placeholder="mis. Rp 15.000.000">
                </div>
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan ADRU</label>
                  <textarea id="adruNote" style="width:100%;" placeholder="Catatan..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn" id="adruMulaiBtn">Mulai Proses</button>
                  <button type="button" class="btn btn-primary" id="adruAjukanBtn">Ajukan ke Case Manager</button>
                  <button type="button" class="btn btn-danger" id="adruRevisiBtn">Revisi Ke Nurse</button>
                </div>
              @elseif($case->caseManager->done)
                <h4>Konfirmasi Persetujuan Pasien (Umum)</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Keterangan Persetujuan</label>
                  <textarea id="adruConfirmNote" style="width:100%;" placeholder="Keterangan..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn btn-primary" id="adruSetujuBtn">Pasien Menyetujui Biaya</button>
                </div>
              @else
                <div class="locked-note">Estimasi biaya diajukan ke Case Manager. Menunggu persetujuan CM.</div>
              @endif
            @endif

            <!-- FARMASI ACTION -->
            @if($activeRole === 'Farmasi')
              @if(!$case->farmasi->done)
                <h4>Review Kebutuhan Paket BMHP / Obat</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan Farmasi</label>
                  <textarea id="farmasiNote" style="width:100%;" placeholder="Catatan obat/BMHP..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn" id="farmasiMulaiBtn">Mulai Review</button>
                  <button type="button" class="btn btn-primary" id="farmasiSetujuBtn">Setujui &amp; Ambil Paket</button>
                  <button type="button" class="btn btn-danger" id="farmasiRevisiBtn">Minta Revisi Paket</button>
                </div>
              @else
                <div class="locked-note">BMHP &amp; Obat sudah disiapkan oleh Farmasi.</div>
              @endif
            @endif

            <!-- CASE MANAGER ACTION -->
            @if($activeRole === 'CaseManager')
              @php
                $stage1Done = $vaActive ? ($case->va->estimasi_total > 0) : ($case->kasir->done && $case->adru->done);
                $cmGateReady = $stage1Done && $case->farmasi->done && (!$adminCotRequired || $case->adminCot->prelim_done);
              @endphp

              @if($cmGateReady && !$case->caseManager->done)
                <h4>Persetujuan Case Manager (Verifikator Dokumen)</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Tujuan Kembalikan / Revisi <span class="hint">(jika ada revisi)</span></label>
                  <select id="cmReturnTo" class="form-control">
                    <option value="Nurse">Nurse (data awal)</option>
                    <option value="VA">VA (estimasi/dokumen asuransi)</option>
                    <option value="Kasir">Kasir (administrasi umum)</option>
                    <option value="ADRUCOT">ADRU COT (estimasi umum)</option>
                    <option value="Farmasi">Farmasi (paket BMHP)</option>
                    <option value="AdminCOT">Admin COT (kebutuhan alat)</option>
                  </select>
                </div>
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan / Instruksi CM</label>
                  <textarea id="cmNote" style="width:100%;" placeholder="Instruksi CM..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn btn-primary" id="cmSetujuBtn">Setujui Estimasi &amp; Dokumen</button>
                  <button type="button" class="btn btn-danger" id="cmRevisiBtn">Kembalikan untuk Revisi</button>
                  <button type="button" class="btn" id="cmBelumLengkapBtn">Dokumen Belum Lengkap</button>
                </div>
              @else
                <div class="locked-note">Menunggu kelengkapan data awal/estimasi/BMHP/alat dari unit pelaksana sebelum diverifikasi oleh Case Manager.</div>
              @endif
            @endif

            <!-- CUSTOMER SERVICE ACTION -->
            @if($activeRole === 'CS' && $vaActive)
              @if($case->va->done && !$case->cs->done)
                <h4>Follow Up &amp; Konfirmasi Pasien</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan Follow Up</label>
                  <textarea id="csNote" style="width:100%;" placeholder="Keterangan respon pasien..."></textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn" id="csHubungiBtn">Hubungi Pasien (Follow Up)</button>
                  <button type="button" class="btn btn-primary" id="csSetujuBtn">Pasien Setuju Tindakan</button>
                  <button type="button" class="btn" id="csRescheduleBtn">Pasien Minta Reschedule</button>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                  <button type="button" class="btn btn-danger" id="csBatalBtn">Pasien Batal Tindakan</button>
                  <button type="button" class="btn" id="csKonfirmasiBtn">Kembalikan ke Case Manager</button>
                </div>
              @else
                <div class="locked-note">Menunggu verifikasi jaminan asuransi selesai sebelum dilakukan follow-up ke pasien.</div>
              @endif
            @endif

            <!-- ADMIN COT ACTION -->
            @if($activeRole === 'AdminCOT' && $adminCotRequired)
              @if(!$case->adminCot->prelim_done)
                <!-- Admin COT Stage 1: Alat Khusus -->
                <h4>Prelim: Verifikasi Kebutuhan Alat Khusus</h4>
                <div class="field" style="margin-bottom:12px;">
                  <label>Masukkan Kebutuhan Alat Khusus (pisahkan dengan koma)</label>
                  <input id="adminAlat" placeholder="mis. C-ARM, Boor High Speed" value="{{ $case->alat->pluck('nama')->implode(', ') }}">
                </div>
                <div class="btn-row">
                  <button type="button" class="btn btn-primary" id="adminPrelimBtn">Simpan Kebutuhan Alat</button>
                </div>
              @elseif($case->adminCot->prelim_done && !$case->adminCot->final_done)
                <!-- Admin COT Stage 2: Final Scheduling -->
                @php
                  $routeDone = $vaActive ? $case->cs->done : ($case->kasir->done && $case->adru->done);
                @endphp

                @if($routeDone)
                  <h4>Penetapan Jadwal &amp; Kamar Operasi Final</h4>
                  <div class="form-grid">
                    <div class="field"><label class="req">Tanggal Operasi</label><input type="date" id="adminTgl" value="{{ $case->tanggal_pilihan1 ? $case->tanggal_pilihan1->format('Y-m-d') : '' }}"></div>
                    <div class="field"><label class="req">Jam Operasi</label><input type="time" id="adminJam" value="{{ $case->jam_operasi ?: '' }}"></div>
                    <div class="field"><label class="req">Ruang/Kamar Operasi</label>
                      <select id="adminRuang" class="form-control">
                        <option value="Kamar Operasi 1">Kamar Operasi 1</option>
                        <option value="Kamar Operasi 2">Kamar Operasi 2</option>
                        <option value="Kamar Operasi 3">Kamar Operasi 3</option>
                        <option value="Cathlab Room">Cathlab Room</option>
                        <option value="Endoscopy Room">Endoscopy Room</option>
                      </select>
                    </div>
                  </div>
                  <div class="field" style="margin-top:12px; margin-bottom:12px;">
                    <label>Catatan Penjadwalan</label>
                    <textarea id="adminNote" style="width:100%;" placeholder="Catatan..."></textarea>
                  </div>
                  <div class="btn-row">
                    <button type="button" class="btn btn-primary" id="adminFinalBtn">Tetapkan Jadwal Final</button>
                    <button type="button" class="btn" id="adminConfirmBtn">Tandai Dalam Konfirmasi</button>
                    <button type="button" class="btn btn-danger" id="adminRevisiBtn">Ajukan Revisi Estimasi</button>
                    <button type="button" class="btn" id="adminRescheduleBtn">Reschedule Jadwal</button>
                  </div>
                @else
                  <div class="locked-note">Kebutuhan alat (prelim) selesai diverifikasi. Menunggu persetujuan administrasi/pasien selesai sebelum menjadwalkan kamar operasi.</div>
                @endif
              @else
                <div class="locked-note">Proses penjadwalan kamar operasi final telah selesai.</div>
              @endif
            @endif

          @endif
        </div>
      </div>
    </div>

    <!-- Riwayat / Audit Log -->
    <div class="card">
      <h3>Riwayat / Audit Log</h3>
      <ul class="audit-list">
        @forelse($case->audit as $a)
          <li class="audit-item">
            <div class="when">{{ $a->created_at->format('d M Y H:i:s') }}</div>
            <div class="who">{{ $a->actor }}</div>
            <div class="what">{{ $a->action }}</div>
            @if($a->note)
              <div class="note">{{ $a->note }}</div>
            @endif
          </li>
        @empty
          <span class="footer-hint">Belum ada aktivitas.</span>
        @endforelse
      </ul>
    </div>
  </div>
@endsection

@section('scripts')
  <script>
    // Embed the estimasi table from COT_DB
    const ESTIMASI_DB = {
      "KECIL": {
        "Sewa kamar Operasi": {"k3": 2076000, "k2": 2906400, "k1": 3321600, "vip": 4152000, "vvip": 4671000},
        "Dokter Operator I": {"k3": 3133895, "k2": 4387280, "k1": 5013540, "vip": 6266925, "vvip": 7050615},
        "Dokter Operator II": {"k3": 940255, "k2": 1316530, "k1": 1504235, "vip": 1880510, "vvip": 2115790},
        "Dokter Anestesi": {"k3": 1096820, "k2": 1536240, "k1": 1755085, "vip": 2193640, "vvip": 2467845},
        "Jasa Layanan Operasi": {"k3": 700650, "k2": 980045, "k1": 1120175, "vip": 1400435, "vvip": 1575165}
      },
      "SEDANG": {
        "Sewa kamar Operasi": {"k3": 2736000, "k2": 3830400, "k1": 4377600, "vip": 5472000, "vvip": 6156000},
        "Dokter Operator I": {"k3": 4443195, "k2": 6220475, "k1": 7109110, "vip": 8886390, "vvip": 10004735},
        "Dokter Operator II": {"k3": 1332960, "k2": 1866145, "k1": 2132735, "vip": 2665915, "vvip": 3001420},
        "Dokter Anestesi": {"k3": 1555120, "k2": 2177165, "k1": 2488190, "vip": 3110235, "vvip": 3501655},
        "Jasa Layanan Operasi": {"k3": 966000, "k2": 1352400, "k1": 1545600, "vip": 1932000, "vvip": 2173500}
      },
      "BESAR": {
        "Sewa kamar Operasi": {"k3": 3532000, "k2": 4944800, "k1": 5651200, "vip": 7064000, "vvip": 7947000},
        "Dokter Operator I": {"k3": 6902260, "k2": 9663165, "k1": 11043615, "vip": 13804520, "vvip": 15530085},
        "Dokter Operator II": {"k3": 2070680, "k2": 2898950, "k1": 3313085, "vip": 4141355, "vvip": 4659025},
        "Dokter Anestesi": {"k3": 2415790, "k2": 3382110, "k1": 3865265, "vip": 4831580, "vvip": 5435530},
        "Jasa Layanan Operasi": {"k3": 1265000, "k2": 1771000, "k1": 2024000, "vip": 2530000, "vvip": 2846250}
      },
      "KHUSUS A": {
        "Sewa kamar Operasi": {"k3": 5120000, "k2": 7168000, "k1": 8192000, "vip": 10240000, "vvip": 11520000},
        "Dokter Operator I": {"k3": 10928230, "k2": 15299520, "k1": 17485165, "vip": 21856455, "vvip": 24588515},
        "Dokter Operator II": {"k3": 3278470, "k2": 4589855, "k1": 5245550, "vip": 6556935, "vvip": 7376555},
        "Dokter Anestesi": {"k3": 3824880, "k2": 5354830, "k1": 6119810, "vip": 7649760, "vvip": 8605980},
        "Jasa Layanan Operasi": {"k3": 1840000, "k2": 2576000, "k1": 2944000, "vip": 3680000, "vvip": 4140000}
      },
      "KHUSUS B": {
        "Sewa kamar Operasi": {"k3": 6224000, "k2": 8713600, "k1": 9958400, "vip": 12448000, "vvip": 14004000},
        "Dokter Operator I": {"k3": 13919860, "k2": 19487805, "k1": 22271775, "vip": 27839720, "vvip": 31319685},
        "Dokter Operator II": {"k3": 4175960, "k2": 5846340, "k1": 6681535, "vip": 8351915, "vvip": 9395905},
        "Dokter Anestesi": {"k3": 4871950, "k2": 6820730, "k1": 7795120, "vip": 9743900, "vvip": 10961890},
        "Jasa Layanan Operasi": {"k3": 2185000, "k2": 3059000, "k1": 3496000, "vip": 4370000, "vvip": 4916250}
      },
      "KHUSUS C": {
        "Sewa kamar Operasi": {"k3": 7560000, "k2": 10584000, "k1": 12096000, "vip": 15120000, "vvip": 17010000},
        "Dokter Operator I": {"k3": 18239850, "k2": 25535790, "k1": 29183760, "vip": 36479700, "vvip": 41039665},
        "Dokter Operator II": {"k3": 5471955, "k2": 7660735, "k1": 8755130, "vip": 10943910, "vvip": 12311900},
        "Dokter Anestesi": {"k3": 6383950, "k2": 8937525, "k1": 10214315, "vip": 12767895, "vvip": 14363880},
        "Jasa Layanan Operasi": {"k3": 2645000, "k2": 3703000, "k1": 4232000, "vip": 5290000, "vvip": 5951250}
      }
    };

    // Helper mapping for class codes used in DB keys
    const classMapping = {
      "Kelas 3": "k3",
      "Kelas 2": "k2",
      "Kelas 1": "k1",
      "VIP": "vip",
      "VVIP": "vvip"
    };

    // Initialize VA Jasa Medis Estimasi form
    function initVaEstimasi() {
      const golSelect = document.getElementById("vaGolongan");
      const kelasSelect = document.getElementById("vaKelas");
      const box = document.getElementById("vaEstimasiBox");
      if (!box || !golSelect || !kelasSelect) return;

      function render() {
        const gol = golSelect.value;
        const kelas = classMapping[kelasSelect.value] || "k3";
        const rates = ESTIMASI_DB[gol];

        if (!rates) {
          box.innerHTML = `
            <div class="autofill-box">
              <span class="hint">Estimasi manual untuk golongan non-standar:</span>
              <div class="field" style="margin-top:8px;">
                <label>Total Estimasi (Rp)</label>
                <input id="vaTotalManual" class="form-control" type="number" style="width:100%; text-align:right;">
              </div>
            </div>`;
          return;
        }

        let rowsHtml = "";
        let total = 0;
        Object.keys(rates).forEach((komp, idx) => {
          const val = rates[komp][kelas] || 0;
          total += val;
          rowsHtml += `
            <tr>
              <td>${komp}</td>
              <td style="text-align:right;">
                <input class="vaKomp" data-i="${idx}" data-komp="${komp}" type="number" value="${val}" style="width:140px; text-align:right; padding:4px 6px;">
              </td>
            </tr>`;
        });

        box.innerHTML = `
          <div class="autofill-box">
            <table class="af-table">
              <tr style="font-weight:700;"><td>Komponen Jasa Medis</td><td style="text-align:right;">Nilai (Rp)</td></tr>
              ${rowsHtml}
              <tr style="font-weight:800;"><td>TOTAL</td><td style="text-align:right;" id="vaTotalCell">${rupiah(total)}</td></tr>
            </table>
            <span class="hint">Nilai Jasa Medis bawaan SK RSUI. Dapat disunting jika diperlukan.</span>
          </div>`;

        box.querySelectorAll(".vaKomp").forEach(inp => {
          inp.addEventListener("input", function() {
            let t = 0;
            box.querySelectorAll(".vaKomp").forEach(x => t += (Number(x.value) || 0));
            document.getElementById("vaTotalCell").textContent = rupiah(t);
          });
        });
      }

      golSelect.addEventListener("change", render);
      kelasSelect.addEventListener("change", render);
      render();
    }

    // Call VA Form Init if element present
    initVaEstimasi();

    // General AJAX submission helper
    function submitAction(routeUrl, payload, message = "Aksi berhasil diproses") {
      fetch(routeUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          toast(message, "success");
          setTimeout(() => window.location.reload(), 1200);
        } else {
          toast(data.message, "error");
        }
      })
      .catch(err => toast("Terjadi kesalahan sistem", "error"));
    }

    // --- Button Event Handlers ---

    // Nurse buttons
    const submitBtn = document.getElementById("submitBtn");
    if (submitBtn) {
      submitBtn.onclick = () => submitAction('{{ route("cases.submit", $case->id) }}', {}, "Kasus diajukan ke Workflow Engine!");
    }
    const cancelBtn = document.getElementById("cancelBtn");
    if (cancelBtn) {
      cancelBtn.onclick = () => {
        const note = prompt("Masukkan alasan pembatalan:");
        if (note !== null) {
          submitAction('{{ route("cases.cancel", $case->id) }}', { note: note }, "Kasus telah dibatalkan");
        }
      };
    }

    // VA buttons
    const vaAjukanBtn = document.getElementById("vaAjukanBtn");
    if (vaAjukanBtn) {
      vaAjukanBtn.onclick = () => {
        const manual = document.getElementById("vaTotalManual");
        let rincian = [];
        let total = 0;
        if (manual) {
          total = Number(manual.value) || 0;
          rincian.push({ komponen: "Estimasi Manual", nilai: total });
        } else {
          const box = document.getElementById("vaEstimasiBox");
          box.querySelectorAll(".vaKomp").forEach(x => {
            const v = Number(x.value) || 0;
            rincian.push({ komponen: x.dataset.komp, nilai: v });
            total += v;
          });
        }
        const note = document.getElementById("vaNote").value;
        const gol = document.getElementById("vaGolongan").value;
        const kelas = document.getElementById("vaKelas").value;

        submitAction('{{ route("cases.va", $case->id) }}', {
          action: 'ajukan1',
          golongan: gol,
          kelas: kelas,
          rincian: rincian,
          total: total,
          note: note
        }, "Estimasi biaya diajukan ke Case Manager!");
      };
    }

    const vaRevisiBtn = document.getElementById("vaRevisiBtn");
    if (vaRevisiBtn) {
      vaRevisiBtn.onclick = () => {
        const note = prompt("Catatan revisi untuk Nurse:");
        if (note !== null) {
          submitAction('{{ route("cases.va", $case->id) }}', { action: 'revisi1', note: note }, "Permintaan revisi dikirim ke Nurse");
        }
      };
    }

    // VA stage 2 buttons
    const vaMulaiBtn = document.getElementById("vaMulaiBtn");
    if (vaMulaiBtn) {
      vaMulaiBtn.onclick = () => {
        const note = document.getElementById("vaFinalNote").value;
        submitAction('{{ route("cases.va", $case->id) }}', { action: 'mulai', note: note }, "Verifikasi asuransi dimulai");
      };
    }
    const vaBelumLengkapBtn = document.getElementById("vaBelumLengkapBtn");
    if (vaBelumLengkapBtn) {
      vaBelumLengkapBtn.onclick = () => {
        const note = document.getElementById("vaFinalNote").value;
        submitAction('{{ route("cases.va", $case->id) }}', { action: 'berkasBelumLengkap', note: note }, "Berkas ditandai belum lengkap");
      };
    }
    const vaSetujuBtn = document.getElementById("vaSetujuBtn");
    if (vaSetujuBtn) {
      vaSetujuBtn.onclick = () => {
        const note = document.getElementById("vaFinalNote").value;
        submitAction('{{ route("cases.va", $case->id) }}', { action: 'disetujui', note: note }, "Jaminan Asuransi disetujui!");
      };
    }
    const vaPendingBtn = document.getElementById("vaPendingBtn");
    if (vaPendingBtn) {
      vaPendingBtn.onclick = () => {
        const note = document.getElementById("vaFinalNote").value;
        submitAction('{{ route("cases.va", $case->id) }}', { action: 'pending', note: note }, "Status diubah menjadi Pending");
      };
    }
    const vaTolakBtn = document.getElementById("vaTolakBtn");
    if (vaTolakBtn) {
      vaTolakBtn.onclick = () => {
        const note = document.getElementById("vaFinalNote").value;
        submitAction('{{ route("cases.va", $case->id) }}', { action: 'ditolak', note: note }, "Pengajuan asuransi Ditolak");
      };
    }

    // Kasir buttons
    const kasirMulaiBtn = document.getElementById("kasirMulaiBtn");
    if (kasirMulaiBtn) {
      kasirMulaiBtn.onclick = () => {
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'mulai' }, "Proses administrasi awal dimulai");
      };
    }
    const kasirSelesai1Btn = document.getElementById("kasirSelesai1Btn");
    if (kasirSelesai1Btn) {
      kasirSelesai1Btn.onclick = () => {
        const note = document.getElementById("kasirNote").value;
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'selesai1', note: note }, "Administrasi awal diselesaikan");
      };
    }
    const kasirRevisiBtn = document.getElementById("kasirRevisiBtn");
    if (kasirRevisiBtn) {
      kasirRevisiBtn.onclick = () => {
        const note = document.getElementById("kasirNote").value;
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'revisi1', note: note }, "Permintaan revisi dikirim");
      };
    }
    const kasirSelesai2Btn = document.getElementById("kasirSelesai2Btn");
    if (kasirSelesai2Btn) {
      kasirSelesai2Btn.onclick = () => {
        const note = document.getElementById("kasirFinalNote").value;
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'selesai2', note: note }, "Administrasi akhir diselesaikan!");
      };
    }

    // ADRU COT buttons
    const adruMulaiBtn = document.getElementById("adruMulaiBtn");
    if (adruMulaiBtn) {
      adruMulaiBtn.onclick = () => {
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'mulai' }, "ADRU mulai proses estimasi");
      };
    }
    const adruAjukanBtn = document.getElementById("adruAjukanBtn");
    if (adruAjukanBtn) {
      adruAjukanBtn.onclick = () => {
        const est = document.getElementById("adruEstimasi").value;
        const note = document.getElementById("adruNote").value;
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'ajukan1', estimasi: est, note: note }, "Estimasi diajukan ke Case Manager!");
      };
    }
    const adruRevisiBtn = document.getElementById("adruRevisiBtn");
    if (adruRevisiBtn) {
      adruRevisiBtn.onclick = () => {
        const note = document.getElementById("adruNote").value;
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'revisi1', note: note }, "Revisi dikirim ke Nurse");
      };
    }
    const adruSetujuBtn = document.getElementById("adruSetujuBtn");
    if (adruSetujuBtn) {
      adruSetujuBtn.onclick = () => {
        const note = document.getElementById("adruConfirmNote").value;
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'konfirmasi2', note: note }, "Persetujuan pasien disubmit!");
      };
    }

    // Farmasi buttons
    const farmasiMulaiBtn = document.getElementById("farmasiMulaiBtn");
    if (farmasiMulaiBtn) {
      farmasiMulaiBtn.onclick = () => {
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'mulai' }, "Farmasi mulai me-review");
      };
    }
    const farmasiSetujuBtn = document.getElementById("farmasiSetujuBtn");
    if (farmasiSetujuBtn) {
      farmasiSetujuBtn.onclick = () => {
        const note = document.getElementById("farmasiNote").value;
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'setuju', note: note }, "Farmasi menyetujui obat & BMHP!");
      };
    }
    const farmasiRevisiBtn = document.getElementById("farmasiRevisiBtn");
    if (farmasiRevisiBtn) {
      farmasiRevisiBtn.onclick = () => {
        const note = document.getElementById("farmasiNote").value;
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'revisi', note: note }, "Permintaan revisi dikirim");
      };
    }

    // Case Manager buttons
    const cmSetujuBtn = document.getElementById("cmSetujuBtn");
    if (cmSetujuBtn) {
      cmSetujuBtn.onclick = () => {
        const note = document.getElementById("cmNote").value;
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'setuju', note: note }, "Dokumen disetujui Case Manager!");
      };
    }
    const cmRevisiBtn = document.getElementById("cmRevisiBtn");
    if (cmRevisiBtn) {
      cmRevisiBtn.onclick = () => {
        const note = document.getElementById("cmNote").value;
        const target = document.getElementById("cmReturnTo").value;
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'revisi', returnTo: target, note: note }, "Revisi dikirim ke " + target);
      };
    }
    const cmBelumLengkapBtn = document.getElementById("cmBelumLengkapBtn");
    if (cmBelumLengkapBtn) {
      cmBelumLengkapBtn.onclick = () => {
        const note = document.getElementById("cmNote").value;
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'dokbelumlengkap', note: note }, "Status berkas ditandai belum lengkap");
      };
    }

    // CS buttons
    const csHubungiBtn = document.getElementById("csHubungiBtn");
    if (csHubungiBtn) {
      csHubungiBtn.onclick = () => {
        const note = document.getElementById("csNote").value;
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'hubungi', note: note }, "Pasien dihubungi");
      };
    }
    const csSetujuBtn = document.getElementById("csSetujuBtn");
    if (csSetujuBtn) {
      csSetujuBtn.onclick = () => {
        const note = document.getElementById("csNote").value;
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'disetujui', note: note }, "Pasien menyetujui tindakan!");
      };
    }
    const csRescheduleBtn = document.getElementById("csRescheduleBtn");
    if (csRescheduleBtn) {
      csRescheduleBtn.onclick = () => {
        const note = document.getElementById("csNote").value;
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'reschedule', note: note }, "CS mensubmit permintaan reschedule");
      };
    }
    const csBatalBtn = document.getElementById("csBatalBtn");
    if (csBatalBtn) {
      csBatalBtn.onclick = () => {
        const note = document.getElementById("csNote").value;
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'batal', note: note }, "Pasien membatalkan tindakan");
      };
    }
    const csKonfirmasiBtn = document.getElementById("csKonfirmasiBtn");
    if (csKonfirmasiBtn) {
      csKonfirmasiBtn.onclick = () => {
        const note = document.getElementById("csNote").value;
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'dalamKonfirmasi', note: note }, "Keberatan diteruskan ke Case Manager");
      };
    }

    // Admin COT buttons
    const adminPrelimBtn = document.getElementById("adminPrelimBtn");
    if (adminPrelimBtn) {
      adminPrelimBtn.onclick = () => {
        const alatVal = document.getElementById("adminAlat").value;
        const list = alatVal.split(",").map(x => x.trim()).filter(Boolean);
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'prelim', alat: list }, "Prelim alat khusus disimpan!");
      };
    }
    const adminFinalBtn = document.getElementById("adminFinalBtn");
    if (adminFinalBtn) {
      adminFinalBtn.onclick = () => {
        const tgl = document.getElementById("adminTgl").value;
        const jam = document.getElementById("adminJam").value;
        const ruang = document.getElementById("adminRuang").value;
        const note = document.getElementById("adminNote").value;

        if (!tgl || !jam || !ruang) {
          toast("Tanggal, Jam, dan Ruang wajib diisi!", "error");
          return;
        }

        submitAction('{{ route("cases.admin-cot", $case->id) }}', {
          action: 'final',
          tanggal: tgl,
          jam: jam,
          ruang: ruang,
          note: note
        }, "Jadwal final operasi berhasil ditetapkan!");
      };
    }
    const adminConfirmBtn = document.getElementById("adminConfirmBtn");
    if (adminConfirmBtn) {
      adminConfirmBtn.onclick = () => {
        const note = document.getElementById("adminNote").value;
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'dalamKonfirmasi', note: note }, "Jadwal ditandai Dalam Konfirmasi");
      };
    }
    const adminRevisiBtn = document.getElementById("adminRevisiBtn");
    if (adminRevisiBtn) {
      adminRevisiBtn.onclick = () => {
        const note = document.getElementById("adminNote").value;
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'revisi', note: note }, "Revisi diajukan ke Verifikator");
      };
    }
    const adminRescheduleBtn = document.getElementById("adminRescheduleBtn");
    if (adminRescheduleBtn) {
      adminRescheduleBtn.onclick = () => {
        const tgl = document.getElementById("adminTgl").value;
        const jam = document.getElementById("adminJam").value;
        const ruang = document.getElementById("adminRuang").value;
        const note = document.getElementById("adminNote").value;

        if (!tgl || !jam || !ruang) {
          toast("Tanggal, Jam, dan Ruang wajib diisi untuk reschedule!", "error");
          return;
        }

        submitAction('{{ route("cases.admin-cot", $case->id) }}', {
          action: 'reschedule',
          tanggal: tgl,
          jam: jam,
          ruang: ruang,
          note: note
        }, "Jadwal berhasil di-reschedule!");
      };
    }
  </script>
@endsection
