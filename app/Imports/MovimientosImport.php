<?php
namespace App\Imports;

use App\Models\Movimiento;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MovimientosImport implements ToModel, WithHeadingRow, WithValidation
{
    private int $headingRow;

    private int $totalFilas     = 0;
    private int $guardados      = 0;
    private int $duplicados     = 0;
    private int $omitidosVacios = 0;
    private ?string $fechaDesde = null;
    private ?string $fechaHasta = null;

    public function __construct(int $headingRow = 36)
    {
        $this->headingRow = $headingRow;
    }

    public function model(array $row)
    {
        $this->totalFilas++;

        $valores = array_values($row);

        $movimiento = [
            'fecha'       => $this->parseFecha($this->getCampo($row, $valores, ['fecha', 'fecha_movimiento', 'fecha_operacion'], 0)),
            'descripcion' => $this->clean($this->getCampo($row, $valores, ['descripcion', 'descripci_n', 'detalle', 'concepto'], 1)),
            'documento'   => $this->clean($this->getCampo($row, $valores, ['documento', 'nro_documento', 'numero_documento'], 2)),
            'asunto'      => $this->clean($this->getCampo($row, $valores, ['asunto', 'referencia', 'observacion'], 3)),
            'dependencia' => $this->clean($this->getCampo($row, $valores, ['dependencia', 'sucursal', 'comercio'], 4)),
            'debito'      => $this->parseNumero($this->getCampo($row, $valores, ['debito', 'd_bito', 'egreso', 'debe'], 5)),
            'credito'     => $this->parseNumero($this->getCampo($row, $valores, ['credito', 'cr_dito', 'ingreso', 'haber'], 6)),
        ];

        if ($this->esFilaVacia($movimiento)) {
            $this->omitidosVacios++;
            return null;
        }

        $this->registrarPeriodo($movimiento['fecha']);

        if ($this->yaExisteMovimiento($movimiento)) {
            $this->duplicados++;
            return null;
        }

        $this->guardados++;

        return new Movimiento($movimiento);
    }

    public function getResumen(): array
    {
        return [
            'total_filas'     => $this->totalFilas,
            'guardados'       => $this->guardados,
            'nuevos'          => $this->guardados,
            'duplicados'      => $this->duplicados,
            'omitidos_vacios' => $this->omitidosVacios,
            'periodo'         => [
                'desde' => $this->fechaDesde,
                'hasta' => $this->fechaHasta,
            ],
        ];
    }

    public function headingRow(): int
    {
        return $this->headingRow;
    }

    /**
     * VALIDACIONES POR FILA
     */
    public function rules(): array
    {
        return [
            '*.fecha'       => ['nullable'],
            '*.descripcion' => ['nullable', 'string'],
            '*.debito'      => ['nullable'],
            '*.credito'     => ['nullable'],
        ];
    }

    /**
     * LIMPIAR TEXTO
     */
    private function clean($value)
    {
        return $value ? trim($value) : null;
    }

    /**
     * PARSEAR FECHA (Excel viene raro)
     */
    private function parseFecha($value)
    {
        try {
            if (! $value) {
                return null;
            }

            // Caso Excel numérico (muy común)
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * PARSEAR NÚMEROS (coma vs punto)
     */
    private function parseNumero($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Si PhpSpreadsheet ya entregó un número nativo (ej: 452.46), úsalo directo.
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalizado = trim((string) $value);
        if ($normalizado === '') {
            return 0;
        }

        // Limpia símbolos comunes de moneda y espacios.
        $normalizado = str_replace(['$', ' '], '', $normalizado);
        $normalizado = str_replace(',', '.', $normalizado);

        // Si tiene múltiples puntos, los anteriores suelen ser miles.
        if (substr_count($normalizado, '.') > 1) {
            $partes      = explode('.', $normalizado);
            $decimal     = array_pop($partes);
            $normalizado = implode('', $partes) . '.' . $decimal;
        }

        return is_numeric($normalizado) ? round((float) $normalizado, 2) : 0;
    }

    private function yaExisteMovimiento(array $movimiento): bool
    {
        $query   = Movimiento::query();
        $debito  = round((float) ($movimiento['debito'] ?? 0), 2);
        $credito = round((float) ($movimiento['credito'] ?? 0), 2);

        foreach (['fecha'] as $campo) {
            if ($movimiento[$campo] === null) {
                $query->whereNull($campo);
            } else {
                $query->where($campo, $movimiento[$campo]);
            }
        }

        $documento = $movimiento['documento'] ?? null;

        if ($documento !== null && $documento !== '') {
            $query->where('documento', $documento);
        } else {
            if ($movimiento['descripcion'] === null) {
                $query->whereNull('descripcion');
            } else {
                $query->where('descripcion', $movimiento['descripcion']);
            }
        }

        $query->where(function ($montoQuery) use ($debito, $credito) {
            $montoQuery
                ->where(function ($exacto) use ($debito, $credito) {
                    $exacto
                        ->where('debito', $debito)
                        ->where('credito', $credito);
                })
                ->orWhere(function ($invertido) use ($debito, $credito) {
                    $invertido
                        ->where('debito', $credito)
                        ->where('credito', $debito);
                });
        });

        return $query->exists();
    }

    private function registrarPeriodo(?string $fecha): void
    {
        if ($fecha === null) {
            return;
        }

        if ($this->fechaDesde === null || $fecha < $this->fechaDesde) {
            $this->fechaDesde = $fecha;
        }

        if ($this->fechaHasta === null || $fecha > $this->fechaHasta) {
            $this->fechaHasta = $fecha;
        }
    }

    private function getCampo(array $row, array $valores, array $aliases, int $fallbackIndex)
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                return $row[$alias];
            }
        }

        return $valores[$fallbackIndex] ?? null;
    }

    private function esFilaVacia(array $movimiento): bool
    {
        $sinTexto = ($movimiento['descripcion'] === null)
            && ($movimiento['documento'] === null)
            && ($movimiento['asunto'] === null)
            && ($movimiento['dependencia'] === null);

        $sinImportes = ((float) $movimiento['debito'] === 0.0)
            && ((float) $movimiento['credito'] === 0.0);

        return $sinTexto && $sinImportes;
    }
}
