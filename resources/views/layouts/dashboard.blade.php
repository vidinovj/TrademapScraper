<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Trade Data Dashboard')</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Pustik Custom CSS -->
    <style>
        :root {
            --pustik-primary: #1e40af;
            --pustik-primary-dark: #1e3a8a;
            --pustik-secondary: #3b82f6;
            --pustik-blue-50: #eff6ff;
            --pustik-green-50: #f0fdf4;
            --pustik-purple-50: #faf5ff;
            --pustik-yellow-50: #fefce8;
            --pustik-gray-50: #f9fafb;
            --pustik-gray-800: #1f2937;
            --pustik-gray-600: #4b5563;
        }
        
        body {
            background-color: var(--pustik-gray-50);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--pustik-gray-800);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--pustik-primary) !important;
            font-size: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 0.5rem;
            background: white;
        }
        
        .card-header {
            background: var(--pustik-primary);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
            padding: 1.5rem;
            border: none;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .stat-card {
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card.blue {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }
        
        .stat-card.green {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        
        .stat-card.purple {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        }
        
        .stat-card.yellow {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }
        
        .stat-card h3 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .table th {
            background-color: var(--pustik-blue-50);
            color: var(--pustik-primary);
            font-weight: 600;
            border: none;
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table td {
            border-color: #e5e7eb;
            padding: 0.75rem;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: var(--pustik-gray-50);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--pustik-primary);
            border-color: var(--pustik-primary);
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--pustik-primary-dark);
            border-color: var(--pustik-primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(30, 64, 175, 0.3);
        }
        
        .btn-outline-primary {
            color: var(--pustik-primary);
            border-color: var(--pustik-primary);
            border-radius: 0.375rem;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--pustik-primary);
            border-color: var(--pustik-primary);
            transform: translateY(-1px);
        }
        
        .btn-outline-secondary {
            color: var(--pustik-gray-600);
            border-color: #d1d5db;
            border-radius: 0.375rem;
        }
        
        .search-container {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .form-control-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
        }
        
        .form-control-lg:focus {
            border-color: var(--pustik-primary);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.25);
        }
        
        .form-select {
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
        }
        
        .form-select:focus {
            border-color: var(--pustik-primary);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.25);
        }
        
        .pagination .page-link {
            color: var(--pustik-primary);
            border-color: #d1d5db;
            border-radius: 0.375rem;
            margin: 0 0.125rem;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--pustik-primary);
            border-color: var(--pustik-primary);
        }
        
        .pagination .page-link:hover {
            background-color: var(--pustik-blue-50);
            border-color: var(--pustik-primary);
        }
        
        .hs-code {
            font-family: 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', monospace;
            font-weight: 600;
            color: var(--pustik-primary);
            background: var(--pustik-blue-50);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .value-cell {
            text-align: right;
            font-family: 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', monospace;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .sector-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            height: 100%;
            background: white;
            transition: all 0.2s ease;
        }
        
        .sector-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .badge {
            font-weight: 500;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e5e7eb;
        }
        
        .nav-link {
            color: var(--pustik-gray-600) !important;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .nav-link:hover {
            color: var(--pustik-primary) !important;
        }
        
        footer {
            background: white;
            border-top: 1px solid #e5e7eb;
            margin-top: 3rem;
        }
        
        .product-label {
            line-height: 1.4;
            color: var(--pustik-gray-800);
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .stat-card h3 {
                font-size: 1.875rem;
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .search-container {
                padding: 1rem;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard.trade-data') }}">
                <i class="fas fa-chart-line me-2"></i>
                Data Impor Indonesia
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard.trade-data') }}">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard.export') }}">
                            <i class="fas fa-download me-1"></i> Export Data
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard.jobs') }}">
                            <i class="fas fa-cogs me-1"></i> Jobs
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-clock me-1"></i> 
                            {{ now()->format('d M Y, H:i') }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid mt-4">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="py-4">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        <strong>Data Engineering Test Demo</strong> - 
                        <a href="https://trademap.org" target="_blank" class="text-decoration-none">Trademap.org</a>
                    </p>
                    <small class="text-muted">Kementerian Luar Negeri Republik Indonesia</small>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted mb-0">
                        <i class="fas fa-code me-1"></i>
                        Laravel + Puppeteer + Bootstrap 5
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Add loading state to forms
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                    }
                    form.classList.add('loading');
                });
            });
            
            // Auto-submit search form with delay
            const searchInput = document.getElementById('search');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.form.submit();
                    }, 500);
                });
            }
        });
        
        // Copy to clipboard functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('HS Code copied to clipboard!');
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
    </script>
    
    @stack('scripts')
</body>
</html>