@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Role & Status Reference')
@section('page_title', 'Role & Status Reference')

@section('content')
  <div class="card">
    <h3>Role &amp; Hak Akses</h3>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Role</th>
            <th>Dapat melakukan</th>
            <th>Tidak dapat melakukan</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Nurse (Entry Point)</strong></td>
            <td>Membuat pengajuan, edit saat Draft, submit, memantau dashboard.</td>
            <td style="color:var(--red-500)">Mengubah data setelah submit kecuali status Returned.</td>
          </tr>
          <tr>
            <td><strong>VA (Asuransi)</strong></td>
            <td>Verifikasi administrasi asuransi, estimasi biaya, kelengkapan dokumen (khusus Penjamin=Asuransi).</td>
            <td style="color:var(--red-500)">Menangani pasien Umum.</td>
          </tr>
          <tr>
            <td><strong>Kasir (Umum)</strong></td>
            <td>Administrasi/pembayaran pasien umum secara paralel dengan ADRU COT.</td>
            <td style="color:var(--red-500)">Menangani pasien Asuransi.</td>
          </tr>
          <tr>
            <td><strong>ADRU COT (Umum)</strong></td>
            <td>Estimasi biaya, konfirmasi pasien umum, meneruskan ke Admin COT tanpa CS.</td>
            <td style="color:var(--red-500)">Menangani pasien Asuransi.</td>
          </tr>
          <tr>
            <td><strong>Farmasi</strong></td>
            <td>Review paket obat/BMHP, tambah/hapus item, approval.</td>
            <td style="color:var(--red-500)">Input paket dari nol tanpa data database.</td>
          </tr>
          <tr>
            <td><strong>Admin COT</strong></td>
            <td>Menentukan kebutuhan alat (awal) dan jadwal final operasi (khusus Lokasi=COT).</td>
            <td style="color:var(--red-500)">Approval estimasi biaya / dokumen asuransi.</td>
          </tr>
          <tr>
            <td><strong>Case Manager</strong></td>
            <td>Review &amp; approval estimasi, BMHP, alat; lengkapi LMA/CL; revisi ke VA/Kasir/ADRU COT.</td>
            <td style="color:var(--red-500)">Mengisi data pasien awal.</td>
          </tr>
          <tr>
            <td><strong>Customer Service</strong></td>
            <td>Menghubungi &amp; konfirmasi jadwal pasien Asuransi, teruskan ke Admin COT.</td>
            <td style="color:var(--red-500)">Menangani pasien Umum (ditangani ADRU COT).</td>
          </tr>
          <tr>
            <td><strong>Viewer (Hanya Lihat)</strong></td>
            <td>Memantau seluruh proses berjalan, melihat keterangan tiap unit, dan estimasi (jasa medis &amp; total).</td>
            <td style="color:var(--red-500)">Membuat/mengubah pengajuan, melakukan aksi apa pun, atau melihat rincian harga BMHP.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>Status Pengajuan — Level Nurse</h3>
    <div style="overflow-x:auto;">
      <table style="margin-top:12px;">
        <tbody>
          <tr><td><span class="badge-status st-Draft">Draft</span></td><td>Sedang diinput oleh Nurse.</td></tr>
          <tr><td><span class="badge-status st-Menunggu">Submitted</span></td><td>Pengajuan telah dikirim ke workflow.</td></tr>
          <tr><td><span class="badge-status st-Revisi">Returned</span></td><td>Dikembalikan ke Nurse untuk diperbaiki.</td></tr>
          <tr><td><span class="badge-status st-Disetujui">InProgress</span></td><td>Sedang diproses unit terkait.</td></tr>
          <tr><td><span class="badge-status st-Completed">Completed</span></td><td>Seluruh proses administrasi selesai.</td></tr>
          <tr><td><span class="badge-status st-Batal">Cancelled</span></td><td>Pengajuan dibatalkan.</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>Status Unit Terkait (VA/Kasir/ADRU COT/Farmasi/Admin COT)</h3>
    <div class="btn-row" style="margin-bottom:12px;">
      <span class="badge-status st-Menunggu">Menunggu</span>
      <span class="badge-status st-Pending">Pending</span>
      <span class="badge-status st-DalamKonfirmasi">Dalam Konfirmasi</span>
      <span class="badge-status st-Approved">Approved</span>
      <span class="badge-status st-Revisi">Revisi</span>
      <span class="badge-status st-Ditolak">Ditolak</span>
      <span class="badge-status st-Terjadwal">Terjadwal</span>
      <span class="badge-status st-Reschedule">Reschedule</span>
      <span class="badge-status st-Selesai">Selesai</span>
      <span class="badge-status st-Batal">Batal</span>
    </div>
  </div>

  <div class="card">
    <h3>Status Case Manager</h3>
    <div style="overflow-x:auto;">
      <table>
        <tbody>
          <tr><td><span class="badge-status st-Disetujui">Disetujui</span></td><td>Estimasi &amp; dokumen lengkap → lanjut ke VA/CS (Asuransi) atau Kasir/ADRU COT (Umum).</td></tr>
          <tr><td><span class="badge-status st-Revisi">Revisi</span></td><td>Dikembalikan ke unit sebelumnya untuk perbaikan.</td></tr>
          <tr><td><span class="badge-status st-DalamKonfirmasi">Dalam Konfirmasi</span></td><td>Menunggu keputusan operator terkait excess/BMHP atau unit lain.</td></tr>
          <tr><td><span class="badge-status st-DokumenBelumLengkap">Dokumen Belum Lengkap</span></td><td>Dokumen asuransi/administrasi belum lengkap.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
@endsection
