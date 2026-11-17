<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Public Terms and Conditions page can be accessed without login.
     */
    public function test_syarat_dan_ketentuan_page_is_publicly_accessible(): void
    {
        $response = $this->get('/syarat-dan-ketentuan');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/SyaratKetentuan')
                ->where('meta.title', 'Syarat dan Ketentuan Penggunaan PAGORA')
                ->where('meta.version', '1.0')
                ->has('sections', 23)
                ->where('sections.0.id', 'definisi')
                ->where('sections.0.title', 'Definisi')
                ->has('acceptance.checkbox_label')
            );
    }
}
