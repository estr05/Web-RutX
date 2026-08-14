<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\ReportFilterRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ReportFilterRequestTest
 *
 * Verifica reglas de validación del filtro de reportes y que toApiPayload()
 * construye el payload exclusivamente desde validated() (guidelines §1.4):
 * - range restringido a diario/semanal/mensual.
 * - Fechas Y-m-d con orden date_to >= date_from.
 * - IDs enteros >= 1.
 * - Valores nulos/vacíos no se reenvían a la API.
 */
class ReportFilterRequestTest extends TestCase
{
    private const ENDPOINT = '/_test/report-filter';

    protected function setUp(): void
    {
        parent::setUp();

        Route::post(self::ENDPOINT, function (ReportFilterRequest $request) {
            return response()->json($request->toApiPayload());
        });
    }

    public function test_valid_payload_is_cleaned_for_api(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'range' => 'semanal',
            'date_from' => '2026-08-14',
            'date_to' => '2026-08-14',
            'zone_id' => 1,
            'route_id' => 3,
        ]);

        $response->assertOk()
            ->assertExactJson([
                'range' => 'semanal',
                'date_from' => '2026-08-14',
                'date_to' => '2026-08-14',
                'zone_id' => 1,
                'route_id' => 3,
            ]);
    }

    public function test_null_values_are_not_sent_to_api(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'range' => 'diario',
            'date_from' => null,
            'zone_id' => null,
        ]);

        $response->assertOk()
            ->assertExactJson(['range' => 'diario']);
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, ['range' => 'anual'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['range']);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, ['date_from' => '14/08/2026'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_date_order_is_validated(): void
    {
        $this->postJson(self::ENDPOINT, [
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-14',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_invalid_ids_are_rejected(): void
    {
        $this->postJson(self::ENDPOINT, ['zone_id' => 0, 'route_id' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['zone_id', 'route_id']);
    }
}
