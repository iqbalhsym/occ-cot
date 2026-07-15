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
                // Check group restriction if configured
                $configuredGroup = config('services.ldap.group');
                if (!empty($configuredGroup)) {
                    $baseDn = config('services.ldap.base_dn');
                    if (empty($baseDn)) {
                        Log::warning("LDAP Group validation failed: base_dn is not configured.");
                        @ldap_close($ldap);
                        return [
                            'success' => false,
                            'message' => 'Konfigurasi LDAP base DN tidak lengkap di server.'
                        ];
                    }

                    $searchUsername = $username;
                    if (str_contains($username, '@')) {
                        $parts = explode('@', $username);
                        $searchUsername = $parts[0];
                    }

                    // Escape input for safety to prevent LDAP injection (CWE-90)
                    $escapedSearchUsername = str_replace(
                        ['\\', '*', '(', ')', "\0"],
                        ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
                        $searchUsername
                    );

                    $filter = "(sAMAccountName=" . $escapedSearchUsername . ")";
                    $search = @ldap_search($ldap, $baseDn, $filter, ['memberof']);

                    if (!$search) {
                        Log::warning("LDAP Search failed for user {$searchUsername} with base DN {$baseDn}.");
                        @ldap_close($ldap);
                        return [
                            'success' => false,
                            'message' => 'Gagal memvalidasi keanggotaan group pengguna.'
                        ];
                    }

                    $entries = @ldap_get_entries($ldap, $search);
                    if (!$entries || $entries['count'] === 0) {
                        Log::warning("LDAP user entries not found for {$searchUsername}.");
                        @ldap_close($ldap);
                        return [
                            'success' => false,
                            'message' => 'Pengguna tidak ditemukan di direktori AD.'
                        ];
                    }

                    $isMember = false;
                    if (isset($entries[0]['memberof'])) {
                        $userGroups = $entries[0]['memberof'];
                        $lowerConfigured = strtolower($configuredGroup);
                        for ($i = 0; $i < $userGroups['count']; $i++) {
                            $groupDn = $userGroups[$i];
                            $lowerGroupDn = strtolower($groupDn);
                            if (str_contains($lowerGroupDn, "cn=" . $lowerConfigured . ",") || str_ends_with($lowerGroupDn, "cn=" . $lowerConfigured)) {
                                $isMember = true;
                                break;
                            }
                        }
                    }

                    if (!$isMember) {
                        Log::warning("LDAP login denied: user '{$username}' is not a member of group '{$configuredGroup}'");
                        @ldap_close($ldap);
                        return [
                            'success' => false,
                            'message' => 'Anda tidak memiliki akses ke aplikasi ini.'
                        ];
                    }
                }

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
