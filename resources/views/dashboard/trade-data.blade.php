@extends('layouts.dashboard')

@section('title', 'Indonesia Trade Data Dashboard')

@section('content')
<!-- Trade Data Ticker (News-style sliding header) -->
@include('components.trade-ticker')

<div class="container-fluid px-2" style="padding-top: 1rem;">
    <!-- Summary Statistics Cards -->
    <div class="row g-2 mb-1">
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
    <div class="search-container mb-3">
        <form method="GET" action="{{ route('dashboard.trade-data') }}" class="row gx-2 g-3 align-items-end">
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

            <div class="col-md-2">
                <label for="hs_level" class="form-label fw-medium">HS Level</label>
                <select class="form-select" name="hs_level" id="hs_level" onchange="this.form.submit()">
                    <option value="2" {{ ($hsLevel ?? '2') == '2' ? 'selected' : '' }}>HS Level 2</option>
                    <option value="4" {{ ($hsLevel ?? '2') == '4' ? 'selected' : '' }}>HS Level 4</option>
                    <option value="6" {{ ($hsLevel ?? '2') == '6' ? 'selected' : '' }}>HS Level 6</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                    
                    @if($search)
                        <a href="{{ route('dashboard.trade-data') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    @endif
                </div>
            </div>

            
            <div class="col-md-2 text-end">
                <div class="fw-bold text-primary" style="font-size: 1.1rem;">
                    {{ number_format($tradeData->total()) }} hasil
                </div>
            </div>
        </form>
    </div>

    @php
        $currentLevel = (int) ($hsLevel ?? 2);
        $nextLevel = $currentLevel + 2;
    @endphp

    <!-- Charts Row: Treemap & Top Sectors -->
    <div class="row g-2 mb-3">
        <!-- Treemap (Products) -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-sitemap me-2"></i>
                        Porsi Impor Produk (20 Teratas)
                    </h5>
                    @if($currentLevel > 2)
                        @php
                            $parentLevel = $currentLevel - 2;
                            $parentPrefix = '';
                            if ($currentLevel > 2 && !empty($searchPrefix)) {
                                // If it's something like "27.10" (length 5), back to "27" (length 2)
                                // If it's HS4 "27.10", next is HS6 "27.10.12". 
                                // Parent of HS4 (27.10) is HS2 (27).
                                if (str_contains($searchPrefix, '.')) {
                                    $parts = explode('.', $searchPrefix);
                                    array_pop($parts);
                                    $parentPrefix = implode('.', $parts);
                                }
                            }
                        @endphp
                        <a href="{{ route('dashboard.trade-data', array_merge(request()->except(['page']), ['hs_level' => $parentLevel, 'search_prefix' => $parentPrefix, 'search' => ''])) }}" 
                           class="btn btn-xs btn-outline-light py-0 px-2" 
                           style="font-size: 0.75rem;"
                           title="Kembali ke HS {{ $parentLevel }}{{ $parentPrefix ? ' (' . $parentPrefix . ')' : '' }}">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    @endif
                </div>
                <div class="card-body p-2">
                    <div id="treemap-chart" style="height: 400px;"></div>
                </div>
            </div>
        </div>

        <!-- Top Sectors -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-pie me-2"></i>
                        Top Sektor (2024)
                    </h5>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        @foreach($topSectors->take(5) as $sector)
                            <div class="list-group-item bg-transparent border-bottom border-secondary p-2 px-3" style="border-color: var(--pustik-border-card) !important;">
                                <div class="d-flex align-items-center h-100">
                                    <!-- Left: Metrics (Fixed Width) -->
                                    <div class="d-flex flex-column me-3" style="min-width: 80px;">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 mb-1 align-self-start">HS {{ $sector->sector_code }}</span>
                                        <span class="fw-bold lh-1 mb-1" style="color: white; font-size: 0.95rem;">
                                            ${{ number_format($sector->total_value / 1000000, 1) }}B
                                        </span>
                                        <small style="color: var(--pustik-text-light); font-size: 0.75rem; opacity: 0.8;">
                                            {{ number_format($sector->share_percentage, 1) }}% share
                                        </small>
                                    </div>
                                    
                                    <!-- Right: Title (Flexible) -->
                                    <div class="flex-grow-1 border-start border-secondary ps-3" style="border-color: rgba(255,255,255,0.1) !important;">
                                        <div class="small text-light text-opacity-90 lh-sm">
                                            {{ Str::limit($sector->sector_name, 70) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if($topSectors->count() == 0)
                            <div class="p-4 text-center text-muted">
                                No sector data available
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
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
                                    <span class="badge bg-dark text-light">
                                        {{ $tradeData->firstItem() ?? 0 }} - {{ $tradeData->lastItem() ?? 0 }} 
                                        dari {{ number_format($tradeData->total()) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 defi-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">HS4</th>
                                            <th style="width: 65px;">Kode</th>
                                            <th style="min-width: 200px;">Label Produk</th>
                                            <th style="width: 100px;" class="text-end">Nilai impor<br>tahun 2020</th>
                                            <th style="width: 100px;" class="text-end">Nilai impor<br>tahun 2021</th>
                                            <th style="width: 100px;" class="text-end">Nilai impor<br>tahun 2022</th>
                                            <th style="width: 100px;" class="text-end">Nilai impor<br>tahun 2023</th>
                                            <th style="width: 100px;" class="text-end">Nilai impor<br>tahun 2024</th>
                                            <th style="width: 80px;">Trend (5Y)</th>
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
                                                    @if($currentLevel < 6)
                                                        <a href="{{ route('dashboard.trade-data', array_merge(request()->except(['page']), ['hs_level' => $nextLevel, 'search_prefix' => $item->kode_hs, 'search' => ''])) }}" title="Drill down to HS Level {{ $nextLevel }}">
                                                            <span class="hs-code">{{ $item->kode_hs }}</span>
                                                        </a>
                                                    @else
                                                        <span class="hs-code">{{ $item->kode_hs }}</span>
                                                    @endif
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
                                                <td>
                                                    <div id="sparkline-{{ str_replace('.', '-', $item->kode_hs) }}" 
                                                         class="sparkline-chart"
                                                         data-values="{{ json_encode([$item->value_2020, $item->value_2021, $item->value_2022, $item->value_2023, $item->value_2024]) }}"></div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
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

                    .sector-card .text-muted {
                        color: var(--pustik-text-dark) !important;
                    }

                    .sector-card p {
                        color: var(--pustik-text-light);
                    }
                </style>
                @endpush
                
                @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Treemap Chart
                        var treemapOptions = {
                            series: [{
                                data: @json($treemapData)
                            }],
                            chart: {
                                type: 'treemap',
                                height: 400,
                                background: 'transparent',
                                foreColor: 'var(--pustik-text-dark)',
                                toolbar: {
                                    show: false
                                },
                                events: {
                                    dataPointSelection: function(event, chartContext, config) {
                                        @if($currentLevel < 6)
                                            const dataPointIndex = config.dataPointIndex;
                                            const seriesIndex = config.seriesIndex;
                                            const hsCode = config.w.config.series[seriesIndex].data[dataPointIndex].x;
                                            
                                            // Construct drill-down URL
                                            const baseUrl = "{{ route('dashboard.trade-data') }}";
                                            const params = new URLSearchParams(window.location.search);
                                            
                                            // Update params for drill-down
                                            params.set('hs_level', '{{ $nextLevel }}');
                                            params.set('search_prefix', hsCode);
                                            params.delete('search'); // Clear search on drill-down
                                            params.delete('page');   // Reset pagination
                                            
                                            window.location.href = `${baseUrl}?${params.toString()}`;
                                        @endif
                                    }
                                }
                            },
                            theme: {
                                mode: 'dark',
                                monochrome: {
                                    enabled: true,
                                    color: '#3b82f6', // var(--pustik-primary)
                                    shadeTo: 'dark',
                                    shadeIntensity: 0.5
                                }
                            },
                            plotOptions: {
                                treemap: {
                                    distributed: true,
                                    enableShades: false,
                                    dataLabels: {
                                        format: 'scale'
                                    }
                                }
                            },
                            tooltip: {
                                theme: 'dark',
                                y: {
                                    formatter: function(value, opts) {
                                        const percentage = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex].share_percentage;
                                        
                                        // Format dollar value
                                        let fullValue = value * 1000;
                                        let formattedValue;
                                        if (fullValue >= 1000000000) {
                                            formattedValue = '$' + (fullValue / 1000000000).toFixed(2) + ' B';
                                        } else {
                                            formattedValue = '$' + (fullValue / 1000000).toFixed(2) + ' M';
                                        }

                                        return `${formattedValue} (${percentage.toFixed(2)}%)`;
                                    },
                                    title: {
                                        formatter: function(seriesName, opts) {
                                            const seriesIndex = opts.seriesIndex;
                                            const dataPointIndex = opts.dataPointIndex;
                                            const fullLabel = opts.w.config.series[seriesIndex].data[dataPointIndex].full_label;
                                            return fullLabel || ''; // Return the full product label
                                        }
                                    }
                                }
                            },
                            stroke: {
                                show: true,
                                width: 2,
                                colors: ['#161b22'] // var(--pustik-bg-card) for a subtle effect
                            },
                            dataLabels: {
                                style: {
                                    fontSize: '12px',
                                    fontFamily: 'Inter, sans-serif',
                                },
                            },
                        };

                        var treemapChart = new ApexCharts(document.querySelector("#treemap-chart"), treemapOptions);
                        treemapChart.render();

                        // Render Sparklines
                        document.querySelectorAll('.sparkline-chart').forEach(function(chartElement) {
                            const values = JSON.parse(chartElement.dataset.values);
                            const hsCode = chartElement.id.replace('sparkline-', ''); // Get HS code for context if needed

                            const sparklineOptions = {
                                series: [{
                                    data: values
                                }],
                                chart: {
                                    type: 'line',
                                    height: 35,
                                    width: 100,
                                    sparkline: {
                                        enabled: true
                                    },
                                    foreColor: 'var(--pustik-text-dark)', // Use dark text for general elements
                                    // Custom colors based on trend
                                    animations: {
                                        enabled: true,
                                        easing: 'linear',
                                        dynamicAnimation: {
                                            speed: 500
                                        }
                                    }
                                },
                                stroke: {
                                    curve: 'smooth',
                                    width: 3,
                                    // Dynamically set color based on trend (last vs first value)
                                    colors: [values[values.length - 1] > values[0] ? '#22c55e' : (values[values.length - 1] < values[0] ? '#ef4444' : '#9ca3af')]
                                },
                                fill: {
                                    opacity: 0.5,
                                    type: 'solid',
                                    gradient: {
                                        enabled: true,
                                        opacityFrom: 0.5,
                                        opacityTo: 0,
                                    }
                                },
                                tooltip: {
                                    enabled: true,
                                    theme: 'dark',
                                    x: {
                                        show: false
                                    },
                                    y: {
                                        formatter: function(val) {
                                            return "$" + (val / 1000).toFixed(0) + "K";
                                        }
                                    },
                                    marker: {
                                        show: false
                                    }
                                },
                                // No X/Y axis
                                xaxis: {
                                    labels: {
                                        show: false
                                    },
                                    axisBorder: {
                                        show: false
                                    },
                                    axisTicks: {
                                        show: false
                                    }
                                },
                                yaxis: {
                                    labels: {
                                        show: false
                                    },
                                    axisBorder: {
                                        show: false
                                    },
                                    axisTicks: {
                                        show: false
                                    }
                                },
                                grid: {
                                    show: false,
                                    padding: {
                                        left: 0,
                                        right: 0
                                    }
                                },
                                legend: {
                                    show: false
                                },
                                responsive: [{
                                    breakpoint: 768,
                                    options: {
                                        chart: {
                                            width: 80
                                        }
                                    }
                                }]
                            };

                            // Add years to x-axis for tooltip if needed, but not visible
                            sparklineOptions.xaxis.categories = ['2020', '2021', '2022', '2023', '2024'];

                            var sparklineChart = new ApexCharts(chartElement, sparklineOptions);
                            sparklineChart.render();
                        });

                        // ... existing scripts
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
                        
                        const hour = new Date().getHours();
                        if (hour >= 9 && hour <= 17) {
                            console.log('Business hours detected');
                        } else {
                            if (window.tradeTicker) {
                                setTimeout(() => {
                                    window.tradeTicker.setSpeed('slow');
                                }, 2000);
                            }
                        }
                    });

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
                </script>
                @endpush
                