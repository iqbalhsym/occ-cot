@extends('layouts.app')

@section('title', 'Hospital Action Interface Care - Detail Case ' . $case->id)
@section('page_title', 'Detail Case ' . $case->id)

@section('content')
  @php
    $currentUser = Auth::user();
    $activeRole = session('role', $currentUser ? $currentUser->role : 'Viewer');
    $isViewer = ($activeRole === 'Viewer');
    // Make $rolesList available locally for action panel header
    $rolesList = [
      ['id' => 'Nurse',      'label' => 'Nurse (Entry Point)'],
      ['id' => 'VA',         'label' => 'VA (Asuransi)'],
      ['id' => 'Kasir',      'label' => 'Kasir (Umum)'],
      ['id' => 'ADRUCOT',    'label' => 'ADRU COT (Umum)'],
      ['id' => 'Farmasi',    'label' => 'Farmasi'],
      ['id' => 'AdminCOT',   'label' => 'Admin COT'],
      ['id' => 'CaseManager','label' => 'Case Manager'],
      ['id' => 'CS',         'label' => 'Customer Service'],
      ['id' => 'Viewer',     'label' => 'Viewer (Hanya Lihat)'],
    ];

    // Parse raw data JSON
    $rawData = json_decode($case->raw_data, true) ?: [];
    
    $alatFromRaw = [];
    if (isset($rawData['adminCot']['alat'])) {
        foreach ($rawData['adminCot']['alat'] as $a) {
            $alatFromRaw[] = [
                'nama' => $a['nama'] ?? '',
                'harga' => $a['harga'] ?? 0,
                'flag' => $a['flag'] ?? 'Hijau'
            ];
        }
    }
    
    $tambahanFromRaw = [];
    if (isset($rawData['tambahanBMHP'])) {
        foreach ($rawData['tambahanBMHP'] as $t) {
            $tambahanFromRaw[] = [
                'nama' => $t['jenis'] ?? $t['nama'] ?? '',
                'qty' => $t['qty'] ?? 1,
                'harga' => $t['harga'] ?? 0,
                'flag' => $t['flag'] ?? 'Hijau'
            ];
        }
    }

    // Calculate total prices for tools and BMHP
    $totalAlat = 0;
    foreach($alatFromRaw as $a) {
        $totalAlat += $a['harga'];
    }
    
    $totalBmhpPaket = 0;
    foreach($case->tambahanBmhp as $t) {
        if ($t->jenis === 'paket') {
            $totalBmhpPaket += ($t->qty ?: 1) * ($t->harga ?: 0);
        }
    }
    
    $totalBmhpTambahan = 0;
    foreach($tambahanFromRaw as $t) {
        $totalBmhpTambahan += $t['qty'] * $t['harga'];
    }
    $totalBmhp = $totalBmhpPaket + $totalBmhpTambahan;
    
    // Evaluate workflow stages matching JKN simplified workflow
    $statusMap = [
        'Nurse' => 'done',
        'Farmasi' => 'next',
        'Admin' => 'next',
        'CM' => 'next',
        'Selesai' => 'next'
    ];

    if ($case->status === 'Draft') {
        $statusMap['Nurse'] = 'active';
    } elseif ($case->status === 'Returned') {
        $statusMap['Nurse'] = 'returned';
    } else {
        $statusMap['Nurse'] = 'done';
    }

    // Farmasi stage
    if ($case->status === 'Draft') {
        $statusMap['Farmasi'] = 'next';
    } else {
        $statusMap['Farmasi'] = $case->farmasi->done ? 'done' : 'active';
    }

    // Admin COT stage
    if ($case->status === 'Draft') {
        $statusMap['Admin'] = 'next';
    } else {
        $statusMap['Admin'] = $case->adminCot->final_done ? 'done' : 'active';
    }

    // CM stage
    if ($case->status === 'Draft') {
        $statusMap['CM'] = 'next';
    } else {
        $statusMap['CM'] = $case->caseManager->done ? 'done' : (($case->farmasi->done && $case->adminCot->final_done) ? 'active' : 'next');
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
      <h2>{{ $case->id }} - {{ $case->nama }}</h2>
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

  @if($case->va && $case->va->berkas_belum_lengkap)
    <div style="background:#FEE2E2; border-left:4px solid #EF4444; color:#991B1B; padding:12px 16px; border-radius:6px; margin-bottom:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
      <span>[!]</span>
      <span>Dokumen Kelengkapan Asuransi Ditandai BELUM LENGKAP oleh VA. Silakan lengkapi berkas dan unggah ulang.</span>
    </div>
  @endif

  <!-- Stepper Timeline -->
  <div class="card">
    <h3>Progres Alur Kerja</h3>
    <div class="stepper">
      <div class="step {{ $statusMap['Nurse'] }}">Nurse (Awal)</div>
      <div class="step {{ $statusMap['Farmasi'] }}">Farmasi</div>
      <div class="step {{ $statusMap['Admin'] }}">Admin COT</div>
      <div class="step {{ $statusMap['CM'] }}">Case Manager</div>
      <div class="step {{ $statusMap['Selesai'] }}">Selesai</div>
    </div>
  </div>

  <!-- Unit Status Grid -->
  <div class="card">
    <h3>Status Tiap Unit</h3>
    <div class="unit-status-grid">
      <div class="unit-status-card">
        <div class="u-name">Farmasi</div>
        <span class="badge-status st-{{ $case->farmasi->done ? 'Approved' : 'Menunggu' }}">{{ $case->farmasi->done ? 'Approved' : 'Menunggu' }}</span>
      </div>

      <div class="unit-status-card">
        <div class="u-name">Admin COT</div>
        @if($case->adminCot->final_done)
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
        @if($case->expensive_flag)
          <div style="background:#FEE2E2; border:1px solid #EF4444; color:#991B1B; padding:6px 10px; border-radius:4px; margin-top:8px; font-size:11px; font-weight:700;">
            ⚠️ Terdapat item/alkes manual bernilai tinggi (flag merah)
          </div>
        @endif
      </div>
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
          <div><dt>Jadwal (Pilihan 1 / 2)</dt><dd>{{ $case->tanggal_pilihan1 ? $case->tanggal_pilihan1->format('d M Y') : '-' }} / {{ $case->tanggal_pilihan2 ? $case->tanggal_pilihan2->format('d M Y') : '-' }} {{ $case->jam_operasi ? ' - ' . $case->jam_operasi : '' }}</dd></div>
          <div><dt>Estimasi Lama Operasi</dt><dd>{{ $case->estimasi_lama_operasi ?: '-' }}</dd></div>
          <div><dt>Lokasi Tindakan</dt><dd>{{ $case->lokasi_tindakan === 'Lainnya' ? $case->lokasi_tindakan_lainnya : $case->lokasi_tindakan }}</dd></div>
          <div><dt>Asal Pasien</dt><dd>{{ $case->asal_pasien === 'Lainnya' ? $case->asal_pasien_lainnya : $case->asal_pasien }}</dd></div>
          <div><dt>Ruang Pasca Operasi</dt><dd>{{ $case->ruang_pasca_operasi === 'Lainnya' ? $case->ruang_pasca_operasi_lainnya : $case->ruang_pasca_operasi }}</dd></div>
          <div><dt>Estimasi Rawat Inap</dt><dd>{{ $case->estimasi_rawat_inap ?: '-' }}</dd></div>
          <div><dt>Penjamin</dt><dd>{{ $case->penjamin }} {{ ($case->penjamin === 'Asuransi' && $case->nama_guarantor) ? ' - ' . $case->nama_guarantor : '' }}</dd></div>
          @if($case->penjamin === 'BPJS Kesehatan' || $case->penjamin === 'BPJS')
            <div><dt>Hak Kelas BPJS</dt><dd>{{ $case->hak_kelas ?: '-' }}</dd></div>
            <div><dt>Rujukan BPJS</dt><dd>{{ $case->rujukan_bpjs ?: '-' }}</dd></div>
          @else
            <div><dt>Kelas Perawatan</dt><dd>{{ $case->kelas_perawatan ?: '-' }}</dd></div>
          @endif
          <div style="grid-column: span 2;">
            <dt>Kebutuhan Pre-Op (Trigger Admin COT)</dt>
            <dd>
              <ul style="margin: 4px 0 0 0; padding-left: 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; font-size: 12.5px;">
                <li>Anestesi: <strong style="color: {{ $case->pre_op_anestesi === 'Ya' ? 'var(--red-600)' : 'inherit' }}">{{ $case->pre_op_anestesi ?: 'Tidak' }}</strong></li>
                <li>Laboratorium: <strong style="color: {{ $case->pre_op_lab === 'Ya' ? 'var(--red-600)' : 'inherit' }}">{{ $case->pre_op_lab ?: 'Tidak' }}</strong></li>
                <li>Radiologi: <strong style="color: {{ $case->pre_op_rad === 'Ya' ? 'var(--red-600)' : 'inherit' }}">{{ $case->pre_op_rad ?: 'Tidak' }}</strong></li>
                <li>Konsul Spesialis Lain: <strong style="color: {{ $case->pre_op_konsul === 'Ya' ? 'var(--red-600)' : 'inherit' }}">{{ $case->pre_op_konsul === 'Ya' ? 'Ya (' . $case->pre_op_konsul_detail . ')' : 'Tidak' }}</strong></li>
              </ul>
            </dd>
          </div>
          <div>
            <dt>Estimasi Biaya Jasa Medis</dt>
            <dd id="summaryJasaMedis">
              @if($case->va && $case->va->estimasi_total > 0)
                Rp {{ number_format($case->va->estimasi_total, 0, ',', '.') }}
              @elseif($case->adru && $case->adru->estimasi)
                {{ $case->adru->estimasi }}
              @else
                -
              @endif
            </dd>
          </div>
          <div>
            <dt>Estimasi Biaya Alat Khusus</dt>
            <dd id="summaryAlat">
              Rp {{ number_format($totalAlat, 0, ',', '.') }}
            </dd>
          </div>
          <div>
            <dt>Estimasi Biaya BMHP &amp; Obat</dt>
            <dd id="summaryBmhp">
              Rp {{ number_format($totalBmhp, 0, ',', '.') }}
            </dd>
          </div>
          <div>
            <dt>Estimasi Grand Total Biaya</dt>
            <dd>
              @php
                $jasaMedis = 0;
                if ($case->va && $case->va->estimasi_total > 0) {
                    $jasaMedis = $case->va->estimasi_total;
                } elseif ($case->adru && is_numeric(str_replace(['Rp', '.', ','], '', $case->adru->estimasi))) {
                    $jasaMedis = (float)str_replace(['Rp', '.', ','], '', $case->adru->estimasi);
                } elseif ($case->kasir && $case->kasir->total_estimasi > 0) {
                    $jasaMedis = $case->kasir->total_estimasi;
                }
                $grandTotal = $jasaMedis + $totalAlat + $totalBmhp;
              @endphp
              <strong id="summaryGrandTotal" style="color:var(--primary-600); font-size:16px;">Rp {{ number_format($grandTotal, 0, ',', '.') }}</strong>
            </dd>
          </div>
        </dl>

        @if($case->va && $case->va->estimasi_total > 0)
          <div class="section-lbl" style="margin-top:18px;">Rincian Jasa Medis (VA) - Golongan {{ $case->va->golongan }} / Kelas {{ $case->va->kelas }}</div>
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
            <a href="{{ route('cases.download-estimasi', $case->id) }}" target="_blank" class="btn btn-sm"> - Print Estimasi Biaya</a>
          </div>
        @endif
        @if($case->va && $case->va->attachments && count($case->va->attachments) > 0)
          <div class="section-lbl" style="margin-top:18px;">Lampiran Berkas Asuransi (VA)</div>
          <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
            @foreach($case->va->attachments as $att)
              <a href="{{ $att['path'] }}" download="{{ $att['name'] }}" class="chip" style="background:#E0F2FE; color:#0369A1; font-weight:600; text-decoration:none;">
                 {{ $att['name'] }}
              </a>
            @endforeach
          </div>
        @endif

        <div class="section-lbl" style="margin-top:18px;">Alat Khusus (Total: Rp {{ number_format($totalAlat, 0, ',', '.') }})</div>
        <div style="margin-top:6px;">
          @forelse($alatFromRaw as $a)
            @php
              $flag = $a['flag'] ?? 'Hijau';
              $bg = '#E2F3EA'; $fg = '#2E7D5B'; // Hijau
              if ($flag === 'Kuning') { $bg = '#FEF3C7'; $fg = '#B45309'; }
              elseif ($flag === 'Merah') { $bg = '#FEE2E2'; $fg = '#B91C1C'; }
            @endphp
            <span class="chip" style="background: {{ $bg }}; color: {{ $fg }}; font-weight: 700; border: 1px solid {{ $fg }}44;">
              @if($flag === 'Merah') 🚨 @elseif($flag === 'Kuning') ⚠️ @else 🟢 @endif
              {{ $a['nama'] }} (Rp {{ $isViewer ? '***' : number_format($a['harga'], 0, ',', '.') }})
            </span>
          @empty
            <span class="footer-hint">Belum ada</span>
          @endforelse
        </div>

        <div class="section-lbl" style="margin-top:18px;">Kebutuhan Obat &amp; BMHP (Total: Rp {{ number_format($totalBmhp, 0, ',', '.') }})</div>
        <div style="margin-top:6px;">
          <div style="font-size:12px; font-weight:600; color:var(--slate-500); margin-bottom:4px;">A. Paket BMHP (Total: Rp {{ number_format($totalBmhpPaket, 0, ',', '.') }})</div>
          @php $hasPaket = false; @endphp
          @foreach($case->tambahanBmhp as $t)
            @if($t->jenis === 'paket')
              @php $hasPaket = true; @endphp
              <span class="chip" style="background:#E0F2FE; color:#0369A1;">{{ $t->nama }} (x{{ $t->qty }} @ Rp {{ $isViewer ? '***' : number_format($t->harga ?: 0, 0, ',', '.') }})</span>
            @endif
          @endforeach
          @if(!$hasPaket)
            <span class="footer-hint" style="display:block; margin-bottom:10px;">Tidak ada paket BMHP.</span>
          @endif

          <div style="font-size:12px; font-weight:600; color:var(--slate-500); margin-top:8px; margin-bottom:4px;">B. Tambahan di Luar Paket (Total: Rp {{ number_format($totalBmhpTambahan, 0, ',', '.') }})</div>
          @forelse($tambahanFromRaw as $t)
            @php
              $flag = $t['flag'] ?? 'Hijau';
              $bg = '#E2F3EA'; $fg = '#2E7D5B'; // Hijau
              if ($flag === 'Kuning') { $bg = '#FEF3C7'; $fg = '#B45309'; }
              elseif ($flag === 'Merah') { $bg = '#FEE2E2'; $fg = '#B91C1C'; }
            @endphp
            <span class="chip" style="background: {{ $bg }}; color: {{ $fg }}; font-weight: 700; border: 1px solid {{ $fg }}44;">
              @if($flag === 'Merah') 🚨 @elseif($flag === 'Kuning') ⚠️ @else 🟢 @endif
              {{ $t['nama'] }} (x{{ $t['qty'] }} @ Rp {{ $isViewer ? '***' : number_format($t['harga'], 0, ',', '.') }})
            </span>
          @empty
            <span class="footer-hint" style="display:block;">Tidak ada tambahan BMHP.</span>
          @endforelse
        </div>

        @if($case->adminCot->final_done)
          <div class="section-lbl" style="margin-top:18px;">Jadwal Operasi Final (Admin COT)</div>
          <div style="font-weight:600; font-size:14px; margin-top:8px;">
            {{ $case->adminCot->tanggal_fix->format('d M Y') }} - {{ $case->adminCot->jam_fix }} - Ruang {{ $case->adminCot->kamar_operasi }}
          </div>
        @endif

        <!-- L. Dokumen Pengajuan Awal -->
        <div class="section-lbl" style="margin-top:18px;">L. Dokumen Pengajuan Awal</div>
        <div style="font-size:12.5px; color:var(--slate-500); line-height:1.4; margin-top:4px;">
          Unggah Formulir Penjadwalan Tindakan (telah diisi DPJP) beserta dokumen pendukung setelah data ini disimpan sebagai Draft — buka kembali Case ini dan gunakan panel Attachment Center pada halaman Detail. Minimal 1 dokumen wajib diunggah sebelum Submit Pengajuan.
        </div>

        <div style="margin-top:12px; border: 1px solid var(--slate-200); border-radius: 8px; padding: 12px; background: var(--slate-50);">
          <div style="font-weight:700; font-size:13px; color:var(--slate-800); margin-bottom:8px;">Attachment Center</div>
          
          <div id="attachmentsContainer" style="display:flex; flex-direction:column; gap:8px;">
            @php $attachments = $case->attachments; @endphp
            @forelse($attachments as $att)
              <div class="attachment-item" data-id="{{ $att['id'] }}" style="display:flex; justify-content:space-between; align-items:center; background:var(--white); border:1px solid var(--slate-200); padding:8px 12px; border-radius:6px;">
                <div style="display:flex; align-items:center; gap:8px;">
                  <span style="font-size:16px;">📄</span>
                  <a href="{{ $att['path'] }}" target="_blank" class="att-name-link" style="font-weight:600; font-size:12.5px; color:var(--primary-700); text-decoration:none; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ $att['name'] }}
                  </a>
                </div>
                <div style="display:flex; gap:6px;">
                  @php
                    $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
                    $isPreviewable = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'pdf']);
                  @endphp
                  @if($isPreviewable)
                    <button type="button" class="btn btn-sm btn-preview" onclick="previewAttachment('{{ $att['path'] }}', '{{ $att['name'] }}')" style="padding:4px 8px; font-size:11px;">Preview</button>
                  @endif
                  <a href="{{ $att['path'] }}" download="{{ $att['name'] }}" class="btn btn-sm" style="padding:4px 8px; font-size:11px; text-decoration:none; display:inline-block; line-height:1.2;">Download</a>
                  @if(($case->status === 'Draft' || $case->status === 'Returned') && $activeRole === 'Nurse')
                    <button type="button" class="btn btn-sm btn-danger btn-delete-att" onclick="deleteAttachment('{{ $att['id'] }}')" style="padding:4px 8px; font-size:11px;">Hapus</button>
                  @endif
                </div>
              </div>
            @empty
              <div id="noAttachmentsHint" style="font-size:12px; color:var(--slate-400); text-align:center; padding:12px 0;">Belum ada dokumen yang diunggah.</div>
            @endforelse
          </div>

          @if(($case->status === 'Draft' || $case->status === 'Returned') && $activeRole === 'Nurse')
            <div style="margin-top:12px; border-top:1px dashed var(--slate-200); padding-top:12px;">
              <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px; color:var(--slate-600);">Unggah Dokumen Baru (Maks 2 MB)</label>
              <input type="file" id="attachmentFileInput" class="form-control" style="background:var(--white); font-size:12px;">
              <span class="hint" style="display:block; margin-top:2px;">Format didukung: PDF, Gambar (PNG, JPG, JPEG, GIF)</span>
            </div>
          @endif
        </div>
      </div>

      <!-- Actions Panel -->
      <div class="card" id="actionCard">
        <h3>Aksi Role: {{ $rolesList[array_search($activeRole, array_column($rolesList, 'id'))]['label'] }}</h3>
        <div id="actionArea">
          @if($isViewer)
            <div class="locked-note">Mode <strong>Viewer</strong> - hanya melihat. Anda tidak dapat melakukan aksi.</div>
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
            @if($activeRole === 'VA')
              @if(!$vaActive)
                <div style="opacity:0.6;pointer-events:none;background:var(--slate-100);border:1px solid var(--slate-300);padding:15px;border-radius:8px;margin-bottom:15px;">
                  <div style="font-weight:700;color:var(--slate-600);margin-bottom:10px;"> Non-aktif (Penjamin = Umum)</div>
                  <div style="font-size:13px;color:var(--slate-500);margin-bottom:10px;">Form Verifikasi Asuransi hanya dapat diisi jika Penjamin Kasus adalah Asuransi.</div>
                  <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="field"><label>Golongan Tindakan</label><select class="form-control" disabled><option>KECIL</option></select></div>
                    <div class="field"><label>Kelas Perawatan</label><select class="form-control" disabled><option>Kelas 3</option></select></div>
                  </div>
                </div>
              @else
                {{-- Rincian biaya unit lain --}}
                <div class="user-context-badge" style="background:#EBF5FB;border-color:#AED6F1;color:#1B4F72;margin-bottom:12px;display:flex;flex-direction:column;gap:4px;align-items:flex-start;padding:10px 14px;">
                  <div style="font-weight:700;font-size:13px;"> - Rincian Biaya dari Unit Lain:</div>
                  <div style="font-size:12px;display:flex;gap:12px;flex-wrap:wrap;margin-top:2px;">
                    <span><strong>Total Obat/BMHP:</strong> Rp {{ number_format($totalBmhp, 0, ',', '.') }}</span>
                    <span><strong>Total Alat Khusus:</strong> Rp {{ number_format($totalAlat, 0, ',', '.') }}</span>
                  </div>
                </div>

                {{-- Upload berkas asuransi --}}
                <div class="field" style="margin-bottom:15px;background:var(--white);border:1px dashed var(--slate-300);padding:12px 16px;border-radius:8px;">
                  <label style="font-weight:700;display:flex;align-items:center;gap:6px;"> Lampiran Berkas Asuransi (LMA, CL, dll)</label>
                  <input type="file" id="vaFile" multiple class="form-control" style="background:var(--white);margin-top:6px;">
                  <div id="vaFileList" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    @if($case->va && $case->va->attachments && count($case->va->attachments) > 0)
                      @foreach($case->va->attachments as $att)
                        <a href="{{ $att['path'] }}" download="{{ $att['name'] }}" class="chip" style="background:#E0F2FE;color:#0369A1;text-decoration:none;font-weight:600;padding:4px 10px;border-radius:15px;border:1px solid #B9E6FE;">
                           {{ $att['name'] }} (Unduh)
                        </a>
                      @endforeach
                    @else
                      <span class="hint" style="color:var(--slate-400);">Belum ada berkas yang diunggah.</span>
                    @endif
                  </div>
                  <span class="hint" style="display:block;margin-top:4px;">Anda dapat memilih beberapa berkas sekaligus.</span>
                </div>

                @if(!$case->va->done)
                  {{-- VA Stage 1: Estimasi biaya --}}
                  @if($estimasiLocked)
                    <div class="locked-note" style="background:#FEF3C7;color:#B45309;border-color:#FDE68A;">
                      - Estimasi sudah diajukan ke Case Manager - menunggu respon.
                      <div style="margin-top:6px;font-size:12px;">Status Case Manager: <strong>{{ $case->caseManager->decision ?: 'Menunggu' }}</strong></div>
                    </div>
                  @else
                    <h4>Penyusunan Estimasi Biaya (VA Stage 1)</h4>
                    <div class="permission-note" style="margin-bottom:12px;">
                      Estimasi biaya ditarik otomatis dari database berdasarkan Golongan &amp; Kelas. VA dapat merevisi Golongan, Kelas, dan komponen Jasa Medis secara penuh.
                    </div>
                    <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                      <div class="field">
                        <label>Golongan Tindakan</label>
                        <select id="vaGolongan" class="form-control">
                          @foreach(['KECIL','SEDANG','BESAR','KHUSUS A','KHUSUS B','KHUSUS C','CATHLAB RINGAN','CATHLAB SEDANG','CATHLAB BERAT','CATHLAB KHUSUS A','CATHLAB KHUSUS B','BEDAH JANTUNG','NON GOLONGAN'] as $gol)
                            <option value="{{ $gol }}" {{ ($case->va->golongan ?: $case->golongan) === $gol ? 'selected' : '' }}>{{ $gol }}</option>
                          @endforeach
                        </select>
                      </div>
                      <div class="field">
                        <label>Kelas Perawatan</label>
                        <select id="vaKelas" class="form-control">
                          @foreach(['Kelas 3','Kelas 2','Kelas 1','VIP','VVIP'] as $k)
                            <option value="{{ $k }}" {{ ($case->va->kelas ?: $case->kelas_perawatan) === $k ? 'selected' : '' }}>{{ $k }}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    <div id="vaEstimasiBox" style="margin-bottom:15px;"></div>
                    <div class="field" style="margin-bottom:12px;">
                      <label>Catatan Verifikasi</label>
                      <textarea id="vaNote" style="width:100%;" placeholder="Catatan estimasi biaya...">{{ $case->va->decision_note ?: '' }}</textarea>
                    </div>
                    <div class="btn-row" style="margin-bottom:12px;">
                      <button type="button" class="btn btn-primary" id="vaAjukanBtn">
                        {{ $case->va->stage1_done ? ' - Perbarui Estimasi ke Case Manager' : 'Ajukan Estimasi ke Case Manager' }}
                      </button>
                    </div>
                    <div style="border-top:1px solid var(--slate-200);padding-top:12px;margin-top:4px;">
                      <label style="font-size:12px;font-weight:700;color:var(--slate-500);display:block;margin-bottom:6px;">Jika perlu revisi - kembalikan ke unit:</label>
                      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select id="vaReturnTo" class="form-control" style="width:auto;flex:1;max-width:260px;">
                          <option value="Nurse">Nurse (data awal)</option>
                        </select>
                        <button type="button" class="btn btn-danger" id="vaRevisiBtn">Kembalikan untuk Revisi</button>
                      </div>
                    </div>
                  @endif

                  {{-- VA Stage 2: Keputusan Asuransi (setelah CM setujui) --}}
                  @if($case->caseManager->done)
                    <div style="border-top:2px solid var(--primary-200);padding-top:16px;margin-top:16px;">
                      <h4>Verifikasi &amp; Keputusan Asuransi (VA Stage 2)</h4>
                      <div class="permission-note" style="margin-bottom:12px;">
                        Case Manager telah menyetujui. Berikan keputusan status jaminan asuransi pasien.
                      </div>
                      <div class="field" style="margin-bottom:12px;">
                        <label>Keterangan Keputusan Asuransi <span class="hint">(wajib diisi)</span></label>
                        <textarea id="vaNote2" style="width:100%;" placeholder="Keterangan keputusan/proses verifikasi...">{{ $case->va->decision_note ?: '' }}</textarea>
                      </div>
                      <div class="field" style="margin-bottom:12px;">
                        <label style="font-weight:700;">Checklist Kelengkapan Pemberkasan</label>
                        @php
                          $checklist = $case->va->checklist ?: [];
                          $checklistItems = ['Formulir Penjadwalan','Surat Pengantar DPJP','Hasil Laboratorium / Penunjang','Informed Consent (Persetujuan Medis)','Dokumen LMA','Dokumen CL'];
                        @endphp
                        <div style="display:grid;gap:8px;margin-top:8px;">
                          @foreach($checklistItems as $cli)
                            <label style="display:flex;align-items:center;gap:8px;font-weight:normal;cursor:pointer;">
                              <input type="checkbox" class="va-check" value="{{ $cli }}" {{ in_array($cli, $checklist) ? 'checked' : '' }}> {{ $cli }}
                            </label>
                          @endforeach
                        </div>
                      </div>
                      <div style="font-size:12px;font-weight:700;margin-bottom:8px;">Status: <span class="badge-status st-{{ $case->va->decision ?: 'Menunggu' }}">{{ $case->va->decision ?: 'Menunggu' }}</span></div>
                      <div class="btn-row">
                        <button type="button" class="btn btn-success" id="vaSetujuBtn">Disetujui (Acc Asuransi)</button>
                        <button type="button" class="btn" id="vaPendingBtn">Pending</button>
                        <button type="button" class="btn btn-danger" id="vaTolakBtn">Ditolak</button>
                        <button type="button" class="btn" id="vaDalamKonfBtn">Dalam Konfirmasi</button>
                      </div>
                      <div class="btn-row" style="margin-top:8px;">
                        <button type="button" class="btn btn-danger" id="vaBelumLengkapBtn">Tandai Berkas Belum Lengkap</button>
                        @if($case->va->berkas_belum_lengkap)
                          <button type="button" class="btn btn-success" id="vaLengkapBtn">Tandai Berkas Sudah Lengkap</button>
                        @endif
                      </div>
                      <div style="border-top:1px solid var(--slate-200);padding-top:12px;margin-top:12px;">
                        <label style="font-size:12px;font-weight:700;color:var(--slate-500);display:block;margin-bottom:6px;">Jika perlu revisi - kembalikan ke unit:</label>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                          <select id="vaReturnTo2" class="form-control" style="width:auto;flex:1;max-width:260px;">
                            <option value="Nurse">Nurse (data awal)</option>
                            <option value="CaseManager">Case Manager (keberatan)</option>
                          </select>
                          <button type="button" class="btn btn-danger" id="vaRevisiBtn2">Revisi</button>
                        </div>
                      </div>
                      <p class="footer-hint" style="margin-top:8px;">Disetujui  ->  diteruskan ke Customer Service untuk konfirmasi pasien.</p>
                    </div>
                  @endif
                @else
                  <div class="locked-note">Proses verifikasi asuransi telah selesai. Status: <strong>{{ $case->va->decision }}</strong></div>
                @endif
              @endif
            @endif

            <!-- - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - 
                 KASIR ACTION
            - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -->
            @if($activeRole === 'Kasir')
              @if($vaActive)
                <div style="opacity:0.6; background:var(--slate-100); border:1px solid var(--slate-300); padding:15px; border-radius:8px;">
                  <div style="font-weight:700; color:var(--slate-600); margin-bottom:8px;"> Non-aktif (Penjamin = Asuransi)</div>
                  <div style="font-size:13px; color:var(--slate-500);">Form Kasir hanya dapat diisi jika Penjamin Kasus adalah Umum.</div>
                </div>
              @elseif(!$case->kasir->done)
                <div class="user-context-badge" style="background:#EBF5FB; border-color:#AED6F1; color:#1B4F72; margin-bottom:12px; padding:10px 14px; display:flex; flex-direction:column; gap:4px;">
                  <div style="font-weight:700; font-size:13px;"> - Rincian Biaya dari Unit Lain:</div>
                  <div style="font-size:12px; display:flex; gap:12px; flex-wrap:wrap; margin-top:2px;">
                    <span><strong>BMHP:</strong> Rp {{ number_format($totalBmhp, 0, ',', '.') }}</span>
                    <span><strong>Alat:</strong> Rp {{ number_format($totalAlat, 0, ',', '.') }}</span>
                  </div>
                </div>

                @if(!$case->kasir->stage1_done)
                  <h4>Penyusunan &amp; Administrasi Kasir (Stage 1)</h4>
                  <div class="permission-note" style="margin-bottom:12px;">Estimasi biaya dapat dihitung otomatis dari database tarif, atau diedit manual sepenuhnya untuk tindakan non-golongan.</div>
                  <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="field">
                      <label>Golongan Tindakan</label>
                      <select id="kasirGolongan" class="form-control">
                        @foreach(['KECIL','SEDANG','BESAR','KHUSUS A','KHUSUS B','KHUSUS C','CATHLAB RINGAN','CATHLAB SEDANG','CATHLAB BERAT','CATHLAB KHUSUS A','CATHLAB KHUSUS B','BEDAH JANTUNG','NON GOLONGAN'] as $gol)
                          <option value="{{ $gol }}" {{ $case->golongan === $gol ? 'selected' : '' }}>{{ $gol }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="field">
                      <label>Kelas Perawatan</label>
                      <select id="kasirKelas" class="form-control">
                        @foreach(['Kelas 3','Kelas 2','Kelas 1','VIP','VVIP'] as $k)
                          <option value="{{ $k }}" {{ $case->kelas_perawatan === $k ? 'selected' : '' }}>{{ $k }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                  <div id="kasirEstimasiBox" style="margin-bottom:15px;"></div>
                  <div class="field" style="margin-bottom:12px;">
                    <label>Catatan Administrasi</label>
                    <textarea id="kasirNote" style="width:100%;" placeholder="Catatan administrasi kasir...">{{ $case->kasir->note ?: '' }}</textarea>
                  </div>
                  <div class="btn-row">
                    <button type="button" class="btn" id="kasirMulaiBtn">Mulai Administrasi</button>
                    <button type="button" class="btn btn-primary" id="kasirSelesai1Btn">Selesaikan &amp; Ajukan ke CM</button>
                  </div>
                  <div style="border-top:1px solid var(--slate-200); padding-top:12px; margin-top:12px;">
                    <label style="font-size:12px; font-weight:700; color:var(--slate-500); display:block; margin-bottom:6px;">Jika perlu revisi:</label>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <select id="kasirReturnTo" class="form-control" style="width:auto; flex:1; max-width:260px;">
                        <option value="Nurse">Nurse (data awal)</option>
                        <option value="Farmasi">Farmasi (paket BMHP)</option>
                      </select>
                      <button type="button" class="btn btn-danger" id="kasirRevisiBtn">Kembalikan untuk Revisi</button>
                    </div>
                  </div>
                @elseif(!$case->caseManager->done)
                  <div class="locked-note" style="background:#FEF3C7; color:#B45309; border-color:#FDE68A;">
                    [!] Estimasi kasir telah diajukan ke Case Manager. Menunggu review &amp; persetujuan CM.
                  </div>
                @else
                  <h4>Validasi Akhir Kasir (Stage 2)</h4>
                  <div class="permission-note" style="margin-bottom:12px;">Case Manager telah menyetujui. Kirim estimasi ke pasien &amp; konfirmasi kesediaan.</div>
                  <div style="font-size:12px; font-weight:700; margin-bottom:8px;">Status: <span class="badge-status st-{{ $case->kasir->decision ?: 'Menunggu' }}">{{ $case->kasir->decision ?: 'Menunggu' }}</span></div>
                  <div class="field" style="margin-bottom:12px;">
                    <label>Catatan Administrasi Akhir <span class="hint">(wajib diisi)</span></label>
                    <textarea id="kasirNote2" style="width:100%;" placeholder="Catatan administrasi akhir...">{{ $case->kasir->note2 ?: '' }}</textarea>
                  </div>
                  <div class="btn-row">
                    <button type="button" class="btn" id="kasirKonfirmasiBtn">Konfirmasi</button>
                    <button type="button" class="btn btn-success" id="kasirSelesai2Btn">Disetujui - Selesaikan Administrasi</button>
                    <button type="button" class="btn btn-danger" id="kasirBatalBtn">Batal (pasien menolak)</button>
                  </div>
                  <div style="border-top:1px solid var(--slate-200); padding-top:12px; margin-top:12px;">
                    <label style="font-size:12px; font-weight:700; color:var(--slate-500); display:block; margin-bottom:6px;">Jika perlu revisi:</label>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <select id="kasirReturnTo2" class="form-control" style="width:auto; flex:1; max-width:260px;">
                        <option value="Nurse">Nurse (data awal)</option>
                        <option value="CaseManager">Case Manager (keberatan)</option>
                        <option value="Farmasi">Farmasi (paket BMHP)</option>
                        <option value="AdminCOT">Admin COT (kebutuhan alat)</option>
                      </select>
                      <button type="button" class="btn btn-danger" id="kasirRevisi2Btn">Revisi</button>
                    </div>
                  </div>
                @endif
              @else
                <div class="locked-note">Proses administrasi kasir telah selesai.</div>
              @endif
            @endif

            <!-- - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - 
                 ADRU COT ACTION
            - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -->
            @if($activeRole === 'ADRUCOT')
              @if($vaActive)
                <div style="opacity:0.6; background:var(--slate-100); border:1px solid var(--slate-300); padding:15px; border-radius:8px;">
                  <div style="font-weight:700; color:var(--slate-600); margin-bottom:8px;"> Non-aktif (Penjamin = Asuransi)</div>
                  <div style="font-size:13px; color:var(--slate-500);">Form ADRU COT hanya dapat diisi jika Penjamin Kasus adalah Umum.</div>
                </div>
              @elseif(!$case->adru->done)
                <div class="user-context-badge" style="background:#EBF5FB; border-color:#AED6F1; color:#1B4F72; margin-bottom:12px; padding:10px 14px; display:flex; flex-direction:column; gap:4px;">
                  <div style="font-weight:700; font-size:13px;"> - Rincian Biaya dari Unit Lain:</div>
                  <div style="font-size:12px; display:flex; gap:12px; flex-wrap:wrap; margin-top:2px;">
                    <span><strong>BMHP:</strong> Rp {{ number_format($totalBmhp, 0, ',', '.') }}</span>
                    <span><strong>Alat:</strong> Rp {{ number_format($totalAlat, 0, ',', '.') }}</span>
                  </div>
                </div>

                @if(!$case->adru->stage1_done)
                  <h4>Penyusunan Estimasi ADRU (Stage 1)</h4>
                  <div class="permission-note" style="margin-bottom:12px;">Estimasi biaya dapat dihitung otomatis dari database tarif, atau diedit manual sepenuhnya untuk tindakan non-golongan.</div>
                  <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="field">
                      <label>Golongan Tindakan</label>
                      <select id="adruGolongan" class="form-control">
                        @foreach(['KECIL','SEDANG','BESAR','KHUSUS A','KHUSUS B','KHUSUS C','CATHLAB RINGAN','CATHLAB SEDANG','CATHLAB BERAT','CATHLAB KHUSUS A','CATHLAB KHUSUS B','BEDAH JANTUNG','NON GOLONGAN'] as $gol)
                          <option value="{{ $gol }}" {{ $case->golongan === $gol ? 'selected' : '' }}>{{ $gol }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="field">
                      <label>Kelas Perawatan</label>
                      <select id="adruKelas" class="form-control">
                        @foreach(['Kelas 3','Kelas 2','Kelas 1','VIP','VVIP'] as $k)
                          <option value="{{ $k }}" {{ $case->kelas_perawatan === $k ? 'selected' : '' }}>{{ $k }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                  <div id="adruEstimasiBox" style="margin-bottom:15px;"></div>
                  <div class="field" style="margin-bottom:12px;">
                    <label>Catatan ADRU</label>
                    <textarea id="adruNote" style="width:100%;" placeholder="Catatan estimasi ADRU...">{{ $case->adru->note ?: '' }}</textarea>
                  </div>
                  <div class="btn-row">
                    <button type="button" class="btn btn-primary" id="adruAjukanBtn">Ajukan ke Case Manager</button>
                  </div>
                  <div style="border-top:1px solid var(--slate-200); padding-top:12px; margin-top:12px;">
                    <label style="font-size:12px; font-weight:700; color:var(--slate-500); display:block; margin-bottom:6px;">Jika perlu revisi:</label>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <select id="adruReturnTo" class="form-control" style="width:auto; flex:1; max-width:260px;">
                        <option value="Nurse">Nurse (data awal)</option>
                        <option value="Farmasi">Farmasi (paket BMHP)</option>
                      </select>
                      <button type="button" class="btn btn-danger" id="adruRevisiBtn">Kembalikan untuk Revisi</button>
                    </div>
                  </div>
                @elseif(!$case->caseManager->done)
                  <div class="locked-note" style="background:#FEF3C7; color:#B45309; border-color:#FDE68A;">
                    [!] Estimasi ADRU telah diajukan ke Case Manager. Menunggu review &amp; persetujuan CM.
                  </div>
                @else
                  <h4>Konfirmasi Persetujuan Pasien (ADRU Stage 2)</h4>
                  <div class="permission-note" style="margin-bottom:12px;">Kirim estimasi ke pasien &amp; konfirmasi kesediaan. Setelah pasien setuju, diteruskan langsung ke Admin COT.</div>
                  <div style="font-size:12px; font-weight:700; margin-bottom:8px;">Status: <span class="badge-status st-{{ $case->adru->decision ?: 'Menunggu' }}">{{ $case->adru->decision ?: 'Menunggu' }}</span></div>
                  <div class="field" style="margin-bottom:12px;">
                    <label>Catatan Konfirmasi Pasien <span class="hint">(wajib diisi)</span></label>
                    <textarea id="adruConfirmNote" style="width:100%;" placeholder="Keterangan persetujuan pasien...">{{ $case->adru->confirm_note ?: '' }}</textarea>
                  </div>
                  <div class="btn-row">
                    <button type="button" class="btn" id="adruKonfirmasiBtn">Konfirmasi</button>
                    <button type="button" class="btn btn-success" id="adruSetujuBtn">Disetujui - Teruskan ke Admin COT</button>
                    <button type="button" class="btn btn-danger" id="adruBatalBtn">Batal (pasien menolak)</button>
                  </div>
                  <div style="border-top:1px solid var(--slate-200); padding-top:12px; margin-top:12px;">
                    <label style="font-size:12px; font-weight:700; color:var(--slate-500); display:block; margin-bottom:6px;">Jika perlu revisi:</label>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <select id="adruReturnTo2" class="form-control" style="width:auto; flex:1; max-width:260px;">
                        <option value="Nurse">Nurse (data awal)</option>
                        <option value="CaseManager">Case Manager (keberatan)</option>
                        <option value="Farmasi">Farmasi (paket BMHP)</option>
                      </select>
                      <button type="button" class="btn btn-danger" id="adruRevisi2Btn">Revisi</button>
                    </div>
                  </div>
                @endif
              @else
                <div class="locked-note">Proses estimasi &amp; persetujuan pasien umum di COT telah selesai.</div>
              @endif
            @endif

            <!-- - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - 
                 FARMASI ACTION
            - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -->
            @if($activeRole === 'Farmasi')
              @if($case->status === 'Draft')
                <div class="locked-note">Menunggu case di-Submit oleh Nurse.</div>
              @else
                @if($case->farmasi->done)
                  @php
                    $respondedUnits = [];
                    if ($vaActive && $case->va->stage1_done) $respondedUnits[] = 'VA';
                    if (!$vaActive && $case->kasir->stage1_done) $respondedUnits[] = 'Kasir';
                    if (!$vaActive && $case->adru->stage1_done) $respondedUnits[] = 'ADRU COT';
                    if ($adminCotRequired && $case->adminCot->prelim_done) $respondedUnits[] = 'Admin COT';
                    if ($case->caseManager->done) $respondedUnits[] = 'Case Manager';
                  @endphp
                  <div class="permission-note" style="background:#FEF3C7; margin-bottom:12px;">
                    Paket BMHP sudah <strong>Disetujui</strong>. Gunakan tombol Revisi bila perlu mengubah kembali.
                    @if(count($respondedUnits) > 0)
                      <div style="font-size:12px; margin-top:4px;">Unit yang sudah merespon: <strong>{{ implode(', ', $respondedUnits) }}</strong></div>
                    @endif
                  </div>
                @endif

                <h4>Review &amp; Edit Kebutuhan BMHP / Obat</h4>

                <div class="section-lbl" style="margin-bottom:6px;">A. Paket BMHP (dari tindakan)</div>
                <table class="af-table" style="width:100%; margin-bottom:6px;">
                  <thead>
                    <tr style="font-weight:700;"><td>Nama Item</td><td style="width:70px;">Qty</td><td style="width:130px;">Harga (Rp)</td><td style="width:65px;">Aksi</td></tr>
                  </thead>
                  <tbody id="farmasiPaketBody"></tbody>
                </table>
                <button type="button" class="btn btn-sm" id="farmasiAddPaketBtn" {{ $case->farmasi->done ? 'disabled' : '' }}>+ Tambah Item Paket</button>

                <div class="section-lbl" style="margin-top:14px; margin-bottom:6px;">B. Tambahan di Luar Paket</div>
                <table class="af-table" style="width:100%; margin-bottom:6px;">
                  <thead>
                    <tr style="font-weight:700;"><td>Nama Item</td><td style="width:70px;">Qty</td><td style="width:130px;">Harga (Rp)</td><td style="width:65px;">Aksi</td></tr>
                  </thead>
                  <tbody id="farmasiTambahanBody"></tbody>
                </table>
                <button type="button" class="btn btn-sm" id="farmasiAddTambahanBtn" {{ $case->farmasi->done ? 'disabled' : '' }}>+ Tambah Item Tambahan</button>

                <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                  <div style="font-weight:bold; font-size:14px;" id="farmasiGrandTotal">Total: Rp 0</div>
                </div>
                <div class="field" style="margin-top:10px; margin-bottom:12px;">
                  <label>Catatan Review Farmasi</label>
                  <textarea id="farmasiNote" style="width:100%;" placeholder="Catatan obat/BMHP...">{{ $case->farmasi->note ?: '' }}</textarea>
                </div>
                <div class="btn-row">
                  @if(!$case->farmasi->done)
                    <button type="button" class="btn btn-primary" id="farmasiSetujuBtn">Setujui Paket BMHP</button>
                    <button type="button" class="btn" id="farmasiKonfirmasiBtn">Konfirmasi (Simpan Sementara)</button>
                    <button type="button" class="btn btn-danger" id="farmasiRevisiBtn">Kembalikan ke Nurse</button>
                  @else
                    <button type="button" class="btn btn-danger" id="farmasiRevisiAfterDoneBtn">Revisi Paket BMHP</button>
                    <button type="button" class="btn" id="farmasiKonfirmasiBtn">Konfirmasi</button>
                  @endif
                </div>
              @endif
            @endif

            <!-- - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - 
                 CASE MANAGER ACTION
            - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -  - -->
            @if($activeRole === 'CaseManager')
              @if(!$case->caseManager->done)
                <h4>Persetujuan Case Manager</h4>
                
                @php
                  $lengkap = $case->farmasi->done && $case->adminCot->final_done;
                  $belum = [];
                  if (!$case->farmasi->done) $belum[] = 'Farmasi';
                  if (!$case->adminCot->final_done) $belum[] = 'Admin COT';
                @endphp
                @if($lengkap)
                  <div class="permission-note">Semua unit terkait sudah lengkap. Silakan review &amp; putuskan.</div>
                @else
                  <div class="permission-note" style="background:var(--amber-100);">
                    ⚡ <strong>Mode Bypass:</strong> Anda dapat merespon sebelum lengkap. Menunggu: {{ implode(', ', $belum) }}.
                  </div>
                @endif

                <div class="field" style="margin-bottom:12px;">
                  <label>Golongan Tindakan (Adjustable)</label>
                  <select id="cmGolongan" class="form-control">
                    <option value="KECIL" {{ $case->golongan === 'KECIL' ? 'selected' : '' }}>KECIL</option>
                    <option value="SEDANG" {{ $case->golongan === 'SEDANG' ? 'selected' : '' }}>SEDANG</option>
                    <option value="BESAR" {{ $case->golongan === 'BESAR' ? 'selected' : '' }}>BESAR</option>
                    <option value="KHUSUS A" {{ $case->golongan === 'KHUSUS A' ? 'selected' : '' }}>KHUSUS A</option>
                    <option value="KHUSUS B" {{ $case->golongan === 'KHUSUS B' ? 'selected' : '' }}>KHUSUS B</option>
                    <option value="KHUSUS C" {{ $case->golongan === 'KHUSUS C' ? 'selected' : '' }}>KHUSUS C</option>
                    <option value="NON GOLONGAN" {{ $case->golongan === 'NON GOLONGAN' ? 'selected' : '' }}>NON GOLONGAN</option>
                  </select>
                </div>

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
                  <textarea id="cmNote" style="width:100%;" placeholder="Instruksi CM...">{{ $case->caseManager->instruksi ?: '' }}</textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn btn-success" id="cmSetujuBtn">Setujui Estimasi &amp; Dokumen</button>
                  <button type="button" class="btn btn-danger" id="cmRevisiBtn">Kembalikan untuk Revisi</button>
                  <button type="button" class="btn" id="cmBelumLengkapBtn">Dokumen Belum Lengkap</button>
                </div>
              @else
                <div class="locked-note">Case Manager telah menyetujui dokumen ini.</div>
              @endif
            @endif

            <!-- CUSTOMER SERVICE ACTION -->
            @if($activeRole === 'CS' && $vaActive)
              @if(!$case->cs->done)
                <h4>Follow Up &amp; Konfirmasi Pasien</h4>
                @if($case->cs->follow_up_due)
                  <div id="csCountdown" data-due="{{ $case->cs->follow_up_due }}" style="padding: 10px 14px; border-radius: 6px; font-weight: bold; margin-bottom: 12px;"></div>
                @endif
                <div class="field" style="margin-bottom:12px;">
                  <label>Catatan Follow Up</label>
                  <textarea id="csNote" style="width:100%;" placeholder="Keterangan respon pasien...">{{ $case->cs->decision_note ?: '' }}</textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn" id="csHubungiBtn">Hubungi Pasien (Follow Up)</button>
                  <button type="button" class="btn btn-success" id="csSetujuBtn">Pasien Setuju Tindakan</button>
                  <button type="button" class="btn" id="csRescheduleBtn">Pasien Minta Reschedule</button>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                  <button type="button" class="btn btn-danger" id="csBatalBtn">Pasien Batal Tindakan</button>
                  <button type="button" class="btn btn-warning" id="csRevisiBtn">Kembalikan ke Nurse</button>
                  <button type="button" class="btn" id="csKonfirmasiBtn">Kembalikan ke Case Manager</button>
                </div>
              @else
                <div class="locked-note">Follow-up pasien telah selesai/disetujui.</div>
              @endif
            @endif

            <!-- ADMIN COT ACTION -->
            @if($activeRole === 'AdminCOT' && $adminCotRequired)
              {{-- Alat Khusus Editor --}}
              <div style="border: 1px solid var(--slate-200); border-radius: 6px; padding: 12px; margin-bottom: 15px; background: var(--slate-50);">
                <h5 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: var(--primary-800);">Kelola Kebutuhan Alat &amp; Harga</h5>
                <table class="af-table" style="width:100%;">
                  <thead>
                    <tr style="font-weight:700;">
                      <td>Nama Alat Khusus</td>
                      <td style="width:150px;">Harga (Rp)</td>
                      <td style="width:65px;">Aksi</td>
                    </tr>
                  </thead>
                  <tbody id="adminAlatBody"></tbody>
                </table>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                  <button type="button" class="btn btn-sm" id="adminAddAlatBtn">+ Tambah Alat</button>
                  <div style="font-weight:bold; font-size:14px;" id="adminAlatGrandTotal">Total: Rp 0</div>
                </div>
                <div style="margin-top:8px; text-align:right;">
                  <button type="button" class="btn btn-sm btn-primary" id="adminSaveToolsBtn">Simpan Perubahan Alat</button>
                </div>
              </div>

              @if(!$case->adminCot->final_done)
                <h4>Penetapan Jadwal &amp; Kamar Operasi Final</h4>
                <div class="form-grid">
                  <div class="field"><label class="req">Tanggal Operasi</label><input type="date" id="adminTgl" value="{{ $case->tanggal_pilihan1 ? $case->tanggal_pilihan1->format('Y-m-d') : '' }}"></div>
                  <div class="field"><label class="req">Jam Operasi</label><input type="time" id="adminJam" value="{{ $case->jam_operasi ?: '' }}"></div>
                  <div class="field"><label class="req">Ruang/Kamar Operasi</label>
                    <select id="adminRuang" class="form-control">
                      <option value="Kamar Operasi 1" {{ $case->adminCot->kamar_operasi === 'Kamar Operasi 1' ? 'selected' : '' }}>Kamar Operasi 1</option>
                      <option value="Kamar Operasi 2" {{ $case->adminCot->kamar_operasi === 'Kamar Operasi 2' ? 'selected' : '' }}>Kamar Operasi 2</option>
                      <option value="Kamar Operasi 3" {{ $case->adminCot->kamar_operasi === 'Kamar Operasi 3' ? 'selected' : '' }}>Kamar Operasi 3</option>
                      <option value="Kamar Operasi 4" {{ $case->adminCot->kamar_operasi === 'Kamar Operasi 4' ? 'selected' : '' }}>Kamar Operasi 4</option>
                      <option value="Kamar Operasi 5" {{ $case->adminCot->kamar_operasi === 'Kamar Operasi 5' ? 'selected' : '' }}>Kamar Operasi 5</option>
                      <option value="Kamar Operasi 6" {{ $case->adminCot->kamar_operasi === 'Kamar Operasi 6' ? 'selected' : '' }}>Kamar Operasi 6</option>
                      <option value="HYBRID" {{ $case->adminCot->kamar_operasi === 'HYBRID' ? 'selected' : '' }}>HYBRID</option>
                      <option value="COT LT 5" {{ $case->adminCot->kamar_operasi === 'COT LT 5' ? 'selected' : '' }}>COT LT 5</option>
                      <option value="IGD" {{ $case->adminCot->kamar_operasi === 'IGD' ? 'selected' : '' }}>IGD</option>
                      <option value="CATHLAB" {{ $case->adminCot->kamar_operasi === 'CATHLAB' ? 'selected' : '' }}>CATHLAB</option>
                      <option value="ICU" {{ $case->adminCot->kamar_operasi === 'ICU' ? 'selected' : '' }}>ICU</option>
                    </select>
                  </div>
                </div>
                <div class="field" style="margin-top:12px; margin-bottom:12px;">
                  <label>Catatan Penjadwalan</label>
                  <textarea id="adminNote" style="width:100%;" placeholder="Catatan penjadwalan...">{{ $case->adminCot->decision_note ?: '' }}</textarea>
                </div>
                <div class="btn-row">
                  <button type="button" class="btn btn-primary" id="adminFinalBtn">Tetapkan Jadwal Final</button>
                  <button type="button" class="btn btn-primary" id="adminPrelimBtn">Simpan Alat &amp; Tandai Prelim Selesai</button>
                  <button type="button" class="btn btn-danger" id="adminRevisiNurseBtn">Kembalikan ke Nurse</button>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                  <button type="button" class="btn" id="adminConfirmBtn">Tandai Dalam Konfirmasi</button>
                  <button type="button" class="btn" id="adminRescheduleBtn">Reschedule Jadwal</button>
                </div>
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

  <!-- Preview Modal -->
  <div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center; padding:20px;">
    <div style="background:var(--white); width:100%; max-width:800px; height:100%; max-height:600px; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
      <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid var(--slate-200); background:var(--slate-50);">
        <h4 id="previewModalTitle" style="margin:0; font-size:14px; font-weight:700; color:var(--slate-800);">Pratinjau Dokumen</h4>
        <button type="button" onclick="closePreviewModal()" style="border:none; background:none; font-size:20px; font-weight:bold; color:var(--slate-400); cursor:pointer; padding:0 4px; line-height:1;">&times;</button>
      </div>
      <div id="previewModalBody" style="flex:1; padding:16px; display:flex; justify-content:center; align-items:center; background:var(--slate-100); overflow:auto;">
        <!-- Embedded preview content (iframe or img) will go here -->
      </div>
    </div>
  </div>
@endsection

@section('scripts')
  @php
    // Pre-compute complex PHP expressions to avoid Blade/PHP parse errors
    $cmJasaRincian = ($case->va && $case->va->estimasi_rincian) ? $case->va->estimasi_rincian : [];
    $cmBmhpData = $case->tambahanBmhp->map(function($t) {
        return ['n' => $t->nama, 'h' => (int)$t->harga, 'q' => (float)$t->qty];
    })->values()->toArray();
    $cmAlatData = $case->alat->map(function($a) {
        $h = $a->harga > 0 ? $a->harga : ($a->masterAlat ? $a->masterAlat->tarif : 0);
        return ['nama' => $a->nama, 'harga' => $h];
    })->values()->toArray();
    $farmasiPaketData = $case->tambahanBmhp->filter(function($t) { return $t->jenis === 'paket'; })->values()->map(function($t) {
        return ['nama' => $t->nama, 'qty' => (float)$t->qty, 'harga' => (int)$t->harga];
    })->values()->toArray();
    $farmasiTambahanData = $case->tambahanBmhp->filter(function($t) { return $t->jenis !== 'paket'; })->values()->map(function($t) {
        return ['nama' => $t->nama, 'qty' => (float)$t->qty, 'harga' => (int)$t->harga];
    })->values()->toArray();
    $namaPenjaminJs = json_encode($case->nama_guarantor ?? '');
  @endphp
  <script>
    // Global helper and state variables
    function rupiah(n) {
      return "Rp " + new Intl.NumberFormat("id-ID").format(n);
    }
    let masterData = { tindakan: [], alat: [], paket_bmhp: [] };

    // Embed the estimasi table from COT_DB
    const MASTER_TARIF_DB = @json($masterTarifDb);
    const GUARANTOR_MAPPINGS = @json($mappings);

    function resolveKelompokTarif(guarantorName) {
      const name = String(guarantorName || "").toLowerCase().trim();
      if (!name) return { kelompokTarif: "2026", cob: false };

      for (const m of GUARANTOR_MAPPINGS) {
        if (name.includes(String(m.pola).toLowerCase())) {
          return { kelompokTarif: m.kelompok_tarif, cob: !!m.cob };
        }
      }
      return { kelompokTarif: "2026", cob: false };
    }

    // Helper mapping for class codes used in DB keys
    const classMapping = {
      "Kelas 3": "k3",
      "Kelas 2": "k2",
      "Kelas 1": "k1",
      "VIP": "vip",
      "VVIP": "vvip"
    };

    // Live Summary & Grand Total calculation
    let currentJasaMedis = @json($jasaMedis);

    function updateSummaryPanel() {
      let totalBmhp = 0;
      if (typeof farmasiBmhpList !== 'undefined') {
        farmasiBmhpList.forEach(item => {
          totalBmhp += (Number(item.qty) || 0) * (Number(item.harga) || 0);
        });
      } else {
        totalBmhp = @json($totalBmhp);
      }

      let totalAlat = 0;
      if (typeof adminAlatList !== 'undefined') {
        adminAlatList.forEach(item => {
          totalAlat += Number(item.harga) || 0;
        });
      } else {
        totalAlat = @json($totalAlat);
      }

      const elBmhp = document.getElementById("summaryBmhp");
      if (elBmhp) elBmhp.textContent = rupiah(totalBmhp);

      const elAlat = document.getElementById("summaryAlat");
      if (elAlat) elAlat.textContent = rupiah(totalAlat);

      const grandTotal = currentJasaMedis + totalAlat + totalBmhp;
      const elGrandTotal = document.getElementById("summaryGrandTotal");
      if (elGrandTotal) {
        elGrandTotal.textContent = rupiah(grandTotal);
      }
    }

    // Initialize VA Jasa Medis Estimasi form
    function initVaEstimasi() {
      const golSelect = document.getElementById("vaGolongan");
      const kelasSelect = document.getElementById("vaKelas");
      const box = document.getElementById("vaEstimasiBox");
      if (!box || !golSelect || !kelasSelect) return;

      const guarantorName = @json($case->nama_guarantor);
      const isCito = @json(in_array('CITO', $case->jenis_operasi ?: []));
      const isPenyulit = @json(in_array('Penyulit', $case->jenis_operasi ?: []));
      const isOdc = @json(in_array('ODC', $case->jenis_operasi ?: []));
      const hasOpII = @json($case->operators->count() >= 2);

      const resolved = resolveKelompokTarif(guarantorName);
      const resolvedGroup = resolved.kelompokTarif;
      const isCob = resolved.cob;

      function render() {
        const gol = golSelect.value;
        const resolvedKelas = kelasSelect.value;
        
        let resolvedKelasForTarif = resolvedKelas;
        if (isOdc) {
          resolvedKelasForTarif = "Kelas 3";
        }

        const kelasCol = classMapping[resolvedKelasForTarif] || "k3";

        let tariffKey = gol;
        if (isCito) {
          tariffKey = `${gol} CITO`;
        } else if (isPenyulit) {
          tariffKey = `${gol} PENYULIT`;
        }

        const ratesGroup = MASTER_TARIF_DB[resolvedGroup];
        const rates = ratesGroup ? ratesGroup[tariffKey] : null;

        if (!rates) {
          box.innerHTML = `
            <div class="autofill-box">
              <span class="hint">Estimasi manual untuk golongan non-standar:</span>
              <div class="field" style="margin-top:8px;">
                <label>Total Estimasi (Rp)</label>
                <input id="vaTotalManual" class="form-control" type="number" style="width:100%; text-align:right;" value="${currentJasaMedis || ''}">
              </div>
            </div>`;
          const manualInput = document.getElementById("vaTotalManual");
          if (manualInput) {
            manualInput.addEventListener("input", function() {
              currentJasaMedis = Number(manualInput.value) || 0;
              const elJasa = document.getElementById("summaryJasaMedis");
              if (elJasa) elJasa.textContent = rupiah(currentJasaMedis);
              updateSummaryPanel();
            });
          }
          return;
        }

        let rowsHtml = "";
        let total = 0;
        rates.forEach((row, idx) => {
          if (!hasOpII && /operator\s*ii\b/i.test(row.komponen)) return;

          let val = isCob ? Number(row.cob || 0) : Number(row[kelasCol] || 0);
          if (isOdc && /sewa kamar/i.test(row.komponen)) {
            val = 500000;
          }

          total += val;
          rowsHtml += `
            <tr>
              <td>${row.komponen}</td>
              <td style="text-align:right;">
                <input class="vaKomp" data-i="${idx}" data-komp="${row.komponen}" type="number" value="${val}" style="width:140px; text-align:right; padding:4px 6px;">
              </td>
            </tr>`;
        });

        box.innerHTML = `
          <div class="autofill-box">
            <div style="font-size:11px; margin-bottom:8px; font-weight:700; color:var(--primary-800);">
              Pola Asuransi: "${guarantorName || 'Umum'}" &rarr; Kelompok Tarif ${resolvedGroup} ${isCob ? '(COB)' : ''}
            </div>
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
            currentJasaMedis = t;
            const elJasa = document.getElementById("summaryJasaMedis");
            if (elJasa) elJasa.textContent = rupiah(t);
            updateSummaryPanel();
          });
        });

        // Update summary on load
        currentJasaMedis = total;
        const elJasa = document.getElementById("summaryJasaMedis");
        if (elJasa) elJasa.textContent = rupiah(total);
        updateSummaryPanel();
      }

      golSelect.addEventListener("change", render);
      kelasSelect.addEventListener("change", render);
      render();
    }

    // Call VA Form Init if element present
    initVaEstimasi();

    // Kasir Estimasi Panel (identical engine to VA)
    function initKasirEstimasi() {
      const golSelect = document.getElementById("kasirGolongan");
      const kelasSelect = document.getElementById("kasirKelas");
      const box = document.getElementById("kasirEstimasiBox");
      if (!box || !golSelect || !kelasSelect) return;
      const guarantorName = @json($case->nama_guarantor);
      const isCito = @json(in_array('CITO', $case->jenis_operasi ?: []));
      const isPenyulit = @json(in_array('Penyulit', $case->jenis_operasi ?: []));
      const isOdc = @json(in_array('ODC', $case->jenis_operasi ?: []));
      const hasOpII = @json($case->operators->count() >= 2);
      const resolved = resolveKelompokTarif(guarantorName);
      function render() {
        const gol = golSelect.value; let resolvedKelasForTarif = kelasSelect.value;
        if (isOdc) resolvedKelasForTarif = "Kelas 3";
        const kelasCol = classMapping[resolvedKelasForTarif] || "k3";
        let tariffKey = isCito ? `${gol} CITO` : (isPenyulit ? `${gol} PENYULIT` : gol);
        const ratesGroup = MASTER_TARIF_DB[resolved.kelompokTarif];
        const rates = ratesGroup ? ratesGroup[tariffKey] : null;
        if (!rates) {
          box.innerHTML = `<div class="autofill-box"><span class="hint">Estimasi manual untuk golongan non-standar:</span><div class="field" style="margin-top:8px;"><label>Total Estimasi (Rp)</label><input id="kasirTotalManual" class="form-control" type="number" style="width:100%;text-align:right;" value="${currentJasaMedis||''}"></div></div>`;
          return;
        }
        let rowsHtml = ""; let total = 0;
        rates.forEach((row, idx) => {
          if (!hasOpII && /operator\s*ii\b/i.test(row.komponen)) return;
          let val = resolved.cob ? Number(row.cob||0) : Number(row[kelasCol]||0);
          if (isOdc && /sewa kamar/i.test(row.komponen)) val = 500000;
          total += val;
          rowsHtml += `<tr><td>${row.komponen}</td><td style="text-align:right;"><input class="kasirKomp" data-komp="${row.komponen}" type="number" value="${val}" style="width:140px;text-align:right;padding:4px 6px;"></td></tr>`;
        });
        box.innerHTML = `<div class="autofill-box"><div style="font-size:11px;margin-bottom:8px;font-weight:700;color:var(--primary-800);">Kelompok Tarif ${resolved.kelompokTarif} ${resolved.cob?'(COB)':''}</div><table class="af-table"><tr style="font-weight:700;"><td>Komponen Jasa Medis</td><td style="text-align:right;">Nilai (Rp)</td></tr>${rowsHtml}<tr style="font-weight:800;"><td>TOTAL</td><td style="text-align:right;" id="kasirTotalCell">${rupiah(total)}</td></tr></table><span class="hint">Nilai sesuai SK. Dapat diubah bila perlu.</span></div>`;
        box.querySelectorAll(".kasirKomp").forEach(inp => inp.addEventListener("input", () => {
          let t=0; box.querySelectorAll(".kasirKomp").forEach(x=>t+=Number(x.value)||0);
          const tc=document.getElementById("kasirTotalCell"); if(tc)tc.textContent=rupiah(t);
        }));
      }
      golSelect.addEventListener("change", render);
      kelasSelect.addEventListener("change", render);
      render();
    }
    initKasirEstimasi();

    // ADRU Estimasi Panel (identical engine to VA/Kasir)
    function initAdruEstimasi() {
      const golSelect = document.getElementById("adruGolongan");
      const kelasSelect = document.getElementById("adruKelas");
      const box = document.getElementById("adruEstimasiBox");
      if (!box || !golSelect || !kelasSelect) return;
      const guarantorName = @json($case->nama_guarantor);
      const isCito = @json(in_array('CITO', $case->jenis_operasi ?: []));
      const isPenyulit = @json(in_array('Penyulit', $case->jenis_operasi ?: []));
      const isOdc = @json(in_array('ODC', $case->jenis_operasi ?: []));
      const hasOpII = @json($case->operators->count() >= 2);
      const resolved = resolveKelompokTarif(guarantorName);
      function render() {
        const gol = golSelect.value; let resolvedKelasForTarif = kelasSelect.value;
        if (isOdc) resolvedKelasForTarif = "Kelas 3";
        const kelasCol = classMapping[resolvedKelasForTarif] || "k3";
        let tariffKey = isCito ? `${gol} CITO` : (isPenyulit ? `${gol} PENYULIT` : gol);
        const ratesGroup = MASTER_TARIF_DB[resolved.kelompokTarif];
        const rates = ratesGroup ? ratesGroup[tariffKey] : null;
        if (!rates) {
          box.innerHTML = `<div class="autofill-box"><span class="hint">Estimasi manual untuk golongan non-standar:</span><div class="field" style="margin-top:8px;"><label>Total Estimasi (Rp)</label><input id="adruTotalManual" class="form-control" type="number" style="width:100%;text-align:right;" value="${currentJasaMedis||''}"></div></div>`;
          return;
        }
        let rowsHtml = ""; let total = 0;
        rates.forEach((row, idx) => {
          if (!hasOpII && /operator\s*ii\b/i.test(row.komponen)) return;
          let val = resolved.cob ? Number(row.cob||0) : Number(row[kelasCol]||0);
          if (isOdc && /sewa kamar/i.test(row.komponen)) val = 500000;
          total += val;
          rowsHtml += `<tr><td>${row.komponen}</td><td style="text-align:right;"><input class="adruKomp" data-komp="${row.komponen}" type="number" value="${val}" style="width:140px;text-align:right;padding:4px 6px;"></td></tr>`;
        });
        box.innerHTML = `<div class="autofill-box"><div style="font-size:11px;margin-bottom:8px;font-weight:700;color:var(--primary-800);">Kelompok Tarif ${resolved.kelompokTarif} ${resolved.cob?'(COB)':''}</div><table class="af-table"><tr style="font-weight:700;"><td>Komponen Jasa Medis</td><td style="text-align:right;">Nilai (Rp)</td></tr>${rowsHtml}<tr style="font-weight:800;"><td>TOTAL</td><td style="text-align:right;" id="adruTotalCell">${rupiah(total)}</td></tr></table><span class="hint">Nilai sesuai SK. Dapat diubah bila perlu.</span></div>`;
        box.querySelectorAll(".adruKomp").forEach(inp => inp.addEventListener("input", () => {
          let t=0; box.querySelectorAll(".adruKomp").forEach(x=>t+=Number(x.value)||0);
          const tc=document.getElementById("adruTotalCell"); if(tc)tc.textContent=rupiah(t);
        }));
      }
      golSelect.addEventListener("change", render);
      kelasSelect.addEventListener("change", render);
      render();
    }
    initAdruEstimasi();

    // General AJAX submission helper
    function submitAction(routeUrl, payload, message = "Aksi berhasil diproses") {
      let options = {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken
        }
      };

      if (payload instanceof FormData) {
        options.body = payload;
      } else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
      }

      fetch(routeUrl, options)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          toast(message, "success");
          setTimeout(() => {
            @if($activeRole === 'Nurse')
              window.location.href = '/cases?queue=mine';
            @else
              window.location.reload();
            @endif
          }, 800);
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
      submitBtn.onclick = () => {
        const items = document.querySelectorAll('#attachmentsContainer .attachment-item');
        if (items.length === 0) {
          toast('Minimal 1 dokumen (Formulir Penjadwalan Tindakan) wajib diunggah sebelum Submit Pengajuan.', 'error');
          return;
        }
        if (confirm("Apakah anda yakin data sudah sesuai?")) {
          submitAction('{{ route("cases.submit", $case->id) }}', {}, "Kasus diajukan ke Workflow Engine!");
        }
      };
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

        const formData = new FormData();
        formData.append('action', 'ajukan1');
        formData.append('golongan', gol);
        formData.append('kelas', kelas);
        formData.append('total', total);
        formData.append('note', note);
        formData.append('rincian', JSON.stringify(rincian));

        const fileInput = document.getElementById("vaFile");
        if (fileInput && fileInput.files.length > 0) {
          for (let i = 0; i < fileInput.files.length; i++) {
            formData.append('files[]', fileInput.files[i]);
          }
        }

        submitAction('{{ route("cases.va", $case->id) }}', formData, "Estimasi biaya diajukan ke Case Manager!");
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

    // VA Revisi Stage 1 (with dynamic returnTo)
    const vaRevisiBtn2 = document.getElementById("vaRevisiBtn2");
    if (vaRevisiBtn2) {
      vaRevisiBtn2.onclick = () => {
        const note = document.getElementById("vaNote2") ? document.getElementById("vaNote2").value : '';
        const target = document.getElementById("vaReturnTo2") ? document.getElementById("vaReturnTo2").value : 'Nurse';
        submitAction('{{ route("cases.va", $case->id) }}', { action: 'revisi2', note, returnTo: target }, "Revisi dikirim ke " + target);
      };
    }

    // VA stage 2 submit helper (multipart file upload + checklist)
    function submitVaStage2(action) {
      const formData = new FormData();
      formData.append('action', action);
      
      const noteInput = document.getElementById("vaNote2") || document.getElementById("vaNote");
      if (noteInput) formData.append('note', noteInput.value);

      // Add checklist items
      document.querySelectorAll(".va-check:checked").forEach(chk => formData.append('checklist[]', chk.value));

      // Add files
      const fileInput = document.getElementById("vaFile");
      if (fileInput && fileInput.files.length > 0) {
        for (let i = 0; i < fileInput.files.length; i++) formData.append('files[]', fileInput.files[i]);
      }

      submitAction('{{ route("cases.va", $case->id) }}', formData, "Aksi VA Stage 2 berhasil diproses");
    }
    const vaBelumLengkapBtn = document.getElementById("vaBelumLengkapBtn");
    if (vaBelumLengkapBtn) {
      vaBelumLengkapBtn.onclick = () => submitVaStage2('berkasBelumLengkap');
    }
    const vaLengkapBtn = document.getElementById("vaLengkapBtn");
    if (vaLengkapBtn) {
      vaLengkapBtn.onclick = () => submitVaStage2('berkasLengkap');
    }
    const vaSetujuBtn = document.getElementById("vaSetujuBtn");
    if (vaSetujuBtn) {
      vaSetujuBtn.onclick = () => submitVaStage2('disetujui');
    }
    const vaPendingBtn = document.getElementById("vaPendingBtn");
    if (vaPendingBtn) {
      vaPendingBtn.onclick = () => submitVaStage2('pending');
    }
    const vaTolakBtn = document.getElementById("vaTolakBtn");
    if (vaTolakBtn) {
      vaTolakBtn.onclick = () => submitVaStage2('ditolak');
    }
    const vaDalamKonfBtn = document.getElementById("vaDalamKonfBtn");
    if (vaDalamKonfBtn) {
      vaDalamKonfBtn.onclick = () => submitVaStage2('dalamKonfirmasi');
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
        const note = document.getElementById("kasirNote") ? document.getElementById("kasirNote").value : '';
        const gol = document.getElementById("kasirGolongan") ? document.getElementById("kasirGolongan").value : '';
        const kelas = document.getElementById("kasirKelas") ? document.getElementById("kasirKelas").value : '';
        let rincian = []; let total = 0;
        const box = document.getElementById("kasirEstimasiBox");
        if (box) {
          const manual = box.querySelector('#kasirTotalManual');
          if (manual) { total = Number(manual.value) || 0; rincian.push({ komponen: 'Estimasi Manual', nilai: total }); }
          else { box.querySelectorAll('.kasirKomp').forEach(x => { const v = Number(x.value)||0; rincian.push({komponen:x.dataset.komp,nilai:v}); total+=v; }); }
        }
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'selesai1', note, golongan: gol, kelas, total_estimasi: total, rincian: JSON.stringify(rincian) }, "Administrasi awal diselesaikan dan diajukan ke CM");
      };
    }
    const kasirRevisiBtn = document.getElementById("kasirRevisiBtn");
    if (kasirRevisiBtn) {
      kasirRevisiBtn.onclick = () => {
        const note = document.getElementById("kasirNote") ? document.getElementById("kasirNote").value : '';
        const target = document.getElementById("kasirReturnTo") ? document.getElementById("kasirReturnTo").value : 'Nurse';
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'revisi1', note, returnTo: target }, "Permintaan revisi dikirim");
      };
    }
    const kasirKonfirmasiBtn = document.getElementById("kasirKonfirmasiBtn");
    if (kasirKonfirmasiBtn) {
      kasirKonfirmasiBtn.onclick = () => {
        const note = document.getElementById("kasirNote2") ? document.getElementById("kasirNote2").value : '';
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'konfirmasi', note }, "Status dikonfirmasi");
      };
    }
    const kasirSelesai2Btn = document.getElementById("kasirSelesai2Btn");
    if (kasirSelesai2Btn) {
      kasirSelesai2Btn.onclick = () => {
        const note = document.getElementById("kasirNote2") ? document.getElementById("kasirNote2").value : '';
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'selesai2', note }, "Administrasi akhir diselesaikan!");
      };
    }
    const kasirBatalBtn = document.getElementById("kasirBatalBtn");
    if (kasirBatalBtn) {
      kasirBatalBtn.onclick = () => {
        const note = document.getElementById("kasirNote2") ? document.getElementById("kasirNote2").value : '';
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'batal', note }, "Case dibatalkan atas penolakan pasien");
      };
    }
    const kasirRevisi2Btn = document.getElementById("kasirRevisi2Btn");
    if (kasirRevisi2Btn) {
      kasirRevisi2Btn.onclick = () => {
        const note = document.getElementById("kasirNote2") ? document.getElementById("kasirNote2").value : '';
        const target = document.getElementById("kasirReturnTo2") ? document.getElementById("kasirReturnTo2").value : 'Nurse';
        submitAction('{{ route("cases.kasir", $case->id) }}', { action: 'revisi2', note, returnTo: target }, "Revisi dikirim ke " + target);
      };
    }

    // ADRU COT buttons - now with Golongan/Kelas/full estimasi panel
    const adruAjukanBtn = document.getElementById("adruAjukanBtn");
    if (adruAjukanBtn) {
      adruAjukanBtn.onclick = () => {
        const note = document.getElementById("adruNote") ? document.getElementById("adruNote").value : '';
        const gol = document.getElementById("adruGolongan") ? document.getElementById("adruGolongan").value : '';
        const kelas = document.getElementById("adruKelas") ? document.getElementById("adruKelas").value : '';
        let rincian = []; let total = 0;
        const box = document.getElementById("adruEstimasiBox");
        if (box) {
          const manual = box.querySelector('#adruTotalManual');
          if (manual) { total = Number(manual.value)||0; rincian.push({komponen:'Estimasi Manual',nilai:total}); }
          else { box.querySelectorAll('.adruKomp').forEach(x => { const v=Number(x.value)||0; rincian.push({komponen:x.dataset.komp,nilai:v}); total+=v; }); }
        }
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'ajukan1', note, golongan: gol, kelas, estimasi: total, rincian: JSON.stringify(rincian) }, "Estimasi diajukan ke Case Manager!");
      };
    }
    const adruRevisiBtn = document.getElementById("adruRevisiBtn");
    if (adruRevisiBtn) {
      adruRevisiBtn.onclick = () => {
        const note = document.getElementById("adruNote") ? document.getElementById("adruNote").value : '';
        const target = document.getElementById("adruReturnTo") ? document.getElementById("adruReturnTo").value : 'Nurse';
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'revisi1', note, returnTo: target }, "Revisi dikirim ke " + target);
      };
    }
    const adruKonfirmasiBtn = document.getElementById("adruKonfirmasiBtn");
    if (adruKonfirmasiBtn) {
      adruKonfirmasiBtn.onclick = () => {
        const note = document.getElementById("adruConfirmNote") ? document.getElementById("adruConfirmNote").value : '';
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'konfirmasi_intermediate', note }, "Status dikonfirmasi");
      };
    }
    const adruSetujuBtn = document.getElementById("adruSetujuBtn");
    if (adruSetujuBtn) {
      adruSetujuBtn.onclick = () => {
        const note = document.getElementById("adruConfirmNote") ? document.getElementById("adruConfirmNote").value : '';
        if (!note.trim()) { toast('Catatan konfirmasi wajib diisi', 'error'); return; }
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'konfirmasi2', note }, "Persetujuan pasien disubmit!");
      };
    }
    const adruBatalBtn = document.getElementById("adruBatalBtn");
    if (adruBatalBtn) {
      adruBatalBtn.onclick = () => {
        const note = document.getElementById("adruConfirmNote") ? document.getElementById("adruConfirmNote").value : '';
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'batal', note }, "Case dibatalkan atas penolakan pasien");
      };
    }
    const adruRevisi2Btn = document.getElementById("adruRevisi2Btn");
    if (adruRevisi2Btn) {
      adruRevisi2Btn.onclick = () => {
        const note = document.getElementById("adruConfirmNote") ? document.getElementById("adruConfirmNote").value : '';
        const target = document.getElementById("adruReturnTo2") ? document.getElementById("adruReturnTo2").value : 'Nurse';
        submitAction('{{ route("cases.adru", $case->id) }}', { action: 'revisi2', note, returnTo: target }, "Revisi dikirim ke " + target);
      };
    }

    // Farmasi buttons
    const farmasiSetujuBtn = document.getElementById("farmasiSetujuBtn");
    if (farmasiSetujuBtn) {
      farmasiSetujuBtn.onclick = () => {
        const note = document.getElementById("farmasiNote") ? document.getElementById("farmasiNote").value : '';
        const paket = farmasiPaketList.map(x => ({...x, jenis:'paket'}));
        const tambahan = farmasiTambahanList.map(x => ({...x, jenis:'tambahan'}));
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'setuju', note, items: JSON.stringify([...paket,...tambahan]) }, "Farmasi menyetujui paket BMHP!");
      };
    }
    const farmasiKonfirmasiBtn = document.getElementById("farmasiKonfirmasiBtn");
    if (farmasiKonfirmasiBtn) {
      farmasiKonfirmasiBtn.onclick = () => {
        const note = document.getElementById("farmasiNote") ? document.getElementById("farmasiNote").value : '';
        const paket = farmasiPaketList.map(x => ({...x, jenis:'paket'}));
        const tambahan = farmasiTambahanList.map(x => ({...x, jenis:'tambahan'}));
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'save_items', note, items: JSON.stringify([...paket,...tambahan]) }, "BMHP disimpan sementara");
      };
    }
    const farmasiRevisiBtn = document.getElementById("farmasiRevisiBtn");
    if (farmasiRevisiBtn) {
      farmasiRevisiBtn.onclick = () => {
        const note = document.getElementById("farmasiNote") ? document.getElementById("farmasiNote").value : '';
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'revisi', note }, "Permintaan revisi dikirim ke Nurse");
      };
    }
    const farmasiRevisiAfterDoneBtn = document.getElementById("farmasiRevisiAfterDoneBtn");
    if (farmasiRevisiAfterDoneBtn) {
      farmasiRevisiAfterDoneBtn.onclick = () => {
        const note = document.getElementById("farmasiNote") ? document.getElementById("farmasiNote").value : '';
        submitAction('{{ route("cases.farmasi", $case->id) }}', { action: 'revisi_after_done', note }, "Farmasi membuka revisi paket BMHP");
      };
    }

    const cmSetujuBtn = document.getElementById("cmSetujuBtn");
    if (cmSetujuBtn) {
      cmSetujuBtn.onclick = () => {
        const note = document.getElementById("cmNote") ? document.getElementById("cmNote").value : '';
        const golongan = document.getElementById("cmGolongan") ? document.getElementById("cmGolongan").value : '';
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'setuju', note, golongan }, "Dokumen disetujui Case Manager!");
      };
    }
    const cmRevisiBtn = document.getElementById("cmRevisiBtn");
    if (cmRevisiBtn) {
      cmRevisiBtn.onclick = () => {
        const note = document.getElementById("cmNote") ? document.getElementById("cmNote").value : '';
        const target = document.getElementById("cmReturnTo") ? document.getElementById("cmReturnTo").value : 'Nurse';
        const golongan = document.getElementById("cmGolongan") ? document.getElementById("cmGolongan").value : '';
        if (!note.trim()) { toast('Catatan instruksi wajib diisi saat revisi', 'error'); return; }
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'revisi', returnTo: target, note, golongan }, "Revisi dikirim ke " + target);
      };
    }
    const cmBelumLengkapBtn = document.getElementById("cmBelumLengkapBtn");
    if (cmBelumLengkapBtn) {
      cmBelumLengkapBtn.onclick = () => {
        const note = document.getElementById("cmNote") ? document.getElementById("cmNote").value : '';
        const golongan = document.getElementById("cmGolongan") ? document.getElementById("cmGolongan").value : '';
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'dokbelumlengkap', note, golongan }, "Status dokumen belum lengkap dikonfirmasi");
      };
    }

    // CM Toggle Panels
    const cmEditDataToggleBtn = document.getElementById("cmEditDataToggleBtn");
    const cmEditDataPanel = document.getElementById("cmEditDataPanel");
    if (cmEditDataToggleBtn && cmEditDataPanel) {
      cmEditDataToggleBtn.onclick = () => {
        cmEditDataPanel.style.display = cmEditDataPanel.style.display === 'none' ? 'block' : 'none';
        cmEditDataToggleBtn.textContent = cmEditDataPanel.style.display === 'none' ? ' - -  Edit Identitas / Nama Tindakan' : '^ Tutup Editor';
      };
    }
    const cmSaveDataBtn = document.getElementById("cmSaveDataBtn");
    if (cmSaveDataBtn) {
      cmSaveDataBtn.onclick = () => {
        const nama = document.getElementById("cmNama").value;
        const rm = document.getElementById("cmRm").value;
        const tglLahir = document.getElementById("cmTglLahir").value;
        const diagnosis = document.getElementById("cmDiagnosis").value;
        const tindakan = document.getElementById("cmTindakan").value.split('\n').filter(x => x.trim());
        submitAction('{{ route("cases.case-manager", $case->id) }}', { action: 'save_data', nama, rm, tgl_lahir: tglLahir, diagnosis, tindakan: JSON.stringify(tindakan) }, "Data pasien diperbarui oleh Case Manager");
      };
    }
    const cmEditEstimasiToggleBtn = document.getElementById("cmEditEstimasiToggleBtn");
    const cmEstimasiPanel = document.getElementById("cmEstimasiPanel");
    if (cmEditEstimasiToggleBtn && cmEstimasiPanel) {
      cmEditEstimasiToggleBtn.onclick = () => {
        const isHidden = cmEstimasiPanel.style.display === 'none';
        cmEstimasiPanel.style.display = isHidden ? 'block' : 'none';
        cmEditEstimasiToggleBtn.textContent = isHidden ? '^ Tutup Editor Estimasi' : ' - -  Edit Golongan / Kelas / Estimasi / BMHP / Alat';
        if (isHidden) initCmEstimasiPanel();
      };
    }

    // CM Full Estimasi Panel
    let cmJasaList = {!! json_encode($cmJasaRincian) !!};
    let cmBmhpList = {!! json_encode($cmBmhpData) !!};
    let cmAlatList = {!! json_encode($cmAlatData) !!};
    let cmEstDirty = false;

    function renderCmEstimasi() {
      // Jasa
      const jasaBody = document.getElementById('cmJasaBody');
      if (jasaBody) {
        jasaBody.innerHTML = '';
        let jasaTotal = 0;
        cmJasaList.forEach((r, i) => {
          jasaTotal += Number(r.nilai)||0;
          const tr = document.createElement('tr');
          tr.innerHTML = `<td><input class="form-control" style="width:100%;padding:3px 6px" value="${escHtml(r.komponen||'')}"></td><td style="text-align:right"><input class="form-control" type="number" style="width:130px;text-align:right;padding:3px 6px" value="${r.nilai||0}"></td><td><button type="button" class="btn btn-sm btn-danger">x</button></td>`;
          tr.querySelector('input:first-of-type').oninput = e => { cmJasaList[i].komponen=e.target.value; cmEstDirty=true; showCmSave(); };
          tr.querySelector('input[type=number]').oninput = e => { cmJasaList[i].nilai=Number(e.target.value)||0; cmEstDirty=true; showCmSave(); updateCmTotals(); };
          tr.querySelector('button').onclick = () => { cmJasaList.splice(i,1); renderCmEstimasi(); cmEstDirty=true; showCmSave(); };
          jasaBody.appendChild(tr);
        });
        const tc = document.getElementById('cmJasaTotalCell'); if(tc) tc.textContent = rupiah(jasaTotal);
      }
      // BMHP
      const bmhpBody = document.getElementById('cmBmhpBody');
      if (bmhpBody) {
        bmhpBody.innerHTML = '';
        let bmhpTotal = 0;
        cmBmhpList.forEach((it, i) => {
          bmhpTotal += (Number(it.h)||0)*(Number(it.q)||1);
          const tr = document.createElement('tr');
          tr.innerHTML = `<td><input class="form-control" style="width:100%;padding:3px 6px" value="${escHtml(it.n||'')}"></td><td><input class="form-control" type="number" style="width:60px;padding:3px 6px" value="${it.q||1}"></td><td style="text-align:right"><input class="form-control" type="number" style="width:120px;text-align:right;padding:3px 6px" value="${it.h||0}"></td><td><button type="button" class="btn btn-sm btn-danger">x</button></td>`;
          const [inN,inQ,inH,btnDel] = tr.querySelectorAll('input,button');
          inN.oninput = e => { cmBmhpList[i].n=e.target.value; cmEstDirty=true; showCmSave(); };
          inQ.oninput = e => { cmBmhpList[i].q=Number(e.target.value)||1; cmEstDirty=true; showCmSave(); updateCmTotals(); };
          inH.oninput = e => { cmBmhpList[i].h=Number(e.target.value)||0; cmEstDirty=true; showCmSave(); updateCmTotals(); };
          btnDel.onclick = () => { cmBmhpList.splice(i,1); renderCmEstimasi(); cmEstDirty=true; showCmSave(); };
          bmhpBody.appendChild(tr);
        });
        const tc = document.getElementById('cmBmhpTotalCell'); if(tc) tc.textContent = rupiah(bmhpTotal);
      }
      // Alat
      const alatBody = document.getElementById('cmAlatBody');
      if (alatBody) {
        alatBody.innerHTML = '';
        let alatTotal = 0;
        cmAlatList.forEach((a, i) => {
          alatTotal += Number(a.harga)||0;
          const tr = document.createElement('tr');
          tr.innerHTML = `<td><input class="form-control" style="width:100%;padding:3px 6px" value="${escHtml(a.nama||'')}"></td><td style="text-align:right"><input class="form-control" type="number" style="width:130px;text-align:right;padding:3px 6px" value="${a.harga||0}"></td><td><button type="button" class="btn btn-sm btn-danger">x</button></td>`;
          tr.querySelector('input:first-of-type').oninput = e => { cmAlatList[i].nama=e.target.value; cmEstDirty=true; showCmSave(); };
          tr.querySelector('input[type=number]').oninput = e => { cmAlatList[i].harga=Number(e.target.value)||0; cmEstDirty=true; showCmSave(); updateCmTotals(); };
          tr.querySelector('button').onclick = () => { cmAlatList.splice(i,1); renderCmEstimasi(); cmEstDirty=true; showCmSave(); };
          alatBody.appendChild(tr);
        });
        const tc = document.getElementById('cmAlatTotalCell'); if(tc) tc.textContent = rupiah(alatTotal);
      }
      updateCmTotals();
    }
    function updateCmTotals() {
      const jt = cmJasaList.reduce((s,r)=>s+(Number(r.nilai)||0),0);
      const bt = cmBmhpList.reduce((s,it)=>s+(Number(it.h)||0)*(Number(it.q)||1),0);
      const at = cmAlatList.reduce((s,a)=>s+(Number(a.harga)||0),0);
      const tc=document.getElementById('cmJasaTotalCell'); if(tc)tc.textContent=rupiah(jt);
      const bc=document.getElementById('cmBmhpTotalCell'); if(bc)bc.textContent=rupiah(bt);
      const ac=document.getElementById('cmAlatTotalCell'); if(ac)ac.textContent=rupiah(at);
      const gc=document.getElementById('cmGrandTotalCell'); if(gc)gc.textContent=rupiah(jt+bt+at);
    }
    function showCmSave() {
      const btn=document.getElementById('cmEstimasiSaveBtn'); if(btn)btn.style.display='';
    }
    function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    function initCmEstimasiPanel() { renderCmEstimasi(); }

    const cmJasaAddBtn = document.getElementById('cmJasaAddBtn');
    if (cmJasaAddBtn) cmJasaAddBtn.onclick = () => { cmJasaList.push({komponen:'',nilai:0}); renderCmEstimasi(); cmEstDirty=true; showCmSave(); };
    const cmBmhpAddBtn = document.getElementById('cmBmhpAddBtn');
    if (cmBmhpAddBtn) cmBmhpAddBtn.onclick = () => { cmBmhpList.push({n:'',h:0,q:1}); renderCmEstimasi(); cmEstDirty=true; showCmSave(); };
    const cmAlatAddBtn = document.getElementById('cmAlatAddBtn');
    if (cmAlatAddBtn) cmAlatAddBtn.onclick = () => { cmAlatList.push({nama:'',harga:0}); renderCmEstimasi(); cmEstDirty=true; showCmSave(); };

    const cmEstimasiSaveBtn = document.getElementById('cmEstimasiSaveBtn');
    if (cmEstimasiSaveBtn) {
      cmEstimasiSaveBtn.onclick = () => {
        const gol = document.getElementById('cmGolongan') ? document.getElementById('cmGolongan').value : '';
        const kelas = document.getElementById('cmKelas') ? document.getElementById('cmKelas').value : '';
        submitAction('{{ route("cases.case-manager", $case->id) }}', {
          action: 'save_estimasi',
          golongan: gol, kelas,
          jasa_rincian: JSON.stringify(cmJasaList),
          bmhp_items: JSON.stringify(cmBmhpList),
          alat_items: JSON.stringify(cmAlatList)
        }, "Golongan, Kelas, Estimasi, BMHP & Alat diperbarui oleh CM");
      };
    }
    const CM_GUARANTOR_NAME = {!! $namaPenjaminJs !!};
    if (document.getElementById('cmGolongan')) {
      document.getElementById('cmGolongan').onchange = (e) => {
        const gol=e.target.value; const kelas=document.getElementById('cmKelas')?document.getElementById('cmKelas').value:'';
        const resolved=resolveKelompokTarif(CM_GUARANTOR_NAME);
        const ratesGroup=MASTER_TARIF_DB[resolved.kelompokTarif]; const rates=ratesGroup?ratesGroup[gol]:null;
        if(rates){cmJasaList=rates.map(r=>({komponen:r.komponen,nilai:Number(r[classMapping[kelas]||'k3'])||0}));renderCmEstimasi();cmEstDirty=true;showCmSave();toast('Estimasi dihitung ulang dari database tarif','ok');}
        else toast('Golongan tidak ditemukan di tarif - isi manual','warn');
      };
    }
    if (document.getElementById('cmKelas')) {
      document.getElementById('cmKelas').onchange = (e) => {
        const kelas=e.target.value; const gol=document.getElementById('cmGolongan')?document.getElementById('cmGolongan').value:'';
        const resolved=resolveKelompokTarif(CM_GUARANTOR_NAME);
        const ratesGroup=MASTER_TARIF_DB[resolved.kelompokTarif]; const rates=ratesGroup?ratesGroup[gol]:null;
        if(rates){cmJasaList=rates.map(r=>({komponen:r.komponen,nilai:Number(r[classMapping[kelas]||'k3'])||0}));renderCmEstimasi();cmEstDirty=true;showCmSave();toast('Estimasi dihitung ulang dari database tarif','ok');}
      };
    }

    // CS buttons
    const csHubungiBtn = document.getElementById("csHubungiBtn");
    if (csHubungiBtn) {
      csHubungiBtn.onclick = () => {
        const note = document.getElementById("csNote") ? document.getElementById("csNote").value : '';
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'hubungi', note }, "Status CS dikonfirmasi");
      };
    }
    const csSetujuBtn = document.getElementById("csSetujuBtn");
    if (csSetujuBtn) {
      csSetujuBtn.onclick = () => {
        const note = document.getElementById("csNote") ? document.getElementById("csNote").value : '';
        if (!note.trim()) { toast('Catatan komunikasi wajib diisi', 'error'); return; }
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'disetujui', note }, "Pasien menyetujui - diteruskan ke Admin COT!");
      };
    }
    const csBatalBtn = document.getElementById("csBatalBtn");
    if (csBatalBtn) {
      csBatalBtn.onclick = () => {
        const note = document.getElementById("csNote") ? document.getElementById("csNote").value : '';
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'batal', note }, "Pasien membatalkan tindakan");
      };
    }
    const csRevisiBtn = document.getElementById("csRevisiBtn");
    if (csRevisiBtn) {
      csRevisiBtn.onclick = () => {
        const note = document.getElementById("csNote") ? document.getElementById("csNote").value : '';
        const target = document.getElementById("csReturnTo") ? document.getElementById("csReturnTo").value : 'CaseManager';
        submitAction('{{ route("cases.cs", $case->id) }}', { action: 'revisi', note, returnTo: target }, "Permintaan revisi dikirim ke " + target);
      };
    }

    // Admin COT buttons
    const adminPrelimBtn = document.getElementById("adminPrelimBtn");
    if (adminPrelimBtn) {
      adminPrelimBtn.onclick = () => {
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'prelim', alat: JSON.stringify(adminAlatList) }, "Prelim alat khusus disimpan!");
      };
    }
    const adminKonfirmasi1Btn = document.getElementById("adminKonfirmasi1Btn");
    if (adminKonfirmasi1Btn) {
      adminKonfirmasi1Btn.onclick = () => {
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'konfirmasi1', alat: JSON.stringify(adminAlatList) }, "Status alat dikonfirmasi");
      };
    }
    const adminSaveToolsBtn = document.getElementById("adminSaveToolsBtn");
    if (adminSaveToolsBtn) {
      adminSaveToolsBtn.onclick = () => {
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'save_tools', alat: JSON.stringify(adminAlatList) }, "Alat khusus berhasil disimpan!");
      };
    }
    const adminFinalBtn = document.getElementById("adminFinalBtn");
    if (adminFinalBtn) {
      adminFinalBtn.onclick = () => {
        const tgl = document.getElementById("adminTgl") ? document.getElementById("adminTgl").value : '';
        const jam = document.getElementById("adminJam") ? document.getElementById("adminJam").value : '';
        const ruang = document.getElementById("adminRuang") ? document.getElementById("adminRuang").value : '';
        const note = document.getElementById("adminNote") ? document.getElementById("adminNote").value : '';
        const estimasiJam = document.getElementById("adminEstimasiJam") ? document.getElementById("adminEstimasiJam").value : '';
        const dokterAnestesi = document.getElementById("adminDokterAnestesi") ? document.getElementById("adminDokterAnestesi").value : '';
        if (!tgl || !jam || !ruang) { toast("Tanggal, Jam, dan Ruang wajib diisi!", "error"); return; }
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'final', tanggal: tgl, jam, ruang, note, estimasi_jam: estimasiJam, dokter_anestesi: dokterAnestesi }, "Jadwal final operasi berhasil ditetapkan!");
      };
    }
    const adminKonfirmasi2Btn = document.getElementById("adminKonfirmasi2Btn");
    if (adminKonfirmasi2Btn) {
      adminKonfirmasi2Btn.onclick = () => {
        const note = document.getElementById("adminNote") ? document.getElementById("adminNote").value : '';
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'konfirmasi2', note }, "Jadwal dikonfirmasi (dalam proses)");
      };
    }
    const adminRevisiNurseBtn = document.getElementById("adminRevisiNurseBtn");
    if (adminRevisiNurseBtn) {
      adminRevisiNurseBtn.onclick = () => {
        const note = document.getElementById("adminNote") ? document.getElementById("adminNote").value : '';
        const target = document.getElementById("adminReturnTo") ? document.getElementById("adminReturnTo").value : 'AdminCOT';
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'revisi', returnTo: target, note }, "Permintaan revisi dikirim ke " + target);
      };
    }
    const adminAutoSuggestBtn = document.getElementById("adminAutoSuggestBtn");
    if (adminAutoSuggestBtn) {
      adminAutoSuggestBtn.onclick = () => {
        const resultEl = document.getElementById("adminAutoSuggestResult");
        if (resultEl) resultEl.innerHTML = '<span class="footer-hint"> - Fitur saran jadwal otomatis akan tersedia setelah integrasi dengan kalender OK...</span>';
      };
    }
    // Reschedule button (inside <details>)
    const adminRescheduleBtn = document.getElementById("adminRescheduleBtn");
    if (adminRescheduleBtn) {
      adminRescheduleBtn.onclick = () => {
        const tgl = document.getElementById("adminRescheduleTgl") ? document.getElementById("adminRescheduleTgl").value : '';
        const jam = document.getElementById("adminRescheduleJam") ? document.getElementById("adminRescheduleJam").value : '';
        const ruang = document.getElementById("adminRescheduleRuang") ? document.getElementById("adminRescheduleRuang").value : '';
        const note = document.getElementById("adminRescheduleNote") ? document.getElementById("adminRescheduleNote").value : '';
        const estJam = document.getElementById("adminRescheduleEstJam") ? document.getElementById("adminRescheduleEstJam").value : '';
        if (!tgl || !jam || !ruang) { toast("Tanggal, Jam, dan Ruang wajib diisi untuk reschedule!", "error"); return; }
        if (!note.trim()) { toast("Alasan reschedule wajib diisi!", "error"); return; }
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'reschedule', tanggal: tgl, jam, ruang, note, estimasi_jam: estJam }, "Jadwal berhasil di-reschedule!");
      };
    }
    const adminKonfirmasi3Btn = document.getElementById("adminKonfirmasi3Btn");
    if (adminKonfirmasi3Btn) {
      adminKonfirmasi3Btn.onclick = () => {
        submitAction('{{ route("cases.admin-cot", $case->id) }}', { action: 'konfirmasi3' }, "Status dikonfirmasi");
      };
    }

    // --- Farmasi BMHP Editor Logic (2 tabel: Paket & Tambahan) ---
    let farmasiPaketList = {!! json_encode($farmasiPaketData) !!};
    let farmasiTambahanList = {!! json_encode($farmasiTambahanData) !!};

    function buildFarmasiRow(item, idx, listRef, onDelete) {
      const tr = document.createElement('tr');
      const inpNama = document.createElement('input'); inpNama.type='text'; inpNama.value=item.nama||''; inpNama.style.cssText='width:100%;padding:4px 6px';
      const inpQty = document.createElement('input'); inpQty.type='number'; inpQty.step='any'; inpQty.value=item.qty||1; inpQty.style.cssText='width:100%;padding:4px 6px;text-align:right';
      const inpHarga = document.createElement('input'); inpHarga.type='number'; inpHarga.value=item.harga||0; inpHarga.style.cssText='width:100%;padding:4px 6px;text-align:right';
      const btnDel = document.createElement('button'); btnDel.type='button'; btnDel.className='btn btn-sm btn-danger'; btnDel.textContent='Hapus';
      inpNama.oninput = e => { listRef[idx].nama=e.target.value; updateFarmasiTotals(); };
      inpQty.oninput = e => { listRef[idx].qty=Number(e.target.value)||0; updateFarmasiTotals(); };
      inpHarga.oninput = e => { listRef[idx].harga=Number(e.target.value)||0; updateFarmasiTotals(); };
      btnDel.onclick = onDelete;
      const tdN=document.createElement('td'); tdN.appendChild(inpNama);
      const tdQ=document.createElement('td'); tdQ.appendChild(inpQty);
      const tdH=document.createElement('td'); tdH.appendChild(inpHarga);
      const tdA=document.createElement('td'); tdA.appendChild(btnDel);
      tr.appendChild(tdN); tr.appendChild(tdQ); tr.appendChild(tdH); tr.appendChild(tdA);
      // Autocomplete
      const bmhpSugg = (masterData&&masterData.paket_bmhp)?masterData.paket_bmhp.map(x=>x.nama):[];
      makeAutocomplete(inpNama, bmhpSugg, (sel) => {
        listRef[idx].nama=sel; inpNama.value=sel;
        const found=masterData.paket_bmhp&&masterData.paket_bmhp.find(x=>x.nama===sel);
        if(found){inpHarga.value=found.tarif;listRef[idx].harga=found.tarif;updateFarmasiTotals();}
      });
      return tr;
    }
    function renderFarmasiPaket() {
      const tbody = document.getElementById('farmasiPaketBody'); if(!tbody)return;
      tbody.innerHTML='';
      farmasiPaketList.forEach((item,i) => tbody.appendChild(buildFarmasiRow(item,i,farmasiPaketList,()=>{farmasiPaketList.splice(i,1);renderFarmasiPaket();updateFarmasiTotals();})));
    }
    function renderFarmasiTambahan() {
      const tbody = document.getElementById('farmasiTambahanBody'); if(!tbody)return;
      tbody.innerHTML='';
      farmasiTambahanList.forEach((item,i) => tbody.appendChild(buildFarmasiRow(item,i,farmasiTambahanList,()=>{farmasiTambahanList.splice(i,1);renderFarmasiTambahan();updateFarmasiTotals();})));
    }
    function updateFarmasiTotals() {
      const totalPaket=farmasiPaketList.reduce((s,it)=>s+(Number(it.qty)||0)*(Number(it.harga)||0),0);
      const totalTambahan=farmasiTambahanList.reduce((s,it)=>s+(Number(it.qty)||0)*(Number(it.harga)||0),0);
      const el=document.getElementById('farmasiGrandTotal'); if(el)el.textContent='Total: '+rupiah(totalPaket+totalTambahan);
      updateSummaryPanel();
    }
    const farmasiAddPaketBtn=document.getElementById('farmasiAddPaketBtn');
    if(farmasiAddPaketBtn) farmasiAddPaketBtn.onclick=()=>{farmasiPaketList.push({nama:'',qty:1,harga:0});renderFarmasiPaket();updateFarmasiTotals();};
    const farmasiAddTambahanBtn=document.getElementById('farmasiAddTambahanBtn');
    if(farmasiAddTambahanBtn) farmasiAddTambahanBtn.onclick=()=>{farmasiTambahanList.push({nama:'',qty:1,harga:0});renderFarmasiTambahan();updateFarmasiTotals();};
    renderFarmasiPaket();
    renderFarmasiTambahan();
    updateFarmasiTotals();


    // --- Admin COT Alat Editor Logic ---
    let adminAlatList = {!! json_encode($case->alat->map(function($a){
        return [
            'nama' => $a->nama,
            'harga' => $a->harga > 0 ? $a->harga : ($a->masterAlat ? $a->masterAlat->tarif : 0)
        ];
    })->toArray()) !!};

    fetch('{{ route("api.master-data") }}')
      .then(res => res.json())
      .then(data => {
        masterData = data;
        renderAdminAlat();
        renderFarmasiBmhp();
      });

    function renderAdminAlat() {
      const tbody = document.getElementById("adminAlatBody");
      if (!tbody) return;
      tbody.innerHTML = "";
      let grandTotal = 0;

      adminAlatList.forEach((item, idx) => {
        grandTotal += item.harga;

        const tr = document.createElement("tr");

        // Nama
        const tdNama = document.createElement("td");
        const inpNama = document.createElement("input");
        inpNama.type = "text";
        inpNama.value = item.nama;
        inpNama.style.width = "100%";
        inpNama.style.padding = "4px 6px";
        inpNama.addEventListener("input", (e) => item.nama = e.target.value);
        tdNama.appendChild(inpNama);

        // Harga
        const tdHarga = document.createElement("td");
        const inpHarga = document.createElement("input");
        inpHarga.type = "number";
        inpHarga.value = item.harga;
        inpHarga.style.width = "100%";
        inpHarga.style.padding = "4px 6px";
        inpHarga.style.textAlign = "right";
        inpHarga.addEventListener("input", (e) => {
          item.harga = Number(inpHarga.value) || 0;
          updateAdminAlatTotals();
        });
        tdHarga.appendChild(inpHarga);

        // Aksi (Delete)
        const tdAksi = document.createElement("td");
        const btnDel = document.createElement("button");
        btnDel.type = "button";
        btnDel.className = "btn btn-sm btn-danger";
        btnDel.textContent = "Hapus";
        btnDel.onclick = () => {
          adminAlatList.splice(idx, 1);
          renderAdminAlat();
        };
        tdAksi.appendChild(btnDel);

        tr.appendChild(tdNama);
        tr.appendChild(tdHarga);
        tr.appendChild(tdAksi);
        tbody.appendChild(tr);

        // Autocomplete
        makeAutocomplete(inpNama, masterData.alat, (selectedTool) => {
          item.nama = selectedTool;
          // Look up tool rate
          fetch(`/api/alat/lookup?nama=${encodeURIComponent(selectedTool)}`)
            .then(res => res.json())
            .then(resData => {
              if (resData.success && resData.tarif) {
                inpHarga.value = resData.tarif;
                item.harga = resData.tarif;
                updateAdminAlatTotals();
              }
            });
        });
      });

      document.getElementById("adminAlatGrandTotal").textContent = "Total: " + rupiah(grandTotal);
    }

    function updateAdminAlatTotals() {
      let grandTotal = 0;
      adminAlatList.forEach(item => grandTotal += item.harga);
      const el = document.getElementById("adminAlatGrandTotal");
      if (el) el.textContent = "Total: " + rupiah(grandTotal);
      updateSummaryPanel();
    }

    const adminAddAlatBtn = document.getElementById("adminAddAlatBtn");
    if (adminAddAlatBtn) {
      adminAddAlatBtn.onclick = () => {
        adminAlatList.push({ nama: "", harga: 0 });
        renderAdminAlat();
      };
    }

    // CS follow-up countdown timer script
    const csCountdownEl = document.getElementById("csCountdown");
    if (csCountdownEl) {
      const dueTime = new Date(csCountdownEl.dataset.due).getTime();
      const tick = () => {
        const now = Date.now();
        const diff = dueTime - now;
        if (diff <= 0) {
          csCountdownEl.innerHTML = `[!] - <strong>Follow-up Overdue!</strong> Hubungi pasien segera untuk konfirmasi kesediaan.`;
          csCountdownEl.style.background = "#FEE2E2";
          csCountdownEl.style.color = "#991B1B";
        } else {
          const hrs = Math.floor(diff / 3600000);
          const mins = Math.floor((diff % 3600000) / 60000);
          const secs = Math.floor((diff % 60000) / 1000);
          csCountdownEl.innerHTML = ` - -  <strong>Batas waktu follow-up berikutnya:</strong> ${hrs}j ${mins}m ${secs}d lagi (Ingatkan petugas shift berikutnya).`;
          csCountdownEl.style.background = "#E0F2FE";
          csCountdownEl.style.color = "#0369A1";
        }
      };
      tick();
      setInterval(tick, 1000);
    }

    // Attachment Center Upload Handler
    const attachmentFileInput = document.getElementById('attachmentFileInput');
    if (attachmentFileInput) {
      attachmentFileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        // Size validation (2MB max)
        if (file.size > 2 * 1024 * 1024) {
          toast('File terlalu besar. Maksimal 2 MB', 'error');
          this.value = '';
          return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        this.disabled = true;
        
        fetch('{{ route("cases.upload-attachment", $case->id) }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken
          },
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          this.disabled = false;
          this.value = '';
          if (data.success) {
            toast(data.message, 'success');
            refreshAttachmentsList(data.attachments);
          } else {
            toast(data.message, 'error');
          }
        })
        .catch(err => {
          this.disabled = false;
          this.value = '';
          toast('Gagal mengunggah berkas.', 'error');
        });
      });
    }

    // Attachment Center Delete Handler
    window.deleteAttachment = function(attId) {
      if (!confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) return;
      
      fetch('{{ route("cases.delete-attachment", $case->id) }}', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ attachment_id: attId })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          toast(data.message, 'success');
          refreshAttachmentsList(data.attachments);
        } else {
          toast(data.message, 'error');
        }
      })
      .catch(err => {
        toast('Gagal menghapus berkas.', 'error');
      });
    };

    // Refresh attachments DOM list
    window.refreshAttachmentsList = function(attachments) {
      const container = document.getElementById('attachmentsContainer');
      if (!container) return;
      
      container.innerHTML = '';
      
      if (attachments.length === 0) {
        container.innerHTML = `<div id="noAttachmentsHint" style="font-size:12px; color:var(--slate-400); text-align:center; padding:12px 0;">Belum ada dokumen yang diunggah.</div>`;
        return;
      }
      
      attachments.forEach(att => {
        const ext = att.name.split('.').pop().toLowerCase();
        const isPreviewable = ['png', 'jpg', 'jpeg', 'gif', 'pdf'].includes(ext);
        
        let previewBtn = '';
        if (isPreviewable) {
          previewBtn = `<button type="button" class="btn btn-sm btn-preview" onclick="previewAttachment('${att.path}', '${att.name}')" style="padding:4px 8px; font-size:11px;">Preview</button>`;
        }
        
        let deleteBtn = '';
        const isDraftOrReturned = ('{{ $case->status }}' === 'Draft' || '{{ $case->status }}' === 'Returned');
        const isNurse = ('{{ $activeRole }}' === 'Nurse');
        if (isDraftOrReturned && isNurse) {
          deleteBtn = `<button type="button" class="btn btn-sm btn-danger btn-delete-att" onclick="deleteAttachment('${att.id}')" style="padding:4px 8px; font-size:11px;">Hapus</button>`;
        }
        
        const itemHtml = `
          <div class="attachment-item" data-id="${att.id}" style="display:flex; justify-content:space-between; align-items:center; background:var(--white); border:1px solid var(--slate-200); padding:8px 12px; border-radius:6px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-size:16px;">📄</span>
              <a href="${att.path}" target="_blank" class="att-name-link" style="font-weight:600; font-size:12.5px; color:var(--primary-700); text-decoration:none; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                ${att.name}
              </a>
            </div>
            <div style="display:flex; gap:6px;">
              ${previewBtn}
              <a href="${att.path}" download="${att.name}" class="btn btn-sm" style="padding:4px 8px; font-size:11px; text-decoration:none; display:inline-block; line-height:1.2;">Download</a>
              ${deleteBtn}
            </div>
          </div>
        `;
        
        container.insertAdjacentHTML('beforeend', itemHtml);
      });
    };

    // Preview modal actions
    window.previewAttachment = function(path, name) {
      const modal = document.getElementById('previewModal');
      const title = document.getElementById('previewModalTitle');
      const body = document.getElementById('previewModalBody');
      
      title.textContent = "Pratinjau: " + name;
      body.innerHTML = '';
      
      const ext = name.split('.').pop().toLowerCase();
      if (ext === 'pdf') {
        body.innerHTML = `<iframe src="${path}" style="width:100%; height:100%; border:none;"></iframe>`;
      } else {
        body.innerHTML = `<img src="${path}" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">`;
      }
      
      modal.style.display = 'flex';
    };
    
    window.closePreviewModal = function() {
      document.getElementById('previewModal').style.display = 'none';
      document.getElementById('previewModalBody').innerHTML = '';
    };
  </script>
@endsection
