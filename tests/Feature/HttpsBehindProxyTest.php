<?php

namespace Tests\Feature;

use App\Models\GameType;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Railway terminates TLS at its proxy and forwards plain HTTP. If the proxy
 * is not trusted, every generated URL comes out as http:// and the browser
 * blocks the stylesheet as mixed content - which is exactly what happened on
 * the first deploy. These tests fail if that regresses.
 */
class HttpsBehindProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scoreboard.key' => 'test-board']);

        Player::create(['name' => 'Sue', 'slug' => 'sue', 'position' => 1]);
        Player::create(['name' => 'Lee', 'slug' => 'lee', 'position' => 2]);
        GameType::create(['name' => 'Rummikub', 'slug' => 'rummikub', 'win_direction' => 'low']);
    }

    public function test_assets_are_https_when_the_proxy_says_the_request_was_https(): void
    {
        $response = $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('/test-board');

        $response->assertOk();
        $response->assertDontSee('http://', escape: false);
    }

    public function test_the_manifest_start_url_is_https_behind_the_proxy(): void
    {
        $response = $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('/test-board/manifest.webmanifest');

        $response->assertOk();

        $manifest = $response->json();

        $this->assertStringStartsWith('https://', $manifest['start_url']);
        $this->assertStringStartsWith('https://', $manifest['scope']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertStringStartsWith('https://', $icon['src']);
        }
    }
}
