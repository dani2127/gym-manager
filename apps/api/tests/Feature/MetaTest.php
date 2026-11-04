<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetaTest extends TestCase
{
    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_config_exposes_etb_and_tax_defaults(): void
    {
        config(['gym.tax.enabled' => true]);

        $this->getJson('/api/v1/meta/config')
            ->assertOk()
            ->assertJsonPath('currency.code', 'ETB')
            ->assertJsonPath('tax.enabled', true)
            ->assertJsonPath('tax.rate', 15)
            ->assertJsonPath('locales', ['en', 'am']);
    }

    public function test_config_never_leaks_gateway_secrets(): void
    {
        config(['gym.notifications.telegram.bot_token' => 'secret-token']);

        $this->getJson('/api/v1/meta/config')
            ->assertOk()
            ->assertDontSee('secret-token');
    }

    public function test_only_enabled_notification_channels_are_listed(): void
    {
        config([
            'gym.notifications.telegram.enabled' => true,
            'gym.notifications.whatsapp.enabled' => false,
            'gym.notifications.sms.enabled' => false,
            'gym.notifications.email.enabled' => true,
        ]);

        $this->getJson('/api/v1/meta/config')
            ->assertJsonPath('notification_channels', ['telegram', 'email']);
    }

    public function test_locale_follows_x_locale_header(): void
    {
        $this->getJson('/api/v1/meta/config', ['X-Locale' => 'am'])
            ->assertHeader('Content-Language', 'am')
            ->assertJsonPath('locale', 'am')
            ->assertJsonPath('messages.welcome', 'ወደ ጂም መድረኩ እንኳን ደህና መጡ');
    }

    public function test_unsupported_locale_falls_back_to_english(): void
    {
        $this->getJson('/api/v1/meta/config', ['X-Locale' => 'hu', 'Accept-Language' => 'hu'])
            ->assertJsonPath('locale', 'en');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
