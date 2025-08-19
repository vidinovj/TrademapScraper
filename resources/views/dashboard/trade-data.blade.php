@extends('layouts.dashboard')

@section('title', 'Indonesia Trade Data Dashboard')

@section('content')
<div class="container mx-auto px-4">
    <!-- Trade Data Ticker (News-style sliding header) -->
    @include('components.trade-ticker')

    <!-- Summary Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card blue">
                <h3>{{ number_format($summaryStats['total_records']) }}</h3>
                <p><i class="fas fa-database me-1"></i> Total Records</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card green">
                <h3>${{ number_format($summaryStats['total_value_2024'] / 1000000, 1) }}B</h3>
                <p><i class="fas fa-chart-line me-1"></i> Nilai Impor 2024</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card purple">
                <h3>{{ number_format($summaryStats['total_hs_codes']) }}</h3>
                <p><i class="fas fa-tags me-1"></i> Kode HS</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card yellow">
                <h3>{{ $summaryStats['last_update']?->diffForHumans() ?? 'N/A' }}</h3>
                <p><i class="fas fa-clock me-1"></i> Terakhir Update</p>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-container">
        <form method="GET" action="{{ route('dashboard.trade-data') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label fw-medium">Cari Produk atau Kode HS</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="search" 
                           name="search" 
                           value="{{ $search }}"
                           placeholder="Masukkan kode HS atau nama produk...">
                </div>
            </div>
            
            <div class="col-md-2">
                <label for="per_page" class="form-label fw-medium">Baris per halaman</label>
                <select class="form-select" name="per_page" id="per_page" onchange="this.form.submit()">
                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 per halaman</option>
                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per halaman</option>
                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per halaman</option>
                    <option value="250" {{ $perPage == 250 ? 'selected' : '' }}>250 per halaman</option>
                </select>
            </div>
            
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                    
                    @if($search)
                        <a href="{{ route('dashboard.trade-data') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    @endif
                    
                    <a href="{{ route('dashboard.export', ['search' => $search]) }}" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-download me-1"></i> Export CSV
                    </a>
                    
                    <button type="button" class="btn btn-outline-success" onclick="refreshTicker()">
                        <i class="fas fa-sync me-1"></i> Refresh Ticker
                    </button>
                </div>
            </div>
            
            <div class="col-md-2 text-end">
                <div class="fw-bold text-primary" style="font-size: 1.1rem;">
                    {{ number_format($tradeData->total()) }} hasil
                </div>
            </div>
        </form>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-1 fw-bold">
                        <i class="fas fa-table me-2"></i>
                        Data Impor Indonesia berdasarkan Produk (Level Kode HS)
                    </h5>
                    <small class="opacity-75">Unit: US Dollar ribu</small>
                </div>
                <div class="col-auto">
                    <span class="badge bg-light text-dark">
                        {{ $tradeData->firstItem() ?? 0 }} - {{ $tradeData->lastItem() ?? 0 }} 
                        dari {{ number_format($tradeData->total()) }}
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
                            <th style="width: 100px;">Kode</th>
                            <th style="min-width: 300px;">Label Produk</th>
                            <th style="width: 120px;" class="text-end">Nilai impor<br>tahun 2020</th>
                            <th style="width: 120px;" class="text-end">Nilai impor<br>tahun 2021</th>
                            <th style="width: 120px;" class="text-end">Nilai impor<br>tahun 2022</th>
                            <th style="width: 120px;" class="text-end">Nilai impor<br>tahun 2023</th>
                            <th style="width: 120px;" class="text-end">Nilai impor<br>tahun 2024</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tradeData as $item)
                            <tr>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="copyToClipboard('{{ $item->kode_hs }}')"
                                            title="Klik untuk copy kode HS">
                                        <i class="fas fa-plus"></i>
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
                                    <strong style="color: var(--pustik-primary);">
                                        {{ $item->value_2024 > 0 ? number_format($item->value_2024) : '-' }}
                                    </strong>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox display-1"></i>
                                        <h5 class="mt-3">Tidak ada data perdagangan ditemukan</h5>
                                        <p>Coba sesuaikan kriteria pencarian atau pastikan scraper sudah dijalankan.</p>
                                        
                                        @if(empty($search))
                                            <a href="{{ url('/') }}" class="btn btn-primary mt-2">
                                                <i class="fas fa-sync me-1"></i> Jalankan Scraper
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
                            Menampilkan {{ $tradeData->firstItem() ?? 0 }} sampai {{ $tradeData->lastItem() ?? 0 }} 
                            dari {{ number_format($tradeData->total()) }} hasil
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

    <!-- Top Sectors Summary -->
    @if($topSectors->count() > 0)
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-chart-pie me-2"></i>
                            Top Sektor Impor (2024)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($topSectors->take(5) as $index => $sector)
                                <div class="col-md-4 col-lg-2 mb-3">
                                    <div class="sector-card">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="badge bg-primary me-2">{{ $sector->sector_code }}</span>
                                            <small class="text-muted">HS {{ $sector->sector_code }}</small>
                                        </div>
                                        <p class="small mb-1 fw-medium">{{ Str::limit($sector->sector_name, 40) }}</p>
                                        <h6 class="mb-0" style="color: var(--pustik-primary);">
                                            ${{ number_format($sector->total_value / 1000000, 1) }}B
                                        </h6>
                                        <small class="text-muted">{{ number_format($sector->record_count) }} produk</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
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
        background: var(--pustik-primary);
        color: white;
        padding: 12px 20px;
        border-radius: 0.375rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 1060;
        transform: translateX(100%);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .toast-notification.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .sector-card:hover {
        border-color: var(--pustik-primary);
    }
    
    .btn-outline-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(30, 64, 175, 0.2);
    }
    
    tbody tr:hover .hs-code {
        background-color: var(--pustik-primary);
        color: white;
    }
    
    .form-label {
        color: var(--pustik-gray-800);
        font-weight: 500;
    }
</style>
@endpush

@push('scripts')
<script>
    // Enhanced copy to clipboard with toast
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Kode HS berhasil disalin ke clipboard!');
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            showToast('Gagal menyalin ke clipboard', 'error');
        });
    }
    
    // Refresh ticker manually
    function refreshTicker() {
        if (window.tradeTicker) {
            showToast('Memperbarui data ticker...', 'info');
            
            fetch('/api/ticker/refresh', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.tradeTicker.loadLatestData();
                        showToast('Data ticker berhasil diperbarui!', 'success');
                    }
                })
                .catch(error => {
                    showToast('Gagal memperbarui ticker', 'error');
                });
        }
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
        
        // Add search placeholder animation
        const searchInput = document.getElementById('search');
        if (searchInput) {
            const placeholders = [
                'Masukkan kode HS atau nama produk...',
                'Contoh: 27 untuk bahan bakar mineral...',
                'Contoh: Machinery untuk mesin...',
                'Contoh: 84 untuk reaktor nuklir...'
            ];
            let currentIndex = 0;
            
            setInterval(() => {
                if (!searchInput.value && document.activeElement !== searchInput) {
                    searchInput.placeholder = placeholders[currentIndex];
                    currentIndex = (currentIndex + 1) % placeholders.length;
                }
            }, 3000);
        }
        
        // Add ticker style switching based on time
        const hour = new Date().getHours();
        if (hour >= 9 && hour <= 17) {
            // Business hours - normal style
            console.log('Business hours detected');
        } else {
            // Off hours - maybe different style
            if (window.tradeTicker) {
                setTimeout(() => {
                    window.tradeTicker.setSpeed('slow');
                }, 2000);
            }
        }
    });
</script>
@endpush