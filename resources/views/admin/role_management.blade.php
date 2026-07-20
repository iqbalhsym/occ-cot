@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Manajemen Role')
@section('page_title', 'Manajemen Role')

@section('content')
<div class="card">
  <h3>🛡️ Manajemen Role</h3>
  <div style="font-size: 13px; color: var(--slate-600); margin-bottom: 20px; line-height: 1.5; background: var(--slate-50); border: 1px solid var(--slate-200); padding: 12px; border-radius: 8px;">
    Atur menu navigasi apa saja yang dapat diakses oleh setiap role, serta sunting label tampilan nama role. 
    Perubahan di sini langsung membatasi menu sidebar dan akses halaman di tingkat backend secara real-time.
  </div>

  @php
    $menus = [
      ['id' => 'dashboard',        'label' => 'Dashboard', 'icon' => '◆'],
      ['id' => 'monitoring',       'label' => 'Jadwal Operasi', 'icon' => '📅'],
      ['id' => 'newcase',          'label' => 'Buat Case Baru', 'icon' => '＋'],
      ['id' => 'queue',            'label' => 'Antrian Saya', 'icon' => '➜'],
      ['id' => 'estimasiMandiri',  'label' => 'Estimasi Mandiri', 'icon' => '🧮'],
      ['id' => 'guarantorMapping', 'label' => 'Mapping Guarantor', 'icon' => '🏷️'],
      ['id' => 'estimasiHistory',  'label' => 'History Estimasi', 'icon' => '🕘'],
      ['id' => 'roleManagement',   'label' => 'Manajemen Role', 'icon' => '🛡️'],
      ['id' => 'roles',            'label' => 'Penafian', 'icon' => '⚠️'],
    ];
  @endphp

  <form id="roleManagementForm">
    <div style="overflow-x:auto;">
      <table class="table" style="width: 100%; border-collapse: collapse; min-width: 900px;" id="roleTable">
        <thead>
          <tr style="background: var(--slate-100);">
            <th style="padding: 10px; text-align: left; min-width: 200px;">Role ID & Tampilan Label</th>
            @foreach($menus as $menu)
              <th style="padding: 10px; text-align: center; font-size: 11px;">
                <span style="font-size: 14px;">{{ $menu['icon'] }}</span><br>
                {{ $menu['label'] }}
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($permissions as $p)
            @php
              $locked = in_array($p->role_id, ['CaseManager', 'Administrator']);
            @endphp
            <tr data-role-id="{{ $p->role_id }}">
              <td style="padding: 8px;">
                <input type="text" class="form-control rmLabel" value="{{ $p->label }}" style="width: 100%; font-weight: 700;">
                <span style="font-size: 10px; color: var(--slate-400); display: block; margin-top: 2px;">ID: {{ $p->role_id }}</span>
              </td>
              @foreach($menus as $menu)
                @php
                  $hasMenu = in_array($menu['id'], $p->menus ?: []);
                  $disabled = ($locked && $menu['id'] === 'roleManagement') ? 'disabled' : '';
                @endphp
                <td style="padding: 8px; text-align: center; vertical-align: middle;">
                  <input type="checkbox" class="rmMenuChk" data-menu-id="{{ $menu['id'] }}" {{ $hasMenu ? 'checked' : '' }} {{ $disabled }} style="width: 16px; height: 16px; cursor: pointer;">
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
      <button type="submit" class="btn btn-primary">
        Simpan Akses & Nama Role
      </button>
    </div>
  </form>

  <hr style="border: 0; border-top: 1px solid var(--slate-200); margin: 30px 0;">

  <div style="max-width: 500px;">
    <h4>➕ Tambah Role Kustom Baru</h4>
    <p style="font-size: 12px; color: var(--slate-500); margin-bottom: 12px;">
      Tambahkan role kustom baru. ID role harus tanpa spasi (misal: "Radiologi") dan label adalah nama tampilannya.
    </p>
    <form id="addRoleForm">
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <div>
          <label style="font-size: 11px; font-weight: 700; color: var(--slate-500); display: block; margin-bottom: 4px;">ID Role</label>
          <input type="text" id="newRoleId" class="form-control" placeholder="misal: Radiologi" required style="width: 100%;">
        </div>
        <div>
          <label style="font-size: 11px; font-weight: 700; color: var(--slate-500); display: block; margin-bottom: 4px;">Nama Label Tampilan</label>
          <input type="text" id="newRoleLabel" class="form-control" placeholder="misal: Radiologi (Penunjang)" required style="width: 100%;">
        </div>
        <div style="margin-top: 5px;">
          <button type="submit" class="btn btn-secondary">
            + Tambah Role
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@section('scripts')
<script>
  const form = document.getElementById("roleManagementForm");
  const addRoleForm = document.getElementById("addRoleForm");

  // Save Role Permissions via AJAX
  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const permissions = {};
    const rows = document.querySelectorAll("#roleTable tbody tr");
    rows.forEach(tr => {
      const roleId = tr.dataset.roleId;
      const label = tr.querySelector(".rmLabel").value.trim();
      
      const menus = [];
      tr.querySelectorAll(".rmMenuChk").forEach(chk => {
        if (chk.checked) {
          menus.push(chk.dataset.menuId);
        }
      });

      permissions[roleId] = {
        label: label,
        menus: menus
      };
    });

    // Send save request
    fetch("/api/role-permissions", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
      },
      body: JSON.stringify({ permissions: permissions })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast("Perubahan akses role berhasil disimpan.", "success");
        setTimeout(() => location.reload(), 800);
      } else {
        showToast("Gagal menyimpan perubahan: " + (data.message || ""), "error");
      }
    })
    .catch(err => {
      showToast("Terjadi kesalahan jaringan.", "error");
    });
  });

  // Add Custom Role via AJAX
  addRoleForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const roleId = document.getElementById("newRoleId").value.trim();
    const label = document.getElementById("newRoleLabel").value.trim();

    if (!roleId || !label) return;

    fetch("/api/role-permissions/add-role", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
      },
      body: JSON.stringify({ role_id: roleId, label: label })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast("Role kustom berhasil ditambahkan.", "success");
        setTimeout(() => location.reload(), 800);
      } else {
        showToast("Gagal menambah role: ID role sudah terpakai.", "error");
      }
    })
    .catch(err => {
      showToast("Terjadi kesalahan jaringan.", "error");
    });
  });

  function showToast(message, type = 'success') {
    let wrap = document.getElementById("toastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "toastWrap";
      wrap.className = "toast-wrap";
      document.body.appendChild(wrap);
    }
    const t = document.createElement("div");
    t.className = `toast ${type === 'error' ? 'error' : 'success'}`;
    t.textContent = message;
    wrap.appendChild(t);
    setTimeout(() => { t.style.opacity = "0"; setTimeout(() => t.remove(), 400); }, 3000);
  }
</script>
@endsection
