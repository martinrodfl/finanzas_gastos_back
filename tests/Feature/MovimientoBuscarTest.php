<?php
namespace Tests\Feature;

use App\Models\Movimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientoBuscarTest extends TestCase
{
    use RefreshDatabase;

    private function crearMovimiento(array $overrides = []): Movimiento
    {
        return Movimiento::create(array_merge([
            'fecha'            => '2026-01-15',
            'descripcion'      => 'Compra genérica',
            'dependencia'      => null,
            'documento'        => null,
            'asunto'           => null,
            'debito'           => 100,
            'credito'          => 0,
            'categoria_manual' => null,
            'gasto_fijo'       => null,
        ], $overrides));
    }

    public function test_filtra_por_texto_libre_en_varios_campos(): void
    {
        $this->crearMovimiento(['descripcion' => 'Supermercado El Dorado']);
        $this->crearMovimiento(['descripcion' => 'Pago de servicio', 'dependencia' => 'El Dorado SA']);
        $this->crearMovimiento(['descripcion' => 'Otra cosa sin relación']);

        $response = $this->getJson('/api/movimientos/buscar?q=Dorado');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        $this->crearMovimiento(['fecha' => '2026-01-05']);
        $this->crearMovimiento(['fecha' => '2026-03-10']);
        $this->crearMovimiento(['fecha' => '2026-06-20']);

        $response = $this->getJson('/api/movimientos/buscar?fecha_desde=2026-02-01&fecha_hasta=2026-04-01');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.fecha', '2026-03-10');
    }

    public function test_filtra_por_categoria_manual(): void
    {
        $this->crearMovimiento(['categoria_manual' => 'Supermercado']);
        $this->crearMovimiento(['categoria_manual' => 'Transporte']);
        $this->crearMovimiento(['categoria_manual' => null]);

        $response = $this->getJson('/api/movimientos/buscar?categoria=Supermercado');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.categoria_manual', 'Supermercado');
    }

    public function test_filtra_por_categoria_de_regla_cuando_no_hay_manual(): void
    {
        DB::table('categorias')->insert([
            'nombre'     => 'Servicios',
            'icono'      => '🏷',
            'patron'     => 'Pago de servicio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->crearMovimiento(['descripcion' => 'Pago de servicio']);
        $this->crearMovimiento(['descripcion' => 'Otra compra']);

        $response = $this->getJson('/api/movimientos/buscar?categoria=Servicios');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.categoria_regla', 'Servicios');
    }

    public function test_filtra_por_tipo_y_rango_de_monto(): void
    {
        $this->crearMovimiento(['debito' => 50, 'credito' => 0]);
        $this->crearMovimiento(['debito' => 500, 'credito' => 0]);
        $this->crearMovimiento(['debito' => 0, 'credito' => 1000]);

        $response = $this->getJson('/api/movimientos/buscar?tipo=egreso&monto_min=100');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.debito', 500);
    }

    public function test_pagina_los_resultados(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->crearMovimiento(['descripcion' => "Movimiento {$i}"]);
        }

        $response = $this->getJson('/api/movimientos/buscar?per_page=10&page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.total', 30);
        $response->assertJsonPath('meta.last_page', 3);
    }
}
