<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Import CSV - Trade Data Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #198754;
            background: #d4f8d4;
        }
        
        .file-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: between;
            align-items: center;
            border: 1px solid #dee2e6;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
        }
        
        .progress-section {
            display: none;
        }
        
        .advanced-options {
            display: none;
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold mb-2">
                            <i class="fas fa-upload text-success me-3"></i>
                            Import Data Perdagangan
                        </h2>
                        <p class="text-muted mb-0">Upload file CSV untuk menambah data ke database</p>
                    </div>
                    <a href="{{ route('dashboard.trade-data') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Current Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="h4 mb-1">{{ number_format($stats['total_records']) }}</div>
                    <small class="opacity-75">Total Records</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="h4 mb-1">{{ $stats['total_countries'] }}</div>
                    <small class="opacity-75">Countries</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="h4 mb-1">{{ $stats['total_hs_codes'] }}</div>
                    <small class="opacity-75">HS Codes</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="h4 mb-1">
                        @if($stats['last_import'])
                            {{ $stats['last_import']->diffForHumans() }}
                        @else
                            Never
                        @endif
                    </div>
                    <small class="opacity-75">Last Import</small>
                </div>
            </div>
        </div>

        <!-- Import Form -->
        <div class="row">
            <div class="col-md-8">
                <!-- Step 1: File Upload -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-csv me-2 text-success"></i>
                            1. Pilih File CSV
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="importForm" enctype="multipart/form-data">
                            @csrf
                            
                            <!-- Upload Zone -->
                            <div class="upload-zone" id="uploadZone">
                                <div class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                                    <h5>Drag & Drop file CSV di sini</h5>
                                    <p class="text-muted mb-3">atau klik untuk browse file</p>
                                    <input type="file" id="csvFiles" name="csv_files[]" multiple accept=".csv" class="d-none">
                                    <button type="button" class="btn btn-success" onclick="document.getElementById('csvFiles').click()">
                                        <i class="fas fa-folder-open me-2"></i>Pilih File
                                    </button>
                                </div>
                            </div>

                            <!-- Selected Files -->
                            <div id="selectedFiles" class="mt-4" style="display: none;">
                                <h6>File Terpilih:</h6>
                                <div id="filesList"></div>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-info" id="validateBtn" disabled>
                                        <i class="fas fa-check me-1"></i> Validate
                                    </button>
                                    <button type="button" class="btn btn-success" id="importBtn" disabled>
                                        <i class="fas fa-upload me-1"></i> Start Import
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="advancedToggle">
                                        <i class="fas fa-cog me-1"></i> Advanced Options
                                    </button>
                                </div>
                            </div>

                            <!-- Advanced Options (Hidden Initially) -->
                            <div class="advanced-options mt-4" id="advancedOptions">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-cog me-2"></i>
                                            Advanced Settings
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Batch Size</label>
                                                <select name="chunk_size" class="form-select">
                                                    <option value="500">500 (Conservative)</option>
                                                    <option value="1000" selected>1,000 (Recommended)</option>
                                                    <option value="2000">2,000 (Fast)</option>
                                                    <option value="5000">5,000 (Very Fast)</option>
                                                </select>
                                                <small class="text-muted">Larger batch = faster import, more memory usage</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Data Validation</label>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="validate_data" id="validateData" checked>
                                                    <label class="form-check-label" for="validateData">
                                                        Enable data validation
                                                    </label>
                                                </div>
                                                <small class="text-muted">Disable for faster import (less safe)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Step 2: Progress (Hidden Initially) -->
                <div class="progress-section" id="progressSection">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Import Progress
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                     role="progressbar" style="width: 0%" id="progressBar">
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="h5 text-success" id="recordsImported">0</div>
                                    <small class="text-muted">Records Imported</small>
                                </div>
                                <div class="col-3">
                                    <div class="h5 text-info" id="currentFile">0/0</div>
                                    <small class="text-muted">Files Processed</small>
                                </div>
                                <div class="col-3">
                                    <div class="h5 text-warning" id="processingSpeed">0/sec</div>
                                    <small class="text-muted">Records/Second</small>
                                </div>
                                <div class="col-3">
                                    <div class="h5 text-danger" id="errorCount">0</div>
                                    <small class="text-muted">Errors</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="text-muted" id="statusMessage">Starting import...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="col-md-4">
                <!-- Format Requirements -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            Format CSV yang Diperlukan
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">File CSV harus memiliki kolom berikut:</p>
                        <code class="d-block p-2 bg-light rounded small">
negara,kode_hs,label,tahun,jumlah,satuan,sumber_data
                        </code>
                        
                        <p class="small mt-3 mb-2"><strong>Contoh:</strong></p>
                        <div class="small bg-light p-2 rounded">
                            INDONESIA,0301.11.92,IKAN MAS KOKI,2024,1500,-,Trademap
                        </div>
                        
                        <div class="mt-3">
                            <div class="small text-success">
                                <i class="fas fa-check me-1"></i> Multiple files supported
                            </div>
                            <div class="small text-success">
                                <i class="fas fa-check me-1"></i> Large files (>50GB) handled automatically
                            </div>
                            <div class="small text-success">
                                <i class="fas fa-check me-1"></i> Background processing for big imports
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validation Results (Hidden Initially) -->
                <div class="card" id="validationResults" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-clipboard-check me-2 text-success"></i>
                            Validation Results
                        </h6>
                    </div>
                    <div class="card-body" id="validationContent">
                        <!-- Validation results will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedFiles = [];
        let importJobId = null;
        let progressInterval = null;

        // DOM elements
        const csvFilesInput = document.getElementById('csvFiles');
        const uploadZone = document.getElementById('uploadZone');
        const selectedFilesDiv = document.getElementById('selectedFiles');
        const filesListDiv = document.getElementById('filesList');
        const validateBtn = document.getElementById('validateBtn');
        const importBtn = document.getElementById('importBtn');
        const advancedToggle = document.getElementById('advancedToggle');
        const advancedOptions = document.getElementById('advancedOptions');

        // Event listeners
        csvFilesInput.addEventListener('change', handleFileSelection);
        uploadZone.addEventListener('click', () => csvFilesInput.click());
        uploadZone.addEventListener('dragover', handleDragOver);
        uploadZone.addEventListener('drop', handleDrop);
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
        
        validateBtn.addEventListener('click', validateFiles);
        importBtn.addEventListener('click', startImport);
        advancedToggle.addEventListener('click', toggleAdvancedOptions);

        function handleFileSelection(e) {
            const files = Array.from(e.target.files);
            addFiles(files);
        }

        function handleDragOver(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files).filter(f => f.name.endsWith('.csv'));
            addFiles(files);
        }

        function addFiles(files) {
            selectedFiles = [...selectedFiles, ...files];
            updateFilesList();
            
            if (selectedFiles.length > 0) {
                selectedFilesDiv.style.display = 'block';
                selectedFilesDiv.classList.add('fade-in');
                validateBtn.disabled = false;
            }
        }

        function updateFilesList() {
            filesListDiv.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <i class="fas fa-file-csv text-success me-3"></i>
                        <div>
                            <div class="fw-bold">${file.name}</div>
                            <small class="text-muted">${formatFileSize(file.size)}</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');
        }

        window.removeFile = function(index) {
            selectedFiles.splice(index, 1);
            updateFilesList();
            
            if (selectedFiles.length === 0) {
                selectedFilesDiv.style.display = 'none';
                validateBtn.disabled = true;
                importBtn.disabled = true;
                document.getElementById('validationResults').style.display = 'none';
            }
        }

        function toggleAdvancedOptions() {
            const isVisible = advancedOptions.style.display !== 'none';
            advancedOptions.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) {
                advancedOptions.classList.add('fade-in');
            }
            advancedToggle.innerHTML = isVisible ? 
                '<i class="fas fa-cog me-1"></i> Advanced Options' :
                '<i class="fas fa-times me-1"></i> Hide Advanced';
        }

        async function validateFiles() {
            validateBtn.disabled = true;
            validateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Validating...';

            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('csv_files[]', file);
            });

            try {
                const response = await fetch('{{ route("dashboard.validate-csv") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const result = await response.json();
                
                if (result.success) {
                    displayValidationResults(result);
                    importBtn.disabled = false;
                } else {
                    showAlert('Validation failed: ' + result.message, 'danger');
                }

            } catch (error) {
                showAlert('Validation error: ' + error.message, 'danger');
            } finally {
                validateBtn.disabled = false;
                validateBtn.innerHTML = '<i class="fas fa-check me-1"></i> Validate';
            }
        }

        function displayValidationResults(result) {
            const validationDiv = document.getElementById('validationResults');
            const contentDiv = document.getElementById('validationContent');
            
            contentDiv.innerHTML = `
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="h5 text-success">${result.total_files}</div>
                        <small>Files</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-info">${result.total_estimated_records.toLocaleString()}</div>
                        <small>Est. Records</small>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-muted">Estimated Time: <strong>${result.estimated_time}</strong></div>
                </div>
            `;
            
            validationDiv.style.display = 'block';
            validationDiv.classList.add('fade-in');
        }

        async function startImport() {
            const formData = new FormData(document.getElementById('importForm'));
            selectedFiles.forEach(file => {
                formData.append('csv_files[]', file);
            });

            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Starting...';

            try {
                const response = await fetch('{{ route("dashboard.import-csv") }}', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    importJobId = result.job_id;
                    showProgress();
                    
                    if (result.processing_mode === 'background') {
                        startProgressTracking();
                    } else {
                        showAlert('Import completed successfully!', 'success');
                        setTimeout(() => window.location.href = '{{ route("dashboard.trade-data") }}', 2000);
                    }
                } else {
                    showAlert('Import failed: ' + result.message, 'danger');
                    importBtn.disabled = false;
                    importBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Start Import';
                }

            } catch (error) {
                showAlert('Import error: ' + error.message, 'danger');
                importBtn.disabled = false;
                importBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Start Import';
            }
        }

        function showProgress() {
            document.getElementById('progressSection').style.display = 'block';
            document.getElementById('progressSection').classList.add('fade-in');
        }

        function startProgressTracking() {
            progressInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/dashboard/import-progress/${importJobId}`);
                    const progress = await response.json();
                    
                    updateProgressDisplay(progress);
                    
                    if (progress.status === 'completed' || progress.status === 'failed') {
                        clearInterval(progressInterval);
                        
                        if (progress.status === 'completed') {
                            showAlert(`Import completed! ${progress.records_imported} records imported.`, 'success');
                            setTimeout(() => window.location.href = '{{ route("dashboard.trade-data") }}', 3000);
                        } else {
                            showAlert('Import failed: ' + progress.message, 'danger');
                        }
                    }
                    
                } catch (error) {
                    console.error('Progress tracking error:', error);
                }
            }, 2000);
        }

        function updateProgressDisplay(progress) {
            const progressBar = document.getElementById('progressBar');
            const recordsImported = document.getElementById('recordsImported');
            const currentFile = document.getElementById('currentFile');
            const processingSpeed = document.getElementById('processingSpeed');
            const errorCount = document.getElementById('errorCount');
            const statusMessage = document.getElementById('statusMessage');
            
            progressBar.style.width = (progress.progress || 0) + '%';
            recordsImported.textContent = (progress.records_imported || 0).toLocaleString();
            currentFile.textContent = `${progress.current_file || 0}/${progress.total_files || 0}`;
            processingSpeed.textContent = progress.processing_speed || '0/sec';
            errorCount.textContent = progress.errors || 0;
            statusMessage.textContent = progress.message || 'Processing...';
        }

        function formatFileSize(bytes) {
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 Bytes';
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
</body>
</html>