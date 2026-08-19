<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Http\Requests\SalesQueryRequest;
use App\Services\SalesService;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public array $filters = [
        'date_from' => null,
        'date_to' => null,
        'seller_id' => null,
        'status' => null,
        'search' => null,
    ];

    #[Locked]
    public array $sales = [];

    #[Locked]
    public array $meta = [];

    public bool $loading = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->loadSales();
    }

    public function updatedFilters(): void
    {
        $this->loadSales();
    }

    public function loadSales(): void
    {
        $this->loading = true;
        $this->errorMessage = null;

        $validator = Validator::make($this->filters, (new SalesQueryRequest)->rules());

        if ($validator->fails()) {
            $this->errorMessage = 'Filtros inválidos.';
            $this->loading = false;

            return;
        }

        $apiFilters = array_filter($validator->validated(), fn ($v) => $v !== null && $v !== '');
        // Anexamos paginación
        $apiFilters['page'] = $this->getPage();

        $response = app(SalesService::class)->list($apiFilters);

        if (! $response['success']) {
            $this->errorMessage = $response['message'] ?? 'Error al cargar las transacciones.';
            $this->sales = [];
            $this->meta = [];
        } else {
            $this->sales = $response['data'] ?? [];
            $this->meta = $response['meta'] ?? [];
        }

        $this->loading = false;
    }

    public function setPage($page, $pageName = 'page'): void
    {
        parent::setPage($page, $pageName);
        $this->loadSales();
    }

    public function render()
    {
        return view('livewire.sales.index');
    }
}
