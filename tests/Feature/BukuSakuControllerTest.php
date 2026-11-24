<?php

namespace Tests\Feature;

use App\Http\Controllers\BukuSakuController;
use App\Support\BukuSakuCatalog;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BukuSakuControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $root = base_path('tests/Fixtures/buku-saku');
        $this->app->instance(BukuSakuCatalog::class, new BukuSakuCatalog($root.'/resources', $root.'/public'));
        // Fixture views win over the real resources/views/buku-saku templates (W1-C),
        // which link to route names only registered in W2; those are tested separately.
        View::getFinder()->prependLocation($root.'/views');
        Route::get('/_buku-saku', [BukuSakuController::class, 'index']);
        Route::get('/_buku-saku/{buku}', [BukuSakuController::class, 'book']);
        Route::get('/_buku-saku/{buku}/{bab}', [BukuSakuController::class, 'chapter']);
    }

    public function test_three_pages_receive_catalog_data(): void
    {
        $this->get('/_buku-saku')->assertOk()->assertSee('2 buku');
        $this->get('/_buku-saku/buku-saku-mandiri')->assertOk()->assertSee('3 bab');
        $this->get('/_buku-saku/buku-saku-mandiri/01-menyikat-gigi')->assertOk()->assertSee('42 detik');

        $html = (string) app(BukuSakuController::class)->chapter('buku-saku-mandiri', '01-menyikat-gigi')->getData()['slot'];
        $this->assertStringContainsString('42 detik', $html);
    }

    public function test_unknown_and_traversal_slugs_are_404(): void
    {
        foreach (['lain', '..', '%2e%2e', '%252e%252e', 'buku-saku-mandiri%2f..'] as $book) {
            $this->get('/_buku-saku/'.$book)->assertNotFound();
        }
        foreach (['lain', '..', '%2e%2e', '%252e%252e', '01-menyikat-gigi%2f..'] as $chapter) {
            $this->get('/_buku-saku/buku-saku-mandiri/'.$chapter)->assertNotFound();
        }
    }
}
