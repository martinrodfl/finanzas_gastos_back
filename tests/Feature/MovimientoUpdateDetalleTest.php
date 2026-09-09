<?php
namespace Tests\Feature;

use App\Models\Movimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientoUpdateDetalleTest extends TestCase
{
    use RefreshDatabase;

    private function crearMovimiento(array $overrides = []): Movimiento
    {
        return Movimiento::create(array_merge([
            'fecha'       => '2026-01-15',
            'descripcion' => 'Compra genérica',
            'asunto'      => 'Asunto original',
            'debito'      => 100,
            'credito'     => 0,
        ], $overrides));
    }

    public function test_actualiza_solo_descripcion(): void
    {
        $movimiento = $this->crearMovimiento();

        $response = $this->patchJson("/api/movimientos/{$movimiento->id}/detalle", [
            'descripcion' => 'Nueva descripción',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('descripcion', 'Nueva descripción');
        $response->assertJsonPath('asunto', 'Asunto original');
        $this->assertDatabaseHas('movimientos', [
            'id'          => $movimiento->id,
            'descripcion' => 'Nueva descripción',
            'asunto'      => 'Asunto original',
        ]);
    }

    public function test_actualiza_solo_asunto(): void
    {
        $movimiento = $this->crearMovimiento();

        $response = $this->patchJson("/api/movimientos/{$movimiento->id}/detalle", [
            'asunto' => 'Nuevo asunto',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('descripcion', 'Compra genérica');
        $response->assertJsonPath('asunto', 'Nuevo asunto');
    }

    public function test_actualiza_ambos_campos(): void
    {
        $movimiento = $this->crearMovimiento();

        $response = $this->patchJson("/api/movimientos/{$movimiento->id}/detalle", [
            'descripcion' => 'Descripción nueva',
            'asunto'      => 'Asunto nuevo',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('descripcion', 'Descripción nueva');
        $response->assertJsonPath('asunto', 'Asunto nuevo');
    }

    public function test_permite_vaciar_el_asunto(): void
    {
        $movimiento = $this->crearMovimiento();

        $response = $this->patchJson("/api/movimientos/{$movimiento->id}/detalle", [
            'asunto' => null,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('asunto', null);
    }

    public function test_rechaza_descripcion_vacia(): void
    {
        $movimiento = $this->crearMovimiento();

        $response = $this->patchJson("/api/movimientos/{$movimiento->id}/detalle", [
            'descripcion' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_devuelve_404_si_no_existe(): void
    {
        $response = $this->patchJson('/api/movimientos/99999/detalle', [
            'descripcion' => 'Algo',
        ]);

        $response->assertStatus(404);
    }
}
