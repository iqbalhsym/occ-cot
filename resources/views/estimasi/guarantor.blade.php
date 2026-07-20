@extends('layouts.app')

@section('title', 'Hospital Action Interface Care — Mapping Guarantor')
@section('page_title', 'Mapping Guarantor')

@section('content')
<div class="card">
  <h3>🏷️ Mapping Guarantor</h3>
  <div style="font-size: 13px; color: var(--slate-600); margin-bottom: 20px; line-height: 1.5; background: var(--slate-50); border: 1px solid var(--slate-200); padding: 12px; border-radius: 8px;">
    Pengaturan ini memetakan Nama Guarantor/Asuransi yang diinput oleh Nurse ke kelompok tabel tarif acuan. 
    Sistem akan mencocokkan nama asuransi dengan <strong>Pola Pencarian</strong> secara <i>case-insensitive</i> (misal: penjamin asuransi mengandung kata "Inhealth" akan dipetakan ke kelompok "2023").
  </div>

  <form id="guarantorMappingForm">
    <div style="overflow-x:auto;">
      <table class="table" style="width: 100%; border-collapse: collapse;" id="mappingTable">
        <thead>
          <tr style="background: var(--slate-100);">
            <th style="padding: 10px; text-align: left;">Pola Pencarian (Substring)</th>
            <th style="padding: 10px; text-align: left; width: 220px;">Kelompok Tarif Acuan</th>
            <th style="padding: 10px; text-align: center; width: 120px;">Tarif COB?</th>
            <th style="padding: 10px; text-align: center; width: 100px;">Aksi</th>
          </tr>
        </thead>
        <tbody id="mappingTbody">
          @foreach($mappings as $idx => $m)
            <tr data-row-idx="{{ $idx }}">
              <td style="padding: 8px;">
                <input type="text" class="form-control gmPola" value="{{ $m->pola }}" style="width: 100%; font-weight: 600;" required placeholder="misal: Inhealth">
              </td>
              <td style="padding: 8px;">
                <select class="form-control gmKelompok" style="width: 100%;">
                  @foreach(array_keys($tarifDb) as $k)
                    <option value="{{ $k }}" {{ $m->kelompok_tarif === $k ? 'selected' : '' }}>{{ $k }}</option>
                  @endforeach
                  @if(!in_array($m->kelompok_tarif, array_keys($tarifDb)))
                    <option value="{{ $m->kelompok_tarif }}" selected>{{ $m->kelompok_tarif }}</option>
                  @endif
                </select>
              </td>
              <td style="padding: 8px; text-align: center; vertical-align: middle;">
                <input type="checkbox" class="gmCob" {{ $m->cob ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
              </td>
              <td style="padding: 8px; text-align: center; vertical-align: middle;">
                <button type="button" class="btn btn-sm btn-danger removeRowBtn" style="padding: 5px 10px;">Hapus</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <button type="button" class="btn" id="addRowBtn" style="background: var(--slate-100); border: 1.5px dashed var(--slate-300); color: var(--slate-700); font-weight: 600;">
        + Tambah Pola Baru
      </button>
      <button type="submit" class="btn btn-primary">
        Simpan Perubahan Mapping
      </button>
    </div>
  </form>
</div>
@endsection

@section('scripts')
<script>
  const mappingTbody = document.getElementById("mappingTbody");
  const addRowBtn = document.getElementById("addRowBtn");
  const form = document.getElementById("guarantorMappingForm");
  
  // Available kelompok options parsed from database
  const kelompokOptions = @json(array_keys($tarifDb));

  // Add new row helper
  addRowBtn.addEventListener("click", () => {
    const idx = mappingTbody.children.length;
    const tr = document.createElement("tr");
    tr.dataset.rowIdx = idx;

    let selectOptions = '';
    kelompokOptions.forEach(k => {
      selectOptions += `<option value="${k}">${k}</option>`;
    });

    tr.innerHTML = `
      <td style="padding: 8px;">
        <input type="text" class="form-control gmPola" style="width: 100%; font-weight: 600;" required placeholder="misal: Allianz">
      </td>
      <td style="padding: 8px;">
        <select class="form-control gmKelompok" style="width: 100%;">
          ${selectOptions}
        </select>
      </td>
      <td style="padding: 8px; text-align: center; vertical-align: middle;">
        <input type="checkbox" class="gmCob" style="width: 18px; height: 18px; cursor: pointer;">
      </td>
      <td style="padding: 8px; text-align: center; vertical-align: middle;">
        <button type="button" class="btn btn-sm btn-danger removeRowBtn" style="padding: 5px 10px;">Hapus</button>
      </td>
    `;

    mappingTbody.appendChild(tr);
    bindRemoveEvent(tr.querySelector(".removeRowBtn"));
  });

  // Bind remove events
  function bindRemoveEvent(btn) {
    btn.addEventListener("click", (e) => {
      e.target.closest("tr").remove();
    });
  }

  document.querySelectorAll(".removeRowBtn").forEach(bindRemoveEvent);

  // Form submit via AJAX
  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const mappings = [];
    const rows = mappingTbody.querySelectorAll("tr");
    rows.forEach(tr => {
      const pola = tr.querySelector(".gmPola").value.trim();
      const kelompok = tr.querySelector(".gmKelompok").value;
      const cob = tr.querySelector(".gmCob").checked;
      if (pola) {
        mappings.push({
          pola: pola,
          kelompok_tarif: kelompok,
          cob: cob
        });
      }
    });

    // Send AJAX request
    fetch("/api/guarantor-mapping", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
      },
      body: JSON.stringify({ mappings: mappings })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast("Mapping guarantor berhasil disimpan ke database.", "success");
        setTimeout(() => location.reload(), 800);
      } else {
        showToast("Gagal menyimpan mapping: " + (data.message || ""), "error");
      }
    })
    .catch(err => {
      showToast("Terjadi kesalahan jaringan.", "error");
    });
  });

  // Helper function to display toast notifications
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
