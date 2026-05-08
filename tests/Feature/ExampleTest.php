<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The landing page returns 200.
     * withoutVite() prevents ViteManifestNotFoundException in local/CI environments
     * where `npm run build` has not been run.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertStatus(200);
    }
}
