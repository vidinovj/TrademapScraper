@extends('layouts.dashboard')

@section('title', 'Indonesia Trade Data Dashboard')

@section('content')
<div class="row">
    <!-- Summary Statistics -->
    <div class="col-12 mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3>{{ number_format($summaryStats['total_records']) }}</h3>
                    <p><i class="bi bi-database me-1"></i> Total Records</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3>${{ number_format($summaryStats['total_value_2024'] / 1000000, 1) }}B</h3>
                    <p><i class="bi bi-graph-up me-1"></i> 2024 Import Value</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3>{{ number_format($summaryStats['total_hs_codes']) }}</h3>
                    <p><i class="bi bi-tags me-1"></i> HS Codes</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3>{{ $summaryStats['last_update']?->diffForHumans() ?? 'N/A' }}</h3>
                    <p><i class="bi bi-clock me-1"></i> Last Updated</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="col-12">
        <div class="search-container">
            <form method="GET" action="{{ route('dashboard.trade-data') }}" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Products or HS Codes</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ $search }}"
                               placeholder="Enter HS code or product name...">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label for="per_page" class="form-label">Rows per page</label>
                    <select class="form-select" name="per_page" id="per_page" onchange="this.form.submit()">
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                        <option value="250" {{ $perPage == 250 ? 'selected' : '' }}>250 per page</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        
                        @if($search)
                            <a href="{{ route('dashboard.trade-data') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </a>
                        @endif
                        
                        <a href="{{ route('dashboard.export', ['search' => $search]) }}" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
                
                <div class="col-md-2 text-end">
                    <label class="form-label">Total Results</label>
                    <p class="mb-0 fw-bold text-primary fs-5">
                        {{ number_format($tradeData->total()) }}
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>
                            Indonesia Import Data by Product (HS Code Level)
                        </h5>
                        <small class="opacity-75">Unit: US Dollar thousand</small>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-light text-dark">
                            {{ $tradeData->firstItem() ?? 0 }} - {{ $tradeData->lastItem() ?? 0 }} 
                            of {{ number_format($tradeData->total()) }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;">HS4</th>
                                <th style="width: 100px;">Code</th>
                                <th style="min-width: 300px;">Product Label</th>
                                <th style="width: 120px;" class="text-end">Imported value<br>in 2020</th>
                                <th style="width: 120px;" class="text-end">Imported value<br>in 2021</th>
                                <th style="width: 120px;" class="text-end">Imported value<br>in 2022</th>
                                <th style="width: 120px;" class="text-end">Imported value<br>in 2023</th>
                                <th style="width: 120px;" class="text-end">Imported value<br>in 2024</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tradeData as $item)
                                <tr>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="copyToClipboard('{{ $item->kode_hs }}')"
                                                title="Click to copy HS code">
                                            <i class="bi bi-plus-square"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <span class="hs-code">{{ $item->kode_hs }}</span>
                                    </td>
                                    <td>
                                        <div class="product-label">
                                            {{ Str::limit($item->product_label, 80) }}
                                            @if(strlen($item->product_label) > 80)
                                                <span class="text-muted">...</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="value-cell">
                                        {{ $item->value_2020 > 0 ? number_format($item->value_2020) : '-' }}
                                    </td>
                                    <td class="value-cell">
                                        {{ $item->value_2021 > 0 ? number_format($item->value_2021) : '-' }}
                                    </td>
                                    <td class="value-cell">
                                        {{ $item->value_2022 > 0 ? number_format($item->value_2022) : '-' }}
                                    </td>
                                    <td class="value-cell">
                                        {{ $item->value_2023 > 0 ? number_format($item->value_2023) : '-' }}
                                    </td>
                                    <td class="value-cell">
                                        <strong class="text-primary">
                                            {{ $item->value_2024 > 0 ? number_format($item->value_2024) : '-' }}
                                        </strong>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox display-1"></i>
                                            <h5 class="mt-3">No trade data found</h5>
                                            <p>Try adjusting your search criteria or check if the scraper has been run.</p>
                                            
                                            @if(empty($search))
                                                <a href="{{ url('/') }}" class="btn btn-primary">
                                                    <i class="bi bi-arrow-clockwise me-1"></i> Run Scraper
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            @if($tradeData->hasPages())
                <div class="card-footer bg-transparent">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <p class="text-muted mb-0">
                                Showing {{ $tradeData->firstItem() ?? 0 }} to {{ $tradeData->lastItem() ?? 0 }} 
                                of {{ number_format($tradeData->total()) }} results
                            </p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end">
                                {{ $tradeData->appends(request()->query())->links('pagination::bootstrap-4') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Top Sectors Summary -->
    @if($topSectors->count() > 0)
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart me-2"></i>
                        Top Import Sectors (2024)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($topSectors->take(5) as $sector)
                            <div class="col-md-4 col-lg-2 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-primary me-2">{{ $sector->sector_code }}</span>
                                        <small class="text-muted">HS {{ $sector->sector_code }}</small>
                                    </div>
                                    <p class="small mb-1">{{ Str::limit($sector->sector_name, 40) }}</p>
                                    <h6 class="text-primary mb-0">${{ number_format($sector->total_value / 1000000, 1) }}B</h6>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Toast Notification -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
    <div id="copyToast" class="toast" role="alert">
        <div class="toast-header">
            <i class="bi bi-check-circle text-success me-2"></i>
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            HS Code copied to clipboard!
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .product-label {
        line-height: 1.4;
        max-width: 300px;
    }
    
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #198754;
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1060;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .btn-outline-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    tbody tr:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
</style>
@endpush

@push('scripts')
<script>
    // Enhanced copy to clipboard with toast
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            const toastEl = document.getElementById('copyToast');
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
        });
    }
    
    // Add smooth scrolling for pagination
    document.addEventListener('DOMContentLoaded', function() {
        const paginationLinks = document.querySelectorAll('.pagination a');
        paginationLinks.forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(() => {
                    document.querySelector('.card-header').scrollIntoView({
                        behavior: 'smooth'
                    });
                }, 100);
            });
        });
    });
</script>
@endpush