@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Manajemen Data Dokter')
@section('page_title', 'Manajemen Data Dokter')

@section('content')
  @php
    $currentUser = Auth::user();
    $isAdminOrSuper = in_array($currentUser->role, ['SuperAdmin', 'Administrator']);
  @endphp

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
      <div>
        <h3 style="margin:0 0 4px 0;">Manajemen Data Master Dokter</h3>
        <p class="hint" style="margin:0;">
          Daftar dokter yang terintegrasi dengan autocomplete form penjadwalan DPJP dan Operator.
        </p>
      </div>
      <div>
        @if($isAdminOrSuper)
          <button type="button" class="btn btn-primary" id="addDoctorBtn">+ Tambah Dokter Baru</button>
        @endif
      </div>
    </div>

    <!-- Search & Filter -->
    <div style="margin-bottom:18px; display:flex; gap:8px;">
      <form action="{{ route('admin.doctors') }}" method="GET" style="display:flex; gap:8px; width:100%; max-width:500px;">
        <input type="text" name="search" class="form-control" placeholder="Cari dokter berdasarkan nama, KSM, spesialis, dll..." value="{{ request('search') }}" style="flex:1;">
        <button type="submit" class="btn btn-primary">Cari</button>
        @if(request('search'))
          <a href="{{ route('admin.doctors') }}" class="btn" style="background:var(--slate-200); color:var(--slate-700); text-decoration:none; display:flex; align-items:center;">Reset</a>
        @endif
      </form>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Nama Dokter</th>
            <th>Nama Dokter + Gelar</th>
            <th>KSM</th>
            <th>Spesialis</th>
            <th>Konsultan</th>
            <th>Status</th>
            @if($isAdminOrSuper)
              <th style="width:120px; text-align:center;">Aksi</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($doctors as $idx => $d)
            <tr id="row-doc-{{ $d->id }}">
              <td style="color:var(--slate-400); font-size:12px;">{{ $doctors->firstItem() + $idx }}</td>
              <td><strong>{{ $d->nama }}</strong></td>
              <td>{{ $d->nama_gelar }}</td>
              <td><span style="font-size:12.5px;">{{ $d->ksm ?: '-' }}</span></td>
              <td><span style="font-size:12.5px;">{{ $d->spesialis ?: '-' }}</span></td>
              <td><span style="font-size:12.5px; color:var(--slate-600);">{{ $d->konsultan ?: '-' }}</span></td>
              <td>
                @if($d->status)
                  <span class="chip" style="font-size:10.5px; padding:2px 6px; background:var(--primary-100); color:var(--primary-800);">
                    {{ $d->status }}
                  </span>
                @else
                  -
                @endif
              </td>
              @if($isAdminOrSuper)
                <td style="text-align:center;">
                  <div style="display:flex; gap:6px; justify-content:center;">
                    <button type="button" class="btn btn-sm edit-doc-btn" 
                      data-id="{{ $d->id }}"
                      data-nama="{{ $d->nama }}"
                      data-nama_gelar="{{ $d->nama_gelar }}"
                      data-ksm="{{ $d->ksm }}"
                      data-spesialis="{{ $d->spesialis }}"
                      data-konsultan="{{ $d->konsultan }}"
                      data-status="{{ $d->status }}"
                      style="background:var(--slate-200); color:var(--slate-800); border-color:var(--slate-300);">
                      Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-doc-btn" 
                      data-id="{{ $d->id }}"
                      data-nama="{{ $d->nama }}">
                      Hapus
                    </button>
                  </div>
                </td>
              @endif
            </tr>
          @endforeach
          @if($doctors->isEmpty())
            <tr class="empty-row">
              <td colspan="{{ $isAdminOrSuper ? 8 : 7 }}" style="text-align:center; padding:30px;">
                Belum ada data dokter terdaftar.
              </td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    @if($doctors instanceof \Illuminate\Pagination\LengthAwarePaginator && $doctors->hasPages())
      <div class="pagination-wrapper" style="margin-top:20px;">
        <div class="pagination-info">
          Menampilkan {{ $doctors->firstItem() }} - {{ $doctors->lastItem() }} dari {{ $doctors->total() }} data
        </div>
        <div class="pagination-links">
          @if ($doctors->onFirstPage())
            <span class="disabled">&laquo;</span>
          @else
            <a href="{{ $doctors->appends(request()->query())->previousPageUrl() }}">&laquo;</a>
          @endif

          @foreach ($doctors->getUrlRange(max(1, $doctors->currentPage() - 2), min($doctors->lastPage(), $doctors->currentPage() + 2)) as $page => $url)
            @if ($page == $doctors->currentPage())
              <span class="active">{{ $page }}</span>
            @else
              <a href="{{ $doctors->appends(request()->query())->url($page) }}">{{ $page }}</a>
            @endif
          @endforeach

          @if ($doctors->hasMorePages())
            <a href="{{ $doctors->appends(request()->query())->nextPageUrl() }}">&raquo;</a>
          @else
            <span class="disabled">&raquo;</span>
          @endif
        </div>
      </div>
    @endif
  </div>

  <!-- Form Modal (Add / Edit) -->
  <div id="doctorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(3px); z-index:9999; justify-content:center; align-items:center; padding:15px;">
    <div class="card" style="width: 500px; max-width:100%; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); background:var(--white); padding:20px; animation: modalFadeIn 0.2s ease-out;">
      <h3 id="modalTitle" style="margin-top:0; margin-bottom:15px; font-weight:700; color:var(--primary-900);">Tambah Dokter Baru</h3>
      
      <form id="doctorForm">
        <input type="hidden" id="editDoctorId">
        
        <div class="field" style="margin-bottom:12px;">
          <label class="req" style="font-weight:600; margin-bottom:4px; display:block;">Nama Dokter (tanpa gelar)</label>
          <input type="text" id="docName" class="form-control" required placeholder="mis. Dwi Ariawan" style="width:100%;">
        </div>
        
        <div class="field" style="margin-bottom:12px;">
          <label class="req" style="font-weight:600; margin-bottom:4px; display:block;">Nama Dokter + Gelar</label>
          <input type="text" id="docNameGelar" class="form-control" required placeholder="mis. Dr. Dwi Ariawan, drg., Sp.B.M.M." style="width:100%;">
        </div>
        
        <div class="field" style="margin-bottom:12px;">
          <label style="font-weight:600; margin-bottom:4px; display:block;">KSM</label>
          <input type="text" id="docKsm" class="form-control" placeholder="mis. Dokter Gigi Spesialis Bedah Mulut" style="width:100%;">
        </div>
        
        <div class="field" style="margin-bottom:12px;">
          <label style="font-weight:600; margin-bottom:4px; display:block;">Spesialis</label>
          <input type="text" id="docSpesialis" class="form-control" placeholder="mis. Dokter Gigi Spesialis Bedah Mulut" style="width:100%;">
        </div>
        
        <div class="field" style="margin-bottom:12px;">
          <label style="font-weight:600; margin-bottom:4px; display:block;">Konsultan</label>
          <input type="text" id="docKonsultan" class="form-control" placeholder="mis. Konsultan Oral & Maxillofacial Cleft" style="width:100%;">
        </div>
        
        <div class="field" style="margin-bottom:12px;">
          <label style="font-weight:600; margin-bottom:4px; display:block;">Status</label>
          <input type="text" id="docStatus" class="form-control" placeholder="mis. SPESIALIS GIGI PART TIME" style="width:100%;">
        </div>
        
        <div class="btn-row" style="margin-top:20px; display:flex; justify-content:flex-end; gap:8px;">
          <button type="button" class="btn" id="closeModalBtn" style="background:var(--slate-100); color:var(--slate-700); border-color:var(--slate-300);">Batal</button>
          <button type="submit" class="btn btn-primary" id="saveDoctorBtn">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
@endsection

@section('scripts')
  <script>
    const modal = document.getElementById("doctorModal");
    const docForm = document.getElementById("doctorForm");
    const editIdInput = document.getElementById("editDoctorId");
    
    const docName = document.getElementById("docName");
    const docNameGelar = document.getElementById("docNameGelar");
    const docKsm = document.getElementById("docKsm");
    const docSpesialis = document.getElementById("docSpesialis");
    const docKonsultan = document.getElementById("docKonsultan");
    const docStatus = document.getElementById("docStatus");

    // Open Add Doctor Modal
    const addBtn = document.getElementById("addDoctorBtn");
    if (addBtn) {
      addBtn.onclick = () => {
        document.getElementById("modalTitle").textContent = "Tambah Dokter Baru";
        editIdInput.value = "";
        docForm.reset();
        modal.style.display = "flex";
      };
    }

    // Close Modal
    const closeBtn = document.getElementById("closeModalBtn");
    if (closeBtn) {
      closeBtn.onclick = () => {
        modal.style.display = "none";
      };
    }
    
    // Close modal when clicking outside form card
    modal.onclick = (e) => {
      if (e.target === modal) {
        modal.style.display = "none";
      }
    };

    // Open Edit Doctor Modal
    document.querySelectorAll(".edit-doc-btn").forEach(btn => {
      btn.onclick = function() {
        document.getElementById("modalTitle").textContent = "Edit Detail Dokter";
        editIdInput.value = this.dataset.id;
        docName.value = this.dataset.nama || '';
        docNameGelar.value = this.dataset.nama_gelar || '';
        docKsm.value = this.dataset.ksm || '';
        docSpesialis.value = this.dataset.spesialis || '';
        docKonsultan.value = this.dataset.konsultan || '';
        docStatus.value = this.dataset.status || '';
        modal.style.display = "flex";
      };
    });

    // Form Submit (Add / Edit) via AJAX
    docForm.onsubmit = (e) => {
      e.preventDefault();
      
      const id = editIdInput.value;
      const url = id ? `/admin/doctors/${id}` : '/admin/doctors';
      const method = id ? 'PUT' : 'POST';
      
      const payload = {
        nama: docName.value,
        nama_gelar: docNameGelar.value,
        ksm: docKsm.value,
        spesialis: docSpesialis.value,
        konsultan: docKonsultan.value,
        status: docStatus.value
      };

      const submitBtn = document.getElementById("saveDoctorBtn");
      submitBtn.disabled = true;
      submitBtn.textContent = "Menyimpan...";

      fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = "Simpan";
        if (data.success) {
          modal.style.display = "none";
          toast(data.message, "success");
          setTimeout(() => window.location.reload(), 1000);
        } else {
          toast(data.message || "Gagal menyimpan data", "error");
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = "Simpan";
        toast("Terjadi kesalahan sistem", "error");
      });
    };

    // Delete Doctor via AJAX
    document.querySelectorAll(".delete-doc-btn").forEach(btn => {
      btn.onclick = function() {
        const id = this.dataset.id;
        const name = this.dataset.nama;
        
        if (confirm(`Apakah Anda yakin ingin menghapus dokter "${name}"?`)) {
          this.disabled = true;
          
          fetch(`/admin/doctors/${id}`, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': csrfToken
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              toast(data.message, "success");
              const row = document.getElementById(`row-doc-${id}`);
              if (row) row.remove();
            } else {
              this.disabled = false;
              toast(data.message || "Gagal menghapus dokter", "error");
            }
          })
          .catch(() => {
            this.disabled = false;
            toast("Terjadi kesalahan koneksi", "error");
          });
        }
      };
    });
  </script>
@endsection
