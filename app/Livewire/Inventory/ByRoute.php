<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Http\Requests\InventoryRouteFilterRequest;
use App\Services\InventoryService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Inventario por Ruta — piloto del portal (inventory.routes).
 *
 * Consulta de existencias por ruta (≡ vendedor, contrato v2 §6.4). Estado,
 * filtros y paginación viven aquí; la vista solo renderiza arquetipos.
 *
 * Lectura estricta: el piloto no edita existencias, cierra rutas ni
 * reconcilia movimientos (plan §5.2). Si el endpoint no está habilitado, la
 * vista indica el estado honesto correspondiente — el servicio jamás inventa
 * datos (checklist §9).
 */
class ByRoute extends Component
{
    public ?int $routeId = null;

    public ?string $asOf = null;

    public ?int $zoneId = null;

    public int $page = 1;

    public int $perPage = 25;

    /** True tras la primera consulta: la pantalla no muestra error antes de consultar. */
    public bool $consulted = false;

    public function render()
    {
        return view('modules.inventory._routes-content');
    }

    /**
     * Resultado crudo del servicio (success, data, meta, code, message).
     */
    public function getResultProperty(): array
    {
        if (! $this->consulted) {
            return [];
        }

        return app(InventoryService::class)->byRoute($this->filterPayload());
    }

    /**
     * Filas de la tabla del piloto.
     */
    public function getRowsProperty(): array
    {
        return $this->result['success'] ? ($this->result['data'] ?? []) : [];
    }

    /**
     * Meta de paginación (page, per_page, total, last_page).
     */
    public function getMetaProperty(): array
    {
        return $this->result['success'] ? ($this->result['meta'] ?? []) : [];
    }

    /**
     * Código funcional del error (NOT_FOUND, API_UNAVAILABLE, FORBIDDEN_ZONE...).
     */
    public function getErrorCodeProperty(): ?string
    {
        if ($this->result['success'] || empty($this->result)) {
            return null;
        }

        return (string) ($this->result['code'] ?? 'API_UNAVAILABLE');
    }

    /**
     * Mensaje de error funcional para el estado de error; null si hay éxito.
     */
    public function getErrorProperty(): ?string
    {
        if ($this->result['success'] || empty($this->result)) {
            return null;
        }

        return (string) ($this->result['message'] ?? 'No se pudo conectar con el servicio.');
    }

    /**
     * Dispara la consulta del piloto desde el formulario de filtros.
     */
    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new InventoryRouteFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('La ruta es obligatoria para consultar el piloto.'));
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->page = 1;
        $this->consulted = true;
        $this->resetErrorBag();
    }

    public function irAPagina(int $page)
    {
        $lastPage = (int) ($this->meta['last_page'] ?? 1);

        if ($page < 1 || $page > $lastPage) {
            return;
        }

        $this->page = $page;
    }

    public function limpiar()
    {
        $this->reset('routeId', 'asOf', 'zoneId', 'page', 'consulted');
    }

    /**
     * Filtros del componente en snake_case (forma del contrato v2).
     *
     * @return array<string, mixed>
     */
    private function filterInput(): array
    {
        return [
            'route_id' => $this->routeId,
            'as_of' => $this->asOf,
            'zone_id' => $this->zoneId,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * Payload hacia la API: solo campos validados con las reglas de
     * InventoryRouteFilterRequest y con valor (guidelines §1.4).
     *
     * @return array<string, mixed>
     */
    private function filterPayload(): array
    {
        try {
            $validated = Validator::make($this->filterInput(), (new InventoryRouteFilterRequest)->rules())->validated();
        } catch (ValidationException) {
            return ['route_id' => $this->routeId ?? null, 'page' => 1, 'per_page' => $this->perPage];
        }

        return collect($validated)
            ->reject(fn (mixed $value): bool => is_null($value) || $value === '')
            ->all();
    }
}
