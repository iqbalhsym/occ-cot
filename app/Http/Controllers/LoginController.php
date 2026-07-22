<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LdapAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    protected $ldapService;

    public function __construct(LdapAuthService $ldapService)
    {
        $this->ldapService = $ldapService;
    }

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Generate initial captcha challenge if not set
        if (!session()->has('captcha_answer')) {
            $this->generateCaptcha();
        }

        return view('auth.login');
    }

    public function refreshCaptcha()
    {
        $challenge = $this->generateCaptcha();
        return response()->json(['challenge' => $challenge]);
    }

    protected function generateCaptcha()
    {
        $num1 = rand(1, 15);
        $num2 = rand(1, 9);
        $op = rand(0, 1) ? '+' : '-';
        if ($op === '-' && $num1 < $num2) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }
        $answer = $op === '+' ? ($num1 + $num2) : ($num1 - $num2);
        
        $challenge = "$num1 $op $num2";
        session([
            'captcha_challenge' => $challenge,
            'captcha_answer' => $answer
        ]);

        return $challenge;
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'captcha' => 'required|numeric',
        ]);

        // Validate captcha answer
        $bypassCaptcha = app()->environment('local') && $request->header('X-OCC-Test-Bypass') === 'occ-secret-test-bypass-key';
        
        $isLocal = app()->environment('local');
        if (!$isLocal && !$bypassCaptcha && intval($request->captcha) !== session('captcha_answer')) {
            $this->generateCaptcha(); // force new captcha
            return redirect()->back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Jawaban captcha salah. Silakan coba lagi.']);
        }

        $username = strtolower(trim($request->username));
        $password = $request->password;

        // Verify credentials strictly against LDAP/SSO directory (No local DB password fallback)
        $ldapResult = $this->ldapService->authenticate($username, $password);

        if (!$ldapResult['success']) {
            $this->generateCaptcha(); // force new captcha
            return redirect()->back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => $ldapResult['message']]);
        }

        // Provision user locally if not exists
        $user = User::where('username', $username)->first();

        if (!$user) {
            // Determine default role
            $role = 'Viewer';
            if ($username === 'mohammad.hud') {
                $role = 'SuperAdmin';
            }

            $user = User::create([
                'username' => $username,
                'name' => $ldapResult['name'],
                'email' => $ldapResult['email'],
                'role' => $role,
            ]);
        }

        // Log the user in
        Auth::login($user);

        // Store active role in session
        session(['role' => $user->role]);

        return redirect()->route('dashboard')->with('success', 'Selamat datang kembali, ' . $user->name);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah keluar dari aplikasi.');
    }
}
