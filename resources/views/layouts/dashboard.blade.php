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
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Pustik Custom CSS -->
    <style>
        :root {
            --pustik-primary: #3b82f6; /* blue-500 */
            --pustik-secondary: #10b981; /* green-500 */
            --pustik-purple: #a855f7;
            --pustik-yellow: #f59e0b;
            --pustik-bg-dark: #020617; /* Refined deep black */
            --pustik-bg-card: #161b22; /* Refined lighter card background */
            --pustik-border-card: #1e293b; /* Refined slate-800 border */
            --pustik-border: #334155; /* slate-700 for other borders */
            --pustik-text-light: #e2e8f0; /* slate-200 */
            --pustik-text-dark: #cbd5e1; /* slate-300 for better contrast */
        }
        
        body {
            background-color: var(--pustik-bg-dark);
            font-family: 'Inter', sans-serif;
            color: var(--pustik-text-light);
            font-size: 0.875rem;
        }
        
        .card {
            border: 1px solid var(--pustik-border-card);
            box-shadow: none;
            border-radius: 0.5rem;
            background: var(--pustik-bg-card);
        }
        
        .card-header {
            background: transparent;
            color: var(--pustik-text-light);
            border-bottom: 1px solid var(--pustik-border);
            padding: 1rem 1.25rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }

        /* Refined Stat Cards */
        .stat-card {
            padding: 0.75rem; /* p-3 */
            margin-bottom: 1rem;
            background: var(--pustik-bg-card);
            border: 1px solid var(--pustik-border-card);
            border-radius: 0.375rem;
        }
        
        .stat-card.blue { border-top: 3px solid var(--pustik-primary); }
        .stat-card.green { border-top: 3px solid var(--pustik-secondary); }
        .stat-card.purple { border-top: 3px solid var(--pustik-purple); }
        .stat-card.yellow { border-top: 3px solid var(--pustik-yellow); }

        .stat-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            line-height: 1.2;
        }

        /* Refined Stat Card Labels */
        .stat-card p {
            margin: 0;
            color: var(--pustik-text-light); /* Changed to light text for better readability */
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.1em; /* tracking-widest */
        }
        
        /* Refined Table */
        .table th {
            background: transparent;
            color: var(--pustik-text-dark);
            font-weight: 600;
            border: none;
            border-bottom: 1px solid var(--pustik-border-card);
            padding: 0.5rem 0.75rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table td {
            border-color: var(--pustik-border-card);
            padding: 0.5rem 0.75rem; /* py-2 */
            vertical-align: middle;
            color: var(--pustik-text-light);
            font-size: 0.8rem;
        }
        
        .table tbody tr:hover {
            background-color: #283549;
        }

        /*
         * DEFI TABLE OVERRIDE
         * A high-specificity override to defeat Bootstrap's default table styling
         * and prevent "grey on white" issues.
         */
        .defi-table {
            --bs-table-bg: var(--pustik-bg-card) !important;
            --bs-table-color: var(--pustik-text-light) !important;
            --bs-table-border-color: var(--pustik-border-card) !important;
            --bs-table-hover-bg: #283549 !important;
            color: var(--pustik-text-light) !important;
            background-color: var(--pustik-bg-card) !important;
        }

        .defi-table thead {
            background-color: var(--pustik-bg-dark) !important;
        }

        .defi-table th {
            background: var(--pustik-bg-dark) !important;
            color: var(--pustik-text-dark) !important; /* Muted text for header */
            border-bottom: 1px solid var(--pustik-border-card) !important;
            padding: 0.375rem 0.75rem !important; /* py-1.5 */
        }

        .defi-table td {
            padding: 0.375rem 0.75rem !important; /* py-1.5 */
            vertical-align: middle;
        }
        
        /* Monospace & Right-aligned numbers */
        .value-cell, .hs-code {
            font-family: 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', monospace;
            font-size: 0.8rem;
        }
        .value-cell {
            text-align: right;
        }
        
        .hs-code {
            font-weight: 600;
            color: var(--pustik-primary);
            background: rgba(59, 130, 246, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 0 1.5rem 1.5rem 1.5rem;
        }

        .main-content main {
            /* padding-top: 1.5rem; */
        }

        footer {
            background: var(--pustik-bg-dark);
            border-top: 1px solid var(--pustik-border-card);
            margin-top: 3rem;
        }
        
        /* Refined Sidebar */
        .sidebar {
            width: 260px;
            background-color: var(--pustik-bg-dark); /* Changed to match card background */
            border-right: 1px solid var(--pustik-border-card);
            padding: 1rem;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
        }
        
        .sidebar .navbar-brand {
            margin-bottom: 2rem;
            display: block;
            text-align: center;
            font-size: 1.25rem;
        }

        .sidebar .nav-link {
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            color: var(--pustik-text-dark) !important;
            font-weight: 500;
            border-left: 2px solid transparent;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }

        /* Refined Sidebar Active State */
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.05); /* bg-white/5 */
            color: var(--pustik-text-light) !important;
            border-left: 2px solid var(--pustik-primary);
            font-weight: 600;
        }
        
        .sidebar .nav-link:not(.active):hover {
            background-color: var(--pustik-bg-card);
            color: var(--pustik-text-light) !important;
        }

        .btn-success {
            background-color: var(--pustik-secondary);
            border-color: var(--pustik-secondary);
            color: white;
        }

        .btn-success:hover {
            background-color: #047857; /* darker green */
            border-color: #047857;
        }

        .btn-outline-success {
            color: var(--pustik-secondary);
            border-color: var(--pustik-secondary);
        }

        .btn-outline-success:hover {
            background-color: var(--pustik-secondary);
            color: white;
        }

        .btn-primary {
            background-color: var(--pustik-primary);
            border-color: var(--pustik-primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2563eb; /* A slightly darker shade of blue */
            border-color: #2563eb;
        }

        .btn-outline-primary {
            color: var(--pustik-primary);
            border-color: var(--pustik-primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--pustik-primary);
            color: white;
        }
        
        .search-container {
            background: var(--pustik-bg-card);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--pustik-border-card);
        }
        
        .input-group-text {
            background-color: var(--pustik-bg-dark);
            border-color: var(--pustik-border);
            color: var(--pustik-text-dark);
        }
        
        .form-control-lg, .form-select {
            background-color: var(--pustik-bg-dark);
            color: var(--pustik-text-dark); /* Changed to dim text color */
            border-radius: 0.375rem;
            border: 1px solid var(--pustik-border);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .form-control-lg::placeholder { /* Placeholder style */
            color: var(--pustik-text-dark);
            opacity: 1; /* Ensure full opacity */
        }
        
        
        .form-control-lg:focus, .form-select:focus {
            border-color: var(--pustik-primary);
            background-color: var(--pustik-bg-dark);
            color: var(--pustik-text-light);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }

        .form-label {
            color: var(--pustik-text-light);
            font-weight: 500;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        /* Media queries */
        @media (max-width: 992px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; width: 100%; }
            .sidebar .nav-item { display: inline-block; }
        }
        
        @media (max-width: 768px) {
            .stat-card h3 { font-size: 1.5rem; }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="sidebar">
        <a class="navbar-brand" href="{{ route('dashboard.trade-data') }}">
            <img src="/images/logo.png" alt="Logo" style="max-height: 60px; width: auto;">
        </a>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard.trade-data') ? 'active' : '' }}" href="{{ route('dashboard.trade-data') }}">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard.export') ? 'active' : '' }}" href="{{ route('dashboard.export') }}">
                    <i class="fas fa-download"></i> Export Data
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard.import') ? 'active' : '' }}" href="{{ route('dashboard.import') }}">
                    <i class="fas fa-upload"></i> Import Data
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="refreshTicker(); return false;">
                    <i class="fas fa-sync"></i> Refresh Ticker
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard.jobs') ? 'active' : '' }}" href="{{ route('dashboard.jobs') }}">
                    <i class="fas fa-cogs"></i> Jobs
                </a>
            </li>
        </ul>
        <div class="mt-auto" style="position: absolute; bottom: 1.5rem;">
            <span class="nav-link text-muted" style="font-size: 0.8rem">
                <i class="fas fa-clock me-1"></i> 
                {{ now()->format('d M Y, H:i') }}
            </span>
        </div>
    </div>

    <div class="main-content">
        <!-- Main Content -->
        <main>
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="py-4">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6 text-md-start text-center mb-2 mb-md-0">
                        <p class="mb-0" style="color: var(--pustik-text-dark);">
                            <strong>HarmoniData</strong> — Personal Research Project & Interactive Trade Terminal Data Source: <a href="https://trademap.org" target="_blank" class="text-decoration-none" style="color: var(--pustik-primary);">Trademap.org (ITC)</a>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end text-center">
                        <p class="mb-0" style="color: var(--pustik-text-dark);">
                            Built with Laravel + Puppeteer + Tailwind &copy; 2026 Jeremy Vidinov Binsar. All Rights Reserved.
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

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
    
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @stack('scripts')
</body>
</html>