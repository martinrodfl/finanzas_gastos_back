<?php
namespace App\Http\Controllers;

use App\Imports\MovimientosImport;
use App\Models\Movimiento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class MovimientoController extends Controller
{
    public function meses()
    {
        $meses = Movimiento::query()
            ->whereNotNull('fecha')
            ->orderByDesc('fecha')
            ->get(['fecha'])
            ->pluck('fecha')
            ->map(function ($fecha) {
                return Carbon::parse($fecha)->format('Y-m');
            })
            ->unique()
            ->values();

        return response()->json($meses);
    }

    public function index(Request $request)
    {
        $mes = $request->query('mes');

        if (! is_string($mes) || ! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return response()->json([
                'message' => 'Parámetro mes inválido. Formato esperado: YYYY-MM',
            ], 422);
        }

        $anio      = (int) substr($mes, 0, 4);
        $mesNumero = (int) substr($mes, 5, 2);

        $movimientos = Movimiento::query()
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mesNumero)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $reglas = DB::table('categoria_reglas')->pluck('categoria', 'descripcion');

        $movimientosConCategoria = $movimientos->map(function (Movimiento $movimiento) use ($reglas) {
            return [
                'id'               => $movimiento->id,
                'fecha'            => optional($movimiento->fecha)->format('Y-m-d'),
                'descripcion'      => $movimiento->descripcion,
                'asunto'           => $movimiento->asunto,
                'documento'        => $movimiento->documento,
                'dependencia'      => $movimiento->dependencia,
                'debito'           => (float) $movimiento->debito,
                'credito'          => (float) $movimiento->credito,
                'categoria_manual' => $movimiento->categoria_manual,
                'categoria_regla'  => $reglas[$movimiento->descripcion] ?? null,
            ];
        })->values();

        return response()->json($movimientosConCategoria);
    }

    public function resumen()
    {
        $resumen = Movimiento::query()
            ->whereNotNull('fecha')
            ->orderByDesc('fecha')
            ->get(['fecha', 'debito', 'credito'])
            ->groupBy(function (Movimiento $movimiento) {
                return optional($movimiento->fecha)->format('Y-m');
            })
            ->map(function ($items, $mes) {
                return [
                    'mes'           => $mes,
                    'total_debito'  => (float) $items->sum(function (Movimiento $m) {
                        return (float) $m->debito;
                    }),
                    'total_credito' => (float) $items->sum(function (Movimiento $m) {
                        return (float) $m->credito;
                    }),
                ];
            })
            ->values();

        return response()->json($resumen);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'dependencia' => ['nullable', 'string', 'max:100'],
            'documento'   => ['nullable', 'string', 'max:50'],
            'categoria'   => ['nullable', 'string', 'max:100'],
            'monto'       => ['required', 'numeric', 'gt:0'],
        ]);

        $movimiento                   = new Movimiento();
        $movimiento->fecha            = $validated['fecha'];
        $movimiento->descripcion      = trim($validated['descripcion']);
        $movimiento->dependencia      = $validated['dependencia'] ?? null;
        $movimiento->documento        = $validated['documento'] ?? null;
        $movimiento->categoria_manual = $validated['categoria'] ?? null;
        $movimiento->debito           = (float) $validated['monto'];
        $movimiento->credito          = 0;

        $this->ajustarMontosPorCategoria($movimiento, $movimiento->categoria_manual);
        $movimiento->save();
        $this->upsertRegla($movimiento->descripcion, $movimiento->categoria_manual);

        return response()->json([
            'success'    => true,
            'movimiento' => [
                'id'               => $movimiento->id,
                'fecha'            => optional($movimiento->fecha)->format('Y-m-d'),
                'descripcion'      => $movimiento->descripcion,
                'documento'        => $movimiento->documento,
                'dependencia'      => $movimiento->dependencia,
                'debito'           => (float) $movimiento->debito,
                'credito'          => (float) $movimiento->credito,
                'categoria_manual' => $movimiento->categoria_manual,
                'categoria_regla'  => null,
            ],
        ], 201);
    }

    public function updateCategoria(Request $request, int $id)
    {
        $validated = $request->validate([
            'categoria' => ['nullable', 'string', 'max:100'],
        ]);

        $movimiento                   = Movimiento::query()->findOrFail($id);
        $movimiento->categoria_manual = $validated['categoria'] ?? null;
        $this->ajustarMontosPorCategoria($movimiento, $movimiento->categoria_manual);
        $movimiento->save();

        $this->upsertRegla($movimiento->descripcion, $movimiento->categoria_manual);

        return response()->json([
            'success'          => true,
            'id'               => $movimiento->id,
            'categoria_manual' => $movimiento->categoria_manual,
            'debito'           => (float) $movimiento->debito,
            'credito'          => (float) $movimiento->credito,
        ]);
    }

    private function ajustarMontosPorCategoria(Movimiento $movimiento, ?string $categoria): void
    {
        if ($categoria === null) {
            return;
        }

        $debito    = (float) ($movimiento->debito ?? 0);
        $credito   = (float) ($movimiento->credito ?? 0);
        $montoBase = max($debito, $credito);

        if ($montoBase <= 0) {
            return;
        }

        if (Str::lower(trim($categoria)) === 'ingresos') {
            $movimiento->credito = $montoBase;
            $movimiento->debito  = 0;
            return;
        }

        $movimiento->debito  = $montoBase;
        $movimiento->credito = 0;
    }

    public function reglas()
    {
        $reglas = DB::table('categoria_reglas')
            ->orderBy('descripcion')
            ->get(['id', 'descripcion', 'categoria']);

        return response()->json($reglas);
    }

    public function storeRegla(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'categoria'   => ['required', 'string', 'max:100'],
        ]);

        DB::table('categoria_reglas')->upsert(
            [
                'descripcion' => $validated['descripcion'],
                'categoria'   => $validated['categoria'],
            ],
            ['descripcion'],
            ['categoria']
        );

        $regla = DB::table('categoria_reglas')
            ->where('descripcion', $validated['descripcion'])
            ->first();

        return response()->json(['success' => true, 'regla' => $regla], 201);
    }

    public function destroyRegla(int $id)
    {
        $deleted = DB::table('categoria_reglas')->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Regla no encontrada'], 404);
        }

        return response()->json(['success' => true]);
    }

    private function upsertRegla(?string $descripcion, ?string $categoria): void
    {
        if ($descripcion === null || $categoria === null) {
            return;
        }

        DB::table('categoria_reglas')->upsert(
            [
                'descripcion' => $descripcion,
                'categoria'   => $categoria,
            ],
            ['descripcion'],
            ['categoria']
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $headingRow = $this->detectarFilaCabecera($request->file('file')->getRealPath());
            $import     = new MovimientosImport($headingRow);
            Excel::import($import, $request->file('file'));
            $resumen = $import->getResumen();

            return response()->json([
                'success'     => true,
                'message'     => 'Movimientos importados correctamente',
                'resumen'     => $resumen,
                'heading_row' => $headingRow,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function detectarFilaCabecera(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = min((int) $sheet->getHighestDataRow(), 200);

        for ($row = 1; $row <= $maxRow; $row++) {
            $headersNormalizados = [];

            for ($col = 1; $col <= 12; $col++) {
                $valor = $sheet->getCell([$col, $row])->getValue();

                if ($valor === null) {
                    continue;
                }

                $texto = trim((string) $valor);
                if ($texto === '') {
                    continue;
                }

                $headersNormalizados[] = $this->normalizarHeader($texto);
            }

            if ($this->esCabeceraMovimientos($headersNormalizados)) {
                return $row;
            }
        }

        throw new RuntimeException('No se encontró la cabecera de movimientos en el archivo.');
    }

    private function esCabeceraMovimientos(array $headers): bool
    {
        $headers = array_values(array_unique($headers));

        $requeridos = ['fecha', 'descripcion', 'debito', 'credito'];
        foreach ($requeridos as $header) {
            if (! in_array($header, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    private function normalizarHeader(string $valor): string
    {
        $ascii = Str::ascii(mb_strtolower($valor));
        $snake = preg_replace('/[^a-z0-9]+/', '_', $ascii) ?? '';

        return trim($snake, '_');
    }
}
