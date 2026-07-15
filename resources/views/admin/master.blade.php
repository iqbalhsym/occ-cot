@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Master Data Management')
@section('page_title', 'Master Data Management')

@section('content')
  @php
    $currentUser = Auth::user();
    $isAdminOrSuper = in_array($currentUser->role, ['SuperAdmin', 'Administrator']);
  @endphp

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
      <div>
        <h3 style="margin:0 0 4px 0;">Pengelolaan Data Master Tindakan & Paket</h3>
        <p class="hint" style="margin:0;">
          Kelola parameter operasional, tarif paket BMHP, alokasi alat khusus, dan golongan tindakan operasi.
        </p>
      </div>
      <div>
        @if($isAdminOrSuper)
          <button type="button" class="btn btn-primary" id="addMasterBtn">+ Tambah Data Baru</button>
        @endif
      </div>
    </div>

    <!-- TABS SWITCHER -->
    <div class="tabs-nav" style="display:flex; border-bottom:2px solid var(--slate-200); margin-bottom:18px; gap:4px;">
      <a href="{{ route('admin.master', ['tab' => 'database_master']) }}" class="tab-link {{ $tab === 'database_master' ? 'active' : '' }}" style="padding:10px 18px; text-decoration:none; font-weight:600; color:{{ $tab === 'database_master' ? 'var(--primary-700)' : 'var(--slate-600)' }}; border-bottom:3px solid {{ $tab === 'database_master' ? 'var(--primary-600)' : 'transparent' }}; font-size:14px; margin-bottom:-2px;">
        DATABASE_MASTER
      </a>
      <a href="{{ route('admin.master', ['tab' => 'master_tindakan']) }}" class="tab-link {{ $tab === 'master_tindakan' ? 'active' : '' }}" style="padding:10px 18px; text-decoration:none; font-weight:600; color:{{ $tab === 'master_tindakan' ? 'var(--primary-700)' : 'var(--slate-600)' }}; border-bottom:3px solid {{ $tab === 'master_tindakan' ? 'var(--primary-600)' : 'transparent' }}; font-size:14px; margin-bottom:-2px;">
        MASTER_TINDAKAN
      </a>
      <a href="{{ route('admin.master', ['tab' => 'master_paket_bmhp']) }}" class="tab-link {{ $tab === 'master_paket_bmhp' ? 'active' : '' }}" style="padding:10px 18px; text-decoration:none; font-weight:600; color:{{ $tab === 'master_paket_bmhp' ? 'var(--primary-700)' : 'var(--slate-600)' }}; border-bottom:3px solid {{ $tab === 'master_paket_bmhp' ? 'var(--primary-600)' : 'transparent' }}; font-size:14px; margin-bottom:-2px;">
        MASTER_PAKET_BMHP
      </a>
      <a href="{{ route('admin.master', ['tab' => 'master_alat']) }}" class="tab-link {{ $tab === 'master_alat' ? 'active' : '' }}" style="padding:10px 18px; text-decoration:none; font-weight:600; color:{{ $tab === 'master_alat' ? 'var(--primary-700)' : 'var(--slate-600)' }}; border-bottom:3px solid {{ $tab === 'master_alat' ? 'var(--primary-600)' : 'transparent' }}; font-size:14px; margin-bottom:-2px;">
        MASTER_ALAT
      </a>
    </div>

    <!-- Search & Filter -->
    <div style="margin-bottom:18px; display:flex; gap:8px;">
      <form action="{{ route('admin.master') }}" method="GET" style="display:flex; gap:8px; width:100%; max-width:500px;">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="text" name="search" class="form-control" placeholder="Cari data..." value="{{ $search }}" style="flex:1;">
        <button type="submit" class="btn btn-primary">Cari</button>
        @if($search)
          <a href="{{ route('admin.master', ['tab' => $tab]) }}" class="btn" style="background:var(--slate-200); color:var(--slate-700); text-decoration:none; display:flex; align-items:center;">Reset</a>
        @endif
      </form>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            @if($tab === 'database_master')
              <th>Nama Operasi</th>
              <th>Golongan</th>
              <th>Spesialisasi</th>
              <th>Paket Bedah</th>
              <th>Paket Anestesi</th>
              <th>Alat Khusus</th>
            @elseif($tab === 'master_tindakan')
              <th>Nama Tindakan</th>
              <th>Operator (KSM/Spesialis)</th>
              <th>Golongan Tindakan</th>
            @elseif($tab === 'master_paket_bmhp')
              <th>Nama Paket BMHP</th>
              <th style="text-align:right;">Tarif (Rp)</th>
            @elseif($tab === 'master_alat')
              <th>Nama Alat Khusus</th>
              <th style="text-align:right;">Tarif Kelas Perawatan (Rp)</th>
            @endif
            @if($isAdminOrSuper)
              <th style="width:120px; text-align:center;">Aksi</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($data as $idx => $item)
            <tr id="row-item-{{ $item->id }}">
              <td style="color:var(--slate-400); font-size:12px;">{{ $data->firstItem() + $idx }}</td>
              
              @if($tab === 'database_master')
                <td><strong>{{ $item->nama }}</strong></td>
                <td>
                  <span class="chip" style="background:var(--slate-100); color:var(--slate-700); font-size:11px;">
                    {{ $item->golongan ?: 'NON GOLONGAN' }}
                  </span>
                </td>
                <td>{{ $item->spesialisasi ?: '-' }}</td>
                <td>{{ $item->paket ?: '-' }}</td>
                <td>{{ $item->paket_anestesi ?: '-' }}</td>
                <td>{{ $item->alat ?: '-' }}</td>
              @elseif($tab === 'master_tindakan')
                <td><strong>{{ $item->tindakan }}</strong></td>
                <td>{{ $item->operator ?: '-' }}</td>
                <td>
                  <span class="chip" style="background:var(--slate-100); color:var(--slate-700); font-size:11px;">
                    {{ $item->golongan ?: 'NON GOLONGAN' }}
                  </span>
                </td>
              @elseif($tab === 'master_paket_bmhp')
                <td><strong>{{ $item->nama }}</strong></td>
                <td style="text-align:right;">{{ $item->tarif_formatted ?? number_format($item->tarif, 0, ',', '.') }}</td>
              @elseif($tab === 'master_alat')
                <td><strong>{{ $item->nama }}</strong></td>
                <td style="text-align:right;">{{ $item->tarif_formatted ?? number_format($item->tarif, 0, ',', '.') }}</td>
              @endif

              @if($isAdminOrSuper)
                <td style="text-align:center;">
                  <div style="display:flex; gap:6px; justify-content:center;">
                    <button type="button" class="btn btn-sm edit-item-btn" 
                      data-id="{{ $item->id }}"
                      @if($tab === 'database_master')
                        data-nama="{{ $item->nama }}"
                        data-golongan="{{ $item->golongan }}"
                        data-spesialisasi="{{ $item->spesialisasi }}"
                        data-paket="{{ $item->paket }}"
                        data-paket_anestesi="{{ $item->paket_anestesi }}"
                        data-alat="{{ $item->alat }}"
                      @elseif($tab === 'master_tindakan')
                        data-tindakan="{{ $item->tindakan }}"
                        data-operator="{{ $item->operator }}"
                        data-golongan="{{ $item->golongan }}"
                      @elseif($tab === 'master_paket_bmhp')
                        data-nama="{{ $item->nama }}"
                        data-tarif="{{ $item->tarif }}"
                      @elseif($tab === 'master_alat')
                        data-nama="{{ $item->nama }}"
                        data-tarif="{{ $item->tarif }}"
                      @endif
                      style="background:var(--slate-200); color:var(--slate-800); border-color:var(--slate-300);">
                      Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-item-btn" 
                      data-id="{{ $item->id }}"
                      data-nama="{{ $tab === 'master_tindakan' ? $item->tindakan : $item->nama }}">
                      Hapus
                    </button>
                  </div>
                </td>
              @endif
            </tr>
          @endforeach
          @if($data->isEmpty())
            <tr class="empty-row">
              <td colspan="10" style="text-align:center; padding:30px;">
                Belum ada data master pada tab ini.
              </td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:18px; border-top:1px solid var(--slate-100); padding-top:14px;">
      <div class="pagination-info" style="font-size:13px; color:var(--slate-500);">
        Menampilkan {{ $data->firstItem() ?: 0 }} - {{ $data->lastItem() ?: 0 }} dari {{ $data->total() }} data
      </div>
      <div class="pagination-links" style="display:flex; gap:4px; align-items:center;">
        @if ($data->onFirstPage())
          <span class="disabled" style="display:inline-block; padding:4px 10px; background:var(--slate-100); border:1px solid var(--slate-200); border-radius:4px; color:var(--slate-400); font-size:13px; pointer-events:none;">&laquo;</span>
        @else
          <a href="{{ $data->previousPageUrl() }}" style="display:inline-block; padding:4px 10px; background:var(--white); border:1px solid var(--slate-200); border-radius:4px; color:var(--primary-700); text-decoration:none; font-size:13px; transition:background 0.2s;">&laquo;</a>
        @endif

        @foreach ($data->getUrlRange(max(1, $data->currentPage() - 2), min($data->lastPage(), $data->currentPage() + 2)) as $page => $url)
          @if ($page == $data->currentPage())
            <span class="active" style="display:inline-block; padding:4px 12px; background:var(--primary-600); border:1px solid var(--primary-600); border-radius:4px; color:var(--white); font-weight:700; font-size:13px;">{{ $page }}</span>
          @else
            <a href="{{ $url }}" style="display:inline-block; padding:4px 12px; background:var(--white); border:1px solid var(--slate-200); border-radius:4px; color:var(--slate-700); text-decoration:none; font-size:13px; transition:background 0.2s;">{{ $page }}</a>
          @endif
        @endforeach

        @if ($data->hasMorePages())
          <a href="{{ $data->nextPageUrl() }}" style="display:inline-block; padding:4px 10px; background:var(--white); border:1px solid var(--slate-200); border-radius:4px; color:var(--primary-700); text-decoration:none; font-size:13px; transition:background 0.2s;">&raquo;</a>
        @else
          <span class="disabled" style="display:inline-block; padding:4px 10px; background:var(--slate-100); border:1px solid var(--slate-200); border-radius:4px; color:var(--slate-400); font-size:13px; pointer-events:none;">&raquo;</span>
        @endif
      </div>
    </div>
  </div>

  <!-- DYNAMIC CRUD MODAL -->
  @if($isAdminOrSuper)
    <div class="modal-backdrop" id="masterModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:20px;">
      <div class="card" style="width:100%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 12px 30px rgba(0,0,0,0.25);">
        <h3 id="modalTitle">Tambah Data Master Baru</h3>
        <form id="masterForm" style="margin-top:15px; display:flex; flex-direction:column; gap:14px;">
          @csrf
          <input type="hidden" id="itemId">
          <input type="hidden" id="itemTab" value="{{ $tab }}">

          @if($tab === 'database_master')
            <div class="field">
              <label>Nama Operasi / Kasus</label>
              <input type="text" id="dbNama" name="nama" class="form-control" required placeholder="mis. Insisi abses (bius umum)">
            </div>
            <div class="field">
              <label>Golongan Tindakan</label>
              <select id="dbGolongan" name="golongan" class="form-control">
                <option value="KECIL">KECIL</option>
                <option value="SEDANG">SEDANG</option>
                <option value="BESAR">BESAR</option>
                <option value="KHUSUS A">KHUSUS A</option>
                <option value="KHUSUS B">KHUSUS B</option>
                <option value="KHUSUS C">KHUSUS C</option>
                <option value="NON GOLONGAN">NON GOLONGAN</option>
              </select>
            </div>
            <div class="field">
              <label>Spesialisasi</label>
              <input type="text" id="dbSpesialisasi" name="spesialisasi" class="form-control" placeholder="mis. Bedah, Orthopedi">
            </div>
            <div class="field">
              <label>Paket Bedah (BMHP)</label>
              <input type="text" id="dbPaket" name="paket" class="form-control" placeholder="mis. DEBRIDEMENT, ORIF">
            </div>
            <div class="field">
              <label>Paket Anestesi (BMHP)</label>
              <input type="text" id="dbPaketAnestesi" name="paket_anestesi" class="form-control" placeholder="mis. ANESTESI GA, ANESTESI SPINAL">
            </div>
            <div class="field">
              <label>Alat Khusus</label>
              <input type="text" id="dbAlat" name="alat" class="form-control" placeholder="mis. C-ARM, MICROSCOPE">
            </div>

          @elseif($tab === 'master_tindakan')
            <div class="field">
              <label>Nama Tindakan</label>
              <input type="text" id="mtTindakan" name="tindakan" class="form-control" required placeholder="mis. Aff IUD Dalam Narkose">
            </div>
            <div class="field">
              <label>Operator (KSM/Spesialis)</label>
              <input type="text" id="mtOperator" name="operator" class="form-control" placeholder="mis. OBSGYN, UROLOGI, MATA">
            </div>
            <div class="field">
              <label>Golongan Tindakan</label>
              <select id="mtGolongan" name="golongan" class="form-control">
                <option value="KECIL">KECIL</option>
                <option value="SEDANG">SEDANG</option>
                <option value="BESAR">BESAR</option>
                <option value="KHUSUS A">KHUSUS A</option>
                <option value="KHUSUS B">KHUSUS B</option>
                <option value="KHUSUS C">KHUSUS C</option>
                <option value="NON GOLONGAN">NON GOLONGAN</option>
              </select>
            </div>

          @elseif($tab === 'master_paket_bmhp')
            <div class="field">
              <label>Nama Paket BMHP</label>
              <input type="text" id="mpNama" name="nama" class="form-control" required placeholder="mis. SPINAL ANESTESI">
            </div>
            <div class="field">
              <label>Tarif (Rp)</label>
              <input type="number" id="mpTarif" name="tarif" class="form-control" placeholder="mis. 1974568">
            </div>

          @elseif($tab === 'master_alat')
            <div class="field">
              <label>Nama Alat Khusus</label>
              <input type="text" id="maNama" name="nama" class="form-control" required placeholder="mis. ALAT / INSTRUMEN - CARM">
            </div>
            <div class="field">
              <label>Tarif Kelas Perawatan (Rp)</label>
              <input type="number" id="maTarif" name="tarif" class="form-control" placeholder="mis. 966000">
            </div>
          @endif

          <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px; border-top:1px solid var(--slate-200); padding-top:15px;">
            <button type="button" class="btn" id="cancelModalBtn" style="background:var(--slate-200); color:var(--slate-700);">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection

@section('scripts')
  <script>
    const tab = "{{ $tab }}";
    const modal = document.getElementById("masterModal");
    const masterForm = document.getElementById("masterForm");
    const modalTitle = document.getElementById("modalTitle");
    const itemIdInput = document.getElementById("itemId");

    // Open Modal
    const addMasterBtn = document.getElementById("addMasterBtn");
    if (addMasterBtn) {
      addMasterBtn.onclick = () => {
        masterForm.reset();
        itemIdInput.value = "";
        modalTitle.textContent = "Tambah Data Master Baru";
        modal.style.display = "flex";
      };
    }

    // Close Modal
    const cancelModalBtn = document.getElementById("cancelModalBtn");
    if (cancelModalBtn) {
      cancelModalBtn.onclick = () => {
        modal.style.display = "none";
      };
    }

    // Edit Item
    document.querySelectorAll(".edit-item-btn").forEach(btn => {
      btn.onclick = () => {
        masterForm.reset();
        const id = btn.getAttribute("data-id");
        itemIdInput.value = id;
        modalTitle.textContent = "Ubah Data Master (ID: " + id + ")";

        if (tab === 'database_master') {
          document.getElementById("dbNama").value = btn.getAttribute("data-nama") || "";
          document.getElementById("dbGolongan").value = btn.getAttribute("data-golongan") || "NON GOLONGAN";
          document.getElementById("dbSpesialisasi").value = btn.getAttribute("data-spesialisasi") || "";
          document.getElementById("dbPaket").value = btn.getAttribute("data-paket") || "";
          document.getElementById("dbPaketAnestesi").value = btn.getAttribute("data-paket_anestesi") || "";
          document.getElementById("dbAlat").value = btn.getAttribute("data-alat") || "";
        } else if (tab === 'master_tindakan') {
          document.getElementById("mtTindakan").value = btn.getAttribute("data-tindakan") || "";
          document.getElementById("mtOperator").value = btn.getAttribute("data-operator") || "";
          document.getElementById("mtGolongan").value = btn.getAttribute("data-golongan") || "NON GOLONGAN";
        } else if (tab === 'master_paket_bmhp') {
          document.getElementById("mpNama").value = btn.getAttribute("data-nama") || "";
          document.getElementById("mpTarif").value = btn.getAttribute("data-tarif") || "";
        } else if (tab === 'master_alat') {
          document.getElementById("maNama").value = btn.getAttribute("data-nama") || "";
          document.getElementById("maTarif").value = btn.getAttribute("data-tarif") || "";
        }

        modal.style.display = "flex";
      };
    });

    // Form Submit (Save / Update)
    if (masterForm) {
      masterForm.onsubmit = (e) => {
        e.preventDefault();
        const id = itemIdInput.value;
        const url = id ? `/admin/master/${id}` : '/admin/master';
        const method = id ? 'PUT' : 'POST';

        // Read all inputs in form into payload
        const formData = new FormData(masterForm);
        const payload = {
          tab: tab,
          _token: "{{ csrf_token() }}"
        };
        formData.forEach((val, key) => {
          if (key !== '_token') payload[key] = val;
        });

        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}"
          },
          body: JSON.stringify({
            ...payload,
            _method: method
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            toast(data.message, "success");
            modal.style.display = "none";
            setTimeout(() => window.location.reload(), 1000);
          } else {
            toast(data.message || "Gagal menyimpan data", "error");
          }
        })
        .catch(err => {
          toast("Terjadi kesalahan sistem.", "error");
        });
      };
    }

    // Delete Item
    document.querySelectorAll(".delete-item-btn").forEach(btn => {
      btn.onclick = () => {
        const id = btn.getAttribute("data-id");
        const nama = btn.getAttribute("data-nama");

        if (confirm(`Apakah Anda yakin ingin menghapus data '${nama}'?`)) {
          fetch(`/admin/master/${id}?tab=${tab}`, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': "{{ csrf_token() }}"
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              toast(data.message, "success");
              const row = document.getElementById(`row-item-${id}`);
              if (row) row.remove();
            } else {
              toast(data.message || "Gagal menghapus data", "error");
            }
          })
          .catch(err => {
            toast("Terjadi kesalahan sistem.", "error");
          });
        }
      };
    });
  </script>
@endsection
