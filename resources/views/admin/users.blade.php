@extends('layouts.app')

@section('title', 'OCC — Manajemen Pengguna')
@section('page_title', 'Manajemen Pengguna SSO')

@section('content')
  @php
    $currentUser = Auth::user();
    $isSuperAdmin = ($currentUser->role === 'SuperAdmin');
  @endphp

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
      <div>
        <h3 style="margin:0 0 4px 0;">Daftar Pengguna SSO Terdaftar</h3>
        <p class="hint" style="margin:0;">
          Role default setelah login pertama kali adalah <strong>Viewer</strong>.
          Perubahan peran berlaku langsung (real-time).
        </p>
      </div>
      <div style="font-size:12px; color:var(--slate-500);">
        Total: <strong>{{ $users->count() }} pengguna</strong>
      </div>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Username SSO</th>
            <th>Nama Lengkap</th>
            <th>Email</th>
            <th>Login Pertama</th>
            <th>Peran Saat Ini</th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $idx => $u)
            @php
              $isMohammadHud     = ($u->username === 'mohammad.hud');
              $isDisabledForAdmin = ($currentUser->role === 'Administrator' && $u->role === 'SuperAdmin');
              $isChangeDisabled   = ($isMohammadHud || $isDisabledForAdmin);
            @endphp
            <tr>
              <td style="color:var(--slate-400); font-size:12px;">{{ $idx + 1 }}</td>
              <td>
                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                  <strong>{{ $u->username }}</strong>
                  @if($isMohammadHud)
                    <span class="chip" style="background:var(--primary-900); color:var(--accent-400); font-size:10px; padding:2px 6px;">
                      SSO Owner
                    </span>
                  @endif
                </div>
              </td>
              <td>{{ $u->name }}</td>
              <td>{{ $u->email ?: '-' }}</td>
              <td><span class="footer-hint">{{ $u->created_at->format('d M Y H:i') }}</span></td>
              <td>
                @if($isChangeDisabled)
                  <select disabled style="padding:6px; border-radius:4px; border:1px solid var(--slate-200); font-size:12.5px; background:var(--slate-100); color:var(--slate-500); width:190px; cursor:not-allowed;">
                    <option selected>{{ $rolesList[$u->role] ?? $u->role }}</option>
                  </select>
                  <span style="display:block; margin-top:4px; font-size:11px; color:var(--red-500);">
                    @if($isMohammadHud)
                      Locked
                    @else
                      Admin tidak dapat mengubah Super Admin
                    @endif
                  </span>
                @else
                  <select class="role-changer" data-user-id="{{ $u->id }}" style="padding:6px; border-radius:4px; border:1px solid var(--slate-200); font-size:12.5px; width:190px; cursor:pointer;">
                    @foreach($rolesList as $roleKey => $roleLabel)
                      <option value="{{ $roleKey }}" {{ $u->role === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                    @endforeach
                  </select>
                @endif
              </td>
            </tr>
          @endforeach
          @if($users->isEmpty())
            <tr class="empty-row"><td colspan="6">Belum ada pengguna SSO terdaftar.</td></tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
@endsection

@section('scripts')
  <script>
    // Role changer via AJAX
    document.querySelectorAll(".role-changer").forEach(select => {
      select.addEventListener("change", function() {
        const userId  = this.getAttribute("data-user-id");
        const newRole = this.value;
        const self    = this;

        // Visual feedback
        self.style.opacity = '0.6';
        self.disabled = true;

        fetch(`/users/${userId}/role`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
          body: JSON.stringify({ role: newRole })
        })
        .then(res => res.json())
        .then(data => {
          self.style.opacity = '1';
          self.disabled = false;
          if (data.success) {
            toast(data.message, "success");
          } else {
            toast(data.message, "error");
            setTimeout(() => window.location.reload(), 1500);
          }
        })
        .catch(() => {
          toast("Terjadi kesalahan koneksi", "error");
          setTimeout(() => window.location.reload(), 1500);
        });
      });
    });
  </script>
@endsection
