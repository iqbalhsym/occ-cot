@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Penafian')
@section('page_title', 'Penafian')

@section('content')
<div class="card" style="max-width: 800px; margin: 0 auto; line-height: 1.6;">
  <div style="text-align: center; margin-bottom: 24px;">
    <span style="font-size: 48px;">⚠️</span>
    <h3 style="margin-top: 12px; color: var(--primary-900);">Penafian & Batasan Sistem</h3>
  </div>

  <p>Selamat datang di <strong>Hospital Action Interface Care (OCC-COT)</strong>. Harap perhatikan beberapa ketentuan penting berikut mengenai estimasi biaya dan alur penjadwalan tindakan Central Operating Theatre (COT):</p>

  <hr style="border: 0; border-top: 1px solid var(--slate-200); margin: 20px 0;">

  <h4 style="color: var(--primary-800); margin-top: 16px;">1. Estimasi Bersifat Perkiraan Awal</h4>
  <p>Seluruh angka biaya tindakan, jasa medis, alat khusus, dan BMHP yang ditampilkan oleh sistem ini merupakan <strong>estimasi atau perkiraan awal</strong> berdasarkan Master Tarif SK Direksi RSUI dan usulan item tindakan medis. Biaya aktual/riil yang ditagihkan kepada pasien akan mengikuti tindakan dan bahan medis habis pakai (BMHP) aktual yang digunakan selama operasi berlangsung di dalam kamar bedah.</p>

  <h4 style="color: var(--primary-800); margin-top: 16px;">2. Batasan Ruang Lingkup Biaya</h4>
  <p>Estimasi biaya tindakan yang dikeluarkan oleh sistem <strong>belum termasuk</strong> komponen biaya berikut:</p>
  <ul>
    <li>Sewa kamar perawatan pasca bedah (misal: Ruang Rawat Inap, ICU, PICU, dll).</li>
    <li>Obat-obatan pulang atau terapi pemulihan rawat jalan setelah operasi.</li>
    <li>Komplikasi medis tidak terduga selama operasi yang membutuhkan tindakan tambahan darurat.</li>
  </ul>

  <h4 style="color: var(--primary-800); margin-top: 16px;">3. Penentuan Jadwal Final Operasi</h4>
  <p>Jadwal tanggal dan jam operasi yang diajukan oleh Nurse melalui sistem ini adalah opsi pilihan prioritas. Keputusan jadwal final, alokasi slot kamar bedah, dan ketersediaan instrumen berada di bawah wewenang penuh <strong>Admin COT</strong> setelah berkoordinasi dengan tim medis terkait.</p>

  <h4 style="color: var(--primary-800); margin-top: 16px;">4. Keberlakuan Data Offline</h4>
  <p>Sistem ini menyinkronkan data database tarif resmi secara periodik. Jika terdapat perbedaan data antara sistem ini dengan billing system utama rumah sakit (SIMRS), maka acuan billing SIMRS utama yang berlaku resmi.</p>

  <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 8px; padding: 16px; margin-top: 30px; text-align: center; font-size: 13px; color: var(--slate-600);">
    <strong>Operation Command Center COT</strong> &bull; Rumah Sakit Universitas Indonesia
  </div>
</div>
@endsection
