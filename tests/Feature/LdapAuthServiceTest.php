<?php

namespace Tests\Feature;

use App\Services\LdapAuthService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LdapAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Force mock connection for testing LdapAuthService
        Config::set('services.ldap.connection', 'mock');
    }

    public function test_authenticate_returns_success_in_mock_mode(): void
    {
        $service = new LdapAuthService();
        $result = $service->authenticate('test.user', 'somepassword');

        $this->assertTrue($result['success']);
        $this->assertEquals('test.user', $result['username']);
        $this->assertEquals('Test User', $result['name']);
        $this->assertEquals('test.user@rs.ui.ac.id', $result['email']);
    }

    public function test_authenticate_fails_when_password_is_empty_in_mock_mode(): void
    {
        $service = new LdapAuthService();
        $result = $service->authenticate('test.user', '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Password tidak boleh kosong.', $result['message']);
    }

    public function test_configured_group_does_not_affect_mock_mode(): void
    {
        Config::set('services.ldap.group', 'Monitoring Hospital Care');
        
        $service = new LdapAuthService();
        $result = $service->authenticate('test.user', 'somepassword');

        $this->assertTrue($result['success']);
        $this->assertEquals('test.user', $result['username']);
    }
}
