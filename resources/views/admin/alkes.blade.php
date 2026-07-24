@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Manajemen Alkes Khusus')
@section('page_title', 'Manajemen Alkes Khusus')

@section('content')
  @php
    $currentUser = Auth::user();
    $isAdminOrSuper = in_array($currentUser->role, ['SuperAdmin', 'Administrator']);
  @endphp

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
      <div>
        <h3 style="margin:0 0 4px 0;">Manajemen Data Master Alkes Khusus</h3>
        <p class="hint" style="margin:0;">
          Daftar alat medis khusus (alkes khusus) dengan pengaturan pembatasan ketersediaan ruangan tindakan.
        </p>
      </div>
      <div>
        @if($isAdminOrSuper)
          <button type="button" class="btn btn-primary" id="addAlkesBtn">+ Tambah Alkes Khusus</button>
        @endif
      </div>
    </div>

    <!-- Search & Filter -->
    <div style="margin-bottom:18px; display:flex; gap:8px;">
      <form action="{{ route('admin.alkes') }}" method="GET" style="display:flex; gap:8px; width:100%; max-width:500px;">
        <input type="text" name="search" class="form-control" placeholder="Cari alkes khusus..." value="{{ request('search') }}" style="flex:1;">
        <button type="submit" class="btn btn-primary">Cari</button>
        @if(request('search'))
          <a href="{{ route('admin.alkes') }}" class="btn" style="background:var(--slate-200); color:var(--slate-700); text-decoration:none; display:flex; align-items:center;">Reset</a>
        @endif
      </form>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Nama Alkes Khusus</th>
            <th>Keterangan Aturan (Excel)</th>
            <th>Ruangan yang Diizinkan (Allowed Rooms)</th>
            @if($isAdminOrSuper)
              <th style="width:150px; text-align:center;">Aksi</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($alkes as $idx => $a)
            <tr id="row-alkes-{{ $a->id }}">
              <td style="color:var(--slate-400); font-size:12px;">{{ $alkes->firstItem() + $idx }}</td>
              <td><strong>{{ $a->nama }}</strong></td>
              <td style="font-size:12.5px; color:var(--slate-600);">{{ $a->aturan_ruangan ?: '-' }}</td>
              <td>
                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                  @foreach($rooms as $room)
                    @if(in_array($room, $a->allowed_rooms ?: []))
                      <span class="chip" style="font-size:10px; padding:2px 6px; background:#DCFCE7; color:#15803D; font-weight:700;">{{ $room }}</span>
                    @else
                      <span class="chip" style="font-size:10px; padding:2px 6px; background:#F1F5F9; color:#94A3B8; border:1px solid #CBD5E1; text-decoration:line-through; opacity:0.6;">{{ $room }}</span>
                    @endif
                  @endforeach
                </div>
              </td>
              @if($isAdminOrSuper)
                <td style="text-align:center;">
                  <div style="display:flex; gap:6px; justify-content:center;">
                    <button type="button" class="btn btn-sm edit-alkes-btn" 
                      data-id="{{ $a->id }}"
                      data-nama="{{ $a->nama }}"
                      data-aturan_ruangan="{{ $a->aturan_ruangan }}"
                      data-allowed_rooms="{{ json_encode($a->allowed_rooms ?: []) }}"
                      style="background:var(--slate-200); color:var(--slate-800); border-color:var(--slate-300);">
                      Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-alkes-btn" 
                      data-id="{{ $a->id }}"
                      data-nama="{{ $a->nama }}">
                      Hapus
                    </button>
                  </div>
                </td>
              @endif
            </tr>
          @endforeach
          @if($alkes->isEmpty())
            <tr class="empty-row">
              <td colspan="{{ $isAdminOrSuper ? 5 : 4 }}" style="text-align:center; padding:30px;">
                Belum ada data alkes khusus terdaftar.
              </td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    @if($alkes instanceof \Illuminate\Pagination\LengthAwarePaginator && $alkes->hasPages())
      <div class="pagination-wrapper" style="margin-top:20px;">
        <div class="pagination-info">
          Menampilkan {{ $alkes->firstItem() }} - {{ $alkes->lastItem() }} dari {{ $alkes->total() }} data
        </div>
        <div class="pagination-links">
          @if ($alkes->onFirstPage())
            <span class="disabled">&laquo;</span>
          @else
            <a href="{{ $alkes->appends(request()->query())->previousPageUrl() }}">&laquo;</a>
          @endif

          @foreach ($alkes->getUrlRange(max(1, $alkes->currentPage() - 2), min($alkes->lastPage(), $alkes->currentPage() + 2)) as $page => $url)
            @if ($page == $alkes->currentPage())
              <span class="active">{{ $page }}</span>
            @else
              <a href="{{ $alkes->appends(request()->query())->url($page) }}">{{ $page }}</a>
            @endif
          @endforeach

          @if ($alkes->hasMorePages())
            <a href="{{ $alkes->appends(request()->query())->nextPageUrl() }}">&raquo;</a>
          @else
            <span class="disabled">&raquo;</span>
          @endif
        </div>
      </div>
    @endif
  </div>

  <!-- Form Modal (Add / Edit) -->
  <div id="alkesModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(3px); z-index:9999; justify-content:center; align-items:center; padding:15px;">
    <div class="card" style="width: 550px; max-width:100%; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); background:var(--white); padding:20px; animation: modalFadeIn 0.2s ease-out;">
      <h3 id="modalTitle" style="margin-top:0; margin-bottom:15px; font-weight:700; color:var(--primary-900);">Tambah Alkes Khusus Baru</h3>
      
      <form id="alkesForm">
        <input type="hidden" id="editAlkesId">
        
        <div class="field" style="margin-bottom:12px;">
          <label class="req" style="font-weight:600; margin-bottom:4px; display:block;">Nama Alkes Khusus</label>
          <input type="text" id="alkesNama" class="form-control" required placeholder="mis. C-Arm" style="width:100%;">
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label style="font-weight:600; margin-bottom:4px; display:block;">Keterangan Aturan Aturan Ruangan</label>
          <input type="text" id="alkesAturan" class="form-control" placeholder="mis. semua OT (kecuali cathlab)" style="width:100%;">
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label style="font-weight:600; margin-bottom:8px; display:block;">Pilih Ruangan yang Diizinkan (Allowed Rooms)</label>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; background:var(--slate-50); padding:10px; border-radius:6px; border:1px solid var(--slate-200);">
            @foreach($rooms as $room)
              <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; cursor:pointer;">
                <input type="checkbox" name="allowed_rooms[]" value="{{ $room }}" class="room-checkbox" style="width:16px; height:16px; cursor:pointer;">
                {{ $room }}
              </label>
            @endforeach
          </div>
        </div>
        
        <div class="btn-row" style="margin-top:20px; display:flex; justify-content:flex-end; gap:8px;">
          <button type="button" class="btn" id="closeModalBtn" style="background:var(--slate-100); color:var(--slate-700); border-color:var(--slate-300);">Batal</button>
          <button type="submit" class="btn btn-primary" id="saveAlkesBtn">Simpan</button>
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
    const modal = document.getElementById("alkesModal");
    const alkesForm = document.getElementById("alkesForm");
    const editIdInput = document.getElementById("editAlkesId");
    
    const alkesNama = document.getElementById("alkesNama");
    const alkesAturan = document.getElementById("alkesAturan");

    // Open Add Modal
    const addBtn = document.getElementById("addAlkesBtn");
    if (addBtn) {
      addBtn.onclick = () => {
        document.getElementById("modalTitle").textContent = "Tambah Alkes Khusus Baru";
        editIdInput.value = "";
        alkesForm.reset();
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
    
    modal.onclick = (e) => {
      if (e.target === modal) {
        modal.style.display = "none";
      }
    };

    // Open Edit Modal
    document.querySelectorAll(".edit-alkes-btn").forEach(btn => {
      btn.onclick = function() {
        document.getElementById("modalTitle").textContent = "Edit Alkes Khusus";
        editIdInput.value = this.dataset.id;
        alkesNama.value = this.dataset.nama || '';
        alkesAturan.value = this.dataset.aturan_ruangan || '';
        
        // Reset checkboxes
        document.querySelectorAll(".room-checkbox").forEach(cb => cb.checked = false);
        
        // Check allowed rooms
        let allowed = [];
        try {
          allowed = JSON.parse(this.dataset.allowed_rooms);
        } catch(e) {}
        
        if (Array.isArray(allowed)) {
          allowed.forEach(room => {
            const cb = document.querySelector(`.room-checkbox[value="${room}"]`);
            if (cb) cb.checked = true;
          });
        }

        modal.style.display = "flex";
      };
    });

    // Form Submit (Add / Edit) via AJAX
    alkesForm.onsubmit = (e) => {
      e.preventDefault();
      
      const id = editIdInput.value;
      const url = id ? `/admin/alkes/${id}` : '/admin/alkes';
      const method = id ? 'PUT' : 'POST';
      
      const checkedRooms = [];
      document.querySelectorAll(".room-checkbox:checked").forEach(cb => {
        checkedRooms.push(cb.value);
      });
      
      const payload = {
        nama: alkesNama.value,
        aturan_ruangan: alkesAturan.value,
        allowed_rooms: checkedRooms
      };

      const submitBtn = document.getElementById("saveAlkesBtn");
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

    // Delete via AJAX
    document.querySelectorAll(".delete-alkes-btn").forEach(btn => {
      btn.onclick = function() {
        const id = this.dataset.id;
        const name = this.dataset.nama;
        
        if (confirm(`Apakah Anda yakin ingin menghapus alkes khusus "${name}"?`)) {
          this.disabled = true;
          
          fetch(`/admin/alkes/${id}`, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': csrfToken
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              toast(data.message, "success");
              const row = document.getElementById(`row-alkes-${id}`);
              if (row) row.remove();
            } else {
              this.disabled = false;
              toast(data.message || "Gagal menghapus alkes khusus", "error");
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
