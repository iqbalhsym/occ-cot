<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LdapAuthService
{
    public function authenticate(string $username, string $password): array
    {
        $connection = config('services.ldap.connection', 'ldap');
        
        if ($connection === 'mock') {
            Log::info("LDAP Auth mock: attempting login for user '{$username}'");
            
            // Allow mock login if password is not empty
            if (empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Password tidak boleh kosong.'
                ];
            }

            // Generate user details from mock SSO name
            $name = ucwords(str_replace('.', ' ', $username));
            
            return [
                'success' => true,
                'username' => strtolower($username),
                'name' => $name,
                'email' => strtolower($username) . '@rs.ui.ac.id'
            ];
        }

        // Real LDAP configuration
        $host = config('services.ldap.host');
        $port = config('services.ldap.port', 389);
        $domain = config('services.ldap.user_domain', '@rs.ui.ac.id');

        if (empty($host)) {
            return [
                'success' => false,
                'message' => 'Konfigurasi LDAP host tidak lengkap di server.'
            ];
        }

        try {
            // Establish native LDAP connection
            $ldap = @ldap_connect($host, $port);
            if (!$ldap) {
                return [
                    'success' => false,
                    'message' => 'Gagal terhubung ke server LDAP.'
                ];
            }

            // LDAP Options
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

            // Bind user with full DN/UPN (e.g. username@rs.ui.ac.id)
            $userDn = $username;
            if (!str_contains($username, '@')) {
                $userDn = $username . $domain;
            }

            $bind = @ldap_bind($ldap, $userDn, $password);

            if ($bind) {
                // Try searching for user details (name/mail) if anonymous search or user bind permits
                $name = ucwords(str_replace('.', ' ', $username));
                $email = str_contains($username, '@') ? $username : $username . $domain;

                @ldap_close($ldap);

                return [
                    'success' => true,
                    'username' => strtolower($username),
                    'name' => $name,
                    'email' => strtolower($email)
                ];
            }

            // If bind fails, fetch error
            $errorCode = ldap_errno($ldap);
            $errorMsg = ldap_error($ldap);
            Log::warning("LDAP Bind failed for {$userDn}. Error code: {$errorCode}, Message: {$errorMsg}");
            
            @ldap_close($ldap);

            return [
                'success' => false,
                'message' => 'Username atau password SSO Anda salah.'
            ];

        } catch (\Exception $e) {
            Log::error("LDAP connection exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Koneksi server SSO sedang mengalami gangguan.'
            ];
        }
    }
}
