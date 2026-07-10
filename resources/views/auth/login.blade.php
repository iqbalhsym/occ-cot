<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login SSO — HAI Care COT</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%;
    }
    body {
      display: flex;
      align-items: stretch;
      font-family: "Segoe UI", -apple-system, sans-serif;
      background: #062152;
      /* Fix zoom: izinkan scroll vertikal jika konten melebihi layar */
      min-height: 100vh;
      overflow-y: auto;
    }

    /* ── Left panel: foto gedung (teks di tengah) ── */
    .login-photo {
      flex: 1;
      background: url('{{ asset("bg-gedung-rsui.jpeg") }}') center center / cover no-repeat;
      position: relative;
      min-width: 0;
    }
    .login-photo::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(
        135deg,
        rgba(6, 33, 82, 0.75) 0%,
        rgba(13, 71, 161, 0.50) 60%,
        rgba(249, 168, 37, 0.15) 100%
      );
    }
    .login-photo-text {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 2;
      color: white;
      text-align: center;
      width: 80%;
    }
    .login-photo-text h2 {
      font-size: 32px;
      font-weight: 800;
      line-height: 1.3;
      text-shadow: 0 2px 15px rgba(0,0,0,0.6);
      margin-bottom: 12px;
    }
    .login-photo-text p {
      font-size: 16px;
      color: rgba(255,255,255,0.85);
      text-shadow: 0 1px 8px rgba(0,0,0,0.6);
    }

    /* ── Right panel: form ── */
    .login-panel {
      width: 420px;
      flex-shrink: 0;
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 48px 40px;
      position: relative;
      /* Fix zoom: scroll jika tinggi melebihi viewport */
      overflow-y: auto;
    }

    /* Stripe aksen biru-kuning di kanan panel */
    .login-panel::before {
      content: '';
      position: absolute;
      top: 0; right: 0;
      width: 4px;
      height: 100%;
      background: linear-gradient(180deg, #F9A825, #1565C0);
      pointer-events: none;
    }

    .login-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
    }
    .login-logo img {
      width: 52px;
      height: 52px;
      object-fit: contain;
    }
    .login-logo-text h1 {
      font-size: 16px;
      font-weight: 800;
      color: #0A2463;
      line-height: 1.2;
      margin: 0;
    }
    .login-logo-text p {
      font-size: 11px;
      color: #64748B;
      margin: 2px 0 0 0;
      line-height: 1.3;
    }

    .login-divider {
      height: 1px;
      background: linear-gradient(90deg, #E2E8F0, transparent);
      margin: 0 0 28px 0;
    }

    .login-title {
      font-size: 20px;
      font-weight: 800;
      color: #0A2463;
      margin-bottom: 6px;
    }
    .login-subtitle {
      font-size: 13px;
      color: #64748B;
      margin-bottom: 28px;
      line-height: 1.5;
    }

    /* Error / Success Alerts */
    .alert-error {
      background: #FEE2E2;
      border-left: 4px solid #DC2626;
      color: #991B1B;
      padding: 10px 14px;
      border-radius: 4px;
      margin-bottom: 20px;
      font-size: 13px;
    }
    .alert-success {
      background: #DCFCE7;
      border-left: 4px solid #16A34A;
      color: #166534;
      padding: 10px 14px;
      border-radius: 4px;
      margin-bottom: 20px;
      font-size: 13px;
    }

    /* Form fields */
    .f-group { margin-bottom: 18px; }
    .f-group label {
      display: block;
      font-size: 11.5px;
      font-weight: 700;
      color: #334155;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }
    .f-group input.std-input {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid #E2E8F0;
      border-radius: 6px;
      font-size: 14px;
      color: #0F172A;
      font-family: inherit;
      background: #F8FAFC;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .f-group input.std-input:focus {
      outline: none;
      border-color: #1565C0;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(21,101,192,0.12);
    }
    .f-group input.std-input::placeholder { color: #94A3B8; }

    /* Password wrapper dengan eye toggle */
    .pwd-wrapper { position: relative; }
    .pwd-wrapper input {
      width: 100%;
      padding: 11px 44px 11px 14px;
      border: 1.5px solid #E2E8F0;
      border-radius: 6px;
      font-size: 14px;
      color: #0F172A;
      font-family: inherit;
      background: #F8FAFC;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .pwd-wrapper input:focus {
      outline: none;
      border-color: #1565C0;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(21,101,192,0.12);
    }
    .pwd-wrapper input::placeholder { color: #94A3B8; }
    .pwd-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      color: #64748B;
      cursor: pointer;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      user-select: none;
    }
    .pwd-toggle:hover { color: #0F172A; }

    /* Captcha reload button */
    .captcha-reload-btn {
      padding: 0 12px;
      font-size: 13px;
      color: #1565C0;
      border: 1.5px solid #E2E8F0;
      border-radius: 6px;
      background: #fff;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      font-family: inherit;
      transition: border-color 0.2s, background 0.2s;
    }
    .captcha-reload-btn:hover {
      border-color: #1565C0;
      background: #EBF5FB;
    }
    .captcha-reload-btn svg {
      transition: transform 0.5s ease;
    }
    .captcha-reload-btn.spinning svg {
      transform: rotate(360deg);
    }

    .login-btn {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #1565C0, #0D47A1);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14.5px;
      font-weight: 700;
      cursor: pointer;
      transition: opacity 0.2s, transform 0.1s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      letter-spacing: 0.3px;
    }
    .login-btn:hover { opacity: 0.92; }
    .login-btn:active { transform: scale(0.98); }

    /* Aksen bawah kuning */
    .login-btn-accent {
      height: 3px;
      background: linear-gradient(90deg, #F9A825, #E65100);
      border-radius: 0 0 6px 6px;
      margin-top: -3px;
    }

    .login-footer {
      margin-top: 28px;
      text-align: center;
      font-size: 11px;
      color: #94A3B8;
      line-height: 1.6;
    }
    .login-footer strong { color: #1565C0; }

    @media (max-width: 680px) {
      .login-photo { display: none; }
      .login-panel { width: 100%; padding: 40px 28px; }
    }
  </style>
</head>
<body>

  {{-- Foto gedung kiri --}}
  <div class="login-photo">
    <div class="login-photo-text">
      <h2>HAI Care COT</h2>
      <p>Operation Command Center</p>
    </div>
  </div>

  {{-- Panel form kanan --}}
  <div class="login-panel">

    {{-- Logo RSUI --}}
    <div class="login-logo">
      <img src="{{ asset('logo-rsui-big.png') }}" alt="Logo RSUI">
      <div class="login-logo-text">
        <h1>RSUI</h1>
        <p>Rumah Sakit Universitas Indonesia<br>Kontrol Kamar Bedah Sentral</p>
      </div>
    </div>

    <div class="login-divider"></div>

    <div class="login-title">Masuk ke HAI Care COT</div>
    <div class="login-subtitle">Gunakan akun RSUI Anda untuk mengakses sistem.</div>

    {{-- Error / Success --}}
    @if($errors->any())
      <div class="alert-error">{{ $errors->first() }}</div>
    @endif
    @if(session('success'))
      <div class="alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('login') }}" method="POST">
      @csrf

      {{-- Username --}}
      <div class="f-group">
        <label for="username">Username SSO</label>
        <input type="text" id="username" name="username" class="std-input"
               value="{{ old('username') }}"
               placeholder="username"
               required autofocus autocomplete="username">
      </div>

      {{-- Password + Eye Toggle --}}
      <div class="f-group">
        <label for="password">Password</label>
        <div class="pwd-wrapper">
          <input type="password" id="password" name="password"
                 placeholder="password"
                 required autocomplete="current-password">
          <button type="button" class="pwd-toggle" id="pwdToggle" title="Lihat Password">
            <svg id="eyeShowIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
              <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
            </svg>
            <svg id="eyeHideIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="display:none;">
              <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a8.09 8.09 0 0 0-4.728 1.517l.739.739A6.974 6.974 0 0 1 8 4c3.769 0 6.558 3.02 7.114 4-.51.893-1.667 2.447-3.25 3.597l.745.741zM1 2.54l.068-.07A.5.5 0 0 1 1.688 2.4l13 13a.5.5 0 0 1-.07.708l-.707.707a.5.5 0 0 1-.707-.068l-1.39-1.39A8.022 8.022 0 0 1 8 13.5c-5 0-8-5.5-8-5.5a8.09 8.09 0 0 1 2.199-2.778l-1.35-1.35A.5.5 0 0 1 1 2.54zM2.06 7.5c.244.453.642.99 1.177 1.51l6.744-6.744A6.954 6.954 0 0 0 8 2c-3.769 0-6.558 3.02-7.11 4-.15.263-.275.526-.37.777l1.54 1.54z"/>
              <path d="M11.296 9.88l-1.489-1.49a1.993 1.993 0 0 1-1.37 1.37l-1.49-1.49a2 2 0 0 1 2.98-2.98l1.49 1.49a1.993 1.993 0 0 1-1.37 1.37z"/>
            </svg>
          </button>
        </div>
      </div>

      {{-- Math Captcha --}}
      <div class="f-group">
        <label for="captcha">Verifikasi Keamanan</label>
        <div style="display:flex; gap:8px; align-items:stretch; margin-bottom:8px;">
          <canvas id="captchaCanvas" width="140" height="38"
                  style="border:1.5px solid #E2E8F0; border-radius:6px; background:#F8FAFC;"></canvas>
          <button type="button" class="captcha-reload-btn" id="reloadCaptcha">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
              <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
            </svg>
            
          </button>
        </div>
        <input type="text" id="captcha" name="captcha" class="std-input"
               placeholder="Hasil"
               required autocomplete="off">
      </div>

      <button type="submit" class="login-btn">
        Masuk Aplikasi
      </button>
      <div class="login-btn-accent"></div>
    </form>

    <div class="login-footer">
      Akun dikelola oleh <strong>Active Directory RSUI</strong>.<br>
      Tidak bisa login? Hubungi <strong>IT Division RSUI</strong>.<br><br>
      HAI Care COT &copy; {{ date('Y') }} — Rumah Sakit Universitas Indonesia
    </div>
  </div>

  <script>
    // ── Eye Toggle Password ──
    const pwdInput  = document.getElementById('password');
    const pwdToggle = document.getElementById('pwdToggle');
    const eyeShow   = document.getElementById('eyeShowIcon');
    const eyeHide   = document.getElementById('eyeHideIcon');

    pwdToggle.addEventListener('click', function() {
      if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        eyeShow.style.display = 'none';
        eyeHide.style.display = 'block';
        this.title = 'Sembunyikan Password';
      } else {
        pwdInput.type = 'password';
        eyeShow.style.display = 'block';
        eyeHide.style.display = 'none';
        this.title = 'Lihat Password';
      }
    });

    // ── Math Captcha Canvas ──
    const canvas = document.getElementById('captchaCanvas');
    const ctx    = canvas.getContext('2d');
    let captchaChallenge = "{{ session('captcha_challenge', '') }}";

    function drawCaptcha(text) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#F8FAFC';
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      // Garis-garis noise
      ctx.strokeStyle = '#CBD5E1';
      for (let i = 0; i < 5; i++) {
        ctx.beginPath();
        ctx.lineWidth = Math.random() * 1.5 + 0.8;
        ctx.moveTo(Math.random() * canvas.width, Math.random() * canvas.height);
        ctx.lineTo(Math.random() * canvas.width, Math.random() * canvas.height);
        ctx.stroke();
      }

      // Teks soal
      ctx.fillStyle = '#0A2463';
      ctx.font = 'bold 18px "Courier New", Courier, monospace';
      ctx.textBaseline = 'middle';
      ctx.textAlign = 'center';
      ctx.save();
      ctx.translate(canvas.width / 2, canvas.height / 2);
      ctx.rotate((Math.random() - 0.5) * 0.12);
      ctx.fillText(text + ' = ?', 0, 0);
      ctx.restore();
    }

    drawCaptcha(captchaChallenge);

    // Reload captcha dengan icon spin
    const reloadBtn = document.getElementById('reloadCaptcha');
    const reloadSvg = reloadBtn.querySelector('svg');
    reloadBtn.addEventListener('click', function() {
      reloadBtn.disabled = true;
      reloadSvg.style.transform = 'rotate(360deg)';
      reloadSvg.style.transition = 'transform 0.5s ease';

      fetch("{{ route('captcha.refresh') }}")
        .then(res => res.json())
        .then(data => {
          captchaChallenge = data.challenge;
          drawCaptcha(captchaChallenge);
        })
        .finally(() => {
          reloadBtn.disabled = false;
          setTimeout(() => {
            reloadSvg.style.transition = 'none';
            reloadSvg.style.transform  = 'none';
          }, 550);
        });
    });
  </script>
</body>
</html>
