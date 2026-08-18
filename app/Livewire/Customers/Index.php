<?php

declare(strict_types=1);

namespace App\Livewire\Customers;

use App\Http\Requests\CustomerFilterRequest;
use App\Services\CustomersService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Clientes — catálogo del portal (customers.index).
 *
 * Estado, filtros (búsqueda, zona, ruta, estatus), paginación y estados de
 * consulta (éxito/vacío/error) viven aquí; la vista solo renderiza
 * arquetipos. Consume CustomersService::list (GET /customers, contrato v2).
 *
 * La intersección de zonas la aplica el Sincronizador contra el JWT; Laravel
 * no convierte permisos en filtros (plan §5.1).
 */
class Index extends Component
{
    public string $search = '';

    public ?int $zoneId = null;

    public ?int $routeId = null;

    public string $status = '';

    public int $page = 1;

    public int $perPage = 10;

    public function render()
    {
        return view('modules.customers._index-content');
    }

    /**
     * Resultado crudo del servicio (success, data, meta, code, message).
     */
    public function getResultProperty(): array
    {
        return app(CustomersService::class)->list($this->filterPayload());
    }

    /**
     * Filas de la tabla de clientes.
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
     * Mensaje de error funcional para el estado de error; null si hay éxito.
     */
    public function getErrorProperty(): ?string
    {
        if ($this->result['success']) {
            return null;
        }

        return (string) ($this->result['message'] ?? 'No se pudo conectar con el servicio.');
    }

    /**
     * Dispara la consulta desde el formulario de filtros.
     */
    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new CustomerFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los filtros del catálogo de clientes.'));
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->page = 1;
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
        $this->reset('search', 'zoneId', 'routeId', 'status', 'page');
    }

    /**
     * Filtros del componente en snake_case (forma del contrato v2).
     *
     * @return array<string, mixed>
     */
    private function filterInput(): array
    {
        return [
            'search' => $this->search,
            'zone_id' => $this->zoneId,
            'route_id' => $this->routeId,
            'status' => $this->status,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * Payload hacia la API: solo campos validados con las reglas de
     * CustomerFilterRequest y con valor (guidelines §1.4).
     *
     * @return array<string, mixed>
     */
    private function filterPayload(): array
    {
        try {
            $validated = Validator::make($this->filterInput(), (new CustomerFilterRequest)->rules())->validated();
        } catch (ValidationException) {
            return ['page' => 1, 'per_page' => $this->perPage];
        }

        return collect($validated)
            ->reject(fn (mixed $value): bool => is_null($value) || $value === '')
            ->all();
    }
}
