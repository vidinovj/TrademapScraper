@extends('layouts.dashboard')

@section('title', 'Import CSV - Trade Data Dashboard')

@section('content')
<div class="container mx-auto px-4" style="padding-top: 1.5rem;">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold mb-2 text-light">
                <i class="fas fa-upload text-success me-3"></i>
                Import Data Perdagangan
            </h2>
            <p class="mb-0 text-light">Upload file CSV untuk menambah data ke database</p>
        </div>
    </div>

    <!-- Current Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card blue">
                <h3>{{ number_format($stats['total_records']) }}</h3>
                <p><i class="fas fa-database me-1"></i> Total Records</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card green">
                <h3>{{ $stats['total_countries'] }}</h3>
                <p><i class="fas fa-globe me-1"></i> Countries</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card purple">
                <h3>{{ $stats['total_hs_codes'] }}</h3>
                <p><i class="fas fa-tags me-1"></i> HS Codes</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card yellow">
                <h3>
                    @if($stats['last_import'])
                        {{ $stats['last_import']->diffForHumans() }}
                    @else
                        Never
                    @endif
                </h3>
                <p><i class="fas fa-clock me-1"></i> Last Import</p>
            </div>
        </div>
    </div>

    <!-- Import Form -->
    <div class="row">
        <div class="col-md-8">
            <!-- Step 1: File Upload -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">
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
                                <i class="fas fa-cloud-upload-alt fa-4x text-light mb-3"></i>
                                <h5 class="text-light">Drag & Drop file CSV di sini</h5>
                                <p class="mb-3 text-light">atau klik untuk browse file</p>
                                <input type="file" id="csvFiles" name="csv_files[]" multiple accept=".csv,.txt" class="d-none">
                                <button type="button" class="btn btn-success" id="browseBtn">
                                    <i class="fas fa-folder-open me-2"></i>Pilih File
                                </button>
                            </div>
                        </div>

                        <!-- Selected Files -->
                        <div id="selectedFiles" class="mt-4" style="display: none;">
                            <h6 class="text-light">File Terpilih:</h6>
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
                            <div class="card" style="background-color: var(--pustik-bg-dark);">
                                <div class="card-header">
                                    <h6 class="mb-0 fw-bold">
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
                                            <small class="text-light">Larger batch = faster import, more memory usage</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Data Validation</label>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="validate_data" id="validateData" checked>
                                                <label class="form-check-label text-light" for="validateData">
                                                    Enable data validation
                                                </label>
                                            </div>
                                            <small class="text-light">Disable for faster import (less safe)</small>
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
                        <h5 class="mb-0 fw-bold">
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
                                <div class="h5" id="processingSpeed" style="color: var(--pustik-yellow);">0/sec</div>
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
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        Format CSV yang Diperlukan
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2 text-light">File CSV harus memiliki kolom berikut:</p>
                    <code class="d-block p-2 rounded small" style="background-color: var(--pustik-bg-dark); border: 1px solid var(--pustik-border);">
                        negara,kode_hs,label,tahun,jumlah,satuan,sumber_data
                    </code>
                    
                    <p class="small mt-3 mb-2 text-light"><strong>Contoh:</strong></p>
                    <div class="small p-2 rounded text-light" style="background-color: var(--pustik-bg-dark); border: 1px solid var(--pustik-border);">
                        INDONESIA,0301.11.92,IKAN MAS KOKI,2024,1500,-,Trademap
                    </div>
                    
                    <div class="mt-3">
                        <div class="small text-success">
                            <i class="fas fa-check me-1"></i> Multiple files supported
                        </div>
                        <div class="small text-success">
                            <i class="fas fa-check me-1"></i> Large files handled automatically
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
                    <h6 class="mb-0 fw-bold">
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
@endsection

@push('styles')
<style>
    .upload-zone {
        border: 2px dashed var(--pustik-border);
        border-radius: 0.5rem;
        background: var(--pustik-bg-dark);
        transition: all 0.3s ease;
        cursor: pointer;
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .upload-zone:hover, .upload-zone.dragover {
        border-color: var(--pustik-primary);
        background: #1c2a3e;
    }
    
    .file-item {
        background: var(--pustik-bg-dark);
        border-radius: 0.375rem;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--pustik-border);
    }
    
    .progress-section,
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
@endpush

@push('scripts')
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
    const browseBtn = document.getElementById('browseBtn');
    const advancedToggle = document.getElementById('advancedToggle');
    const advancedOptions = document.getElementById('advancedOptions');

    // Event listeners
    csvFilesInput.addEventListener('change', handleFileSelection);
    uploadZone.addEventListener('click', () => {
        csvFilesInput.value = null;
        csvFilesInput.click();
    });
    browseBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        csvFilesInput.value = null;
        csvFilesInput.click();
    });
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
        
        const files = Array.from(e.dataTransfer.files).filter(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            return ['csv', 'txt'].includes(extension);
        });
        
        if (files.length !== e.dataTransfer.files.length) {
            showAlert(`${e.dataTransfer.files.length - files.length} file(s) were filtered out. Only CSV and TXT files are allowed.`, 'warning');
        }
        
        addFiles(files);
    }

    function addFiles(files) {
        const validFiles = files.filter(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            return ['csv', 'txt'].includes(extension);
        });
        
        if (validFiles.length !== files.length) {
            showAlert(`${files.length - validFiles.length} file(s) were filtered out. Only CSV and TXT files are allowed.`, 'warning');
        }
        
        selectedFiles = [...selectedFiles, ...validFiles];
        updateFilesList();
        
        if (selectedFiles.length > 0) {
            selectedFilesDiv.style.display = 'block';
            selectedFilesDiv.classList.add('fade-in');
            validateBtn.disabled = false;
        }
    }

    function updateFilesList() {
        filesListDiv.innerHTML = selectedFiles.map((file, index) => {
            const extension = file.name.split('.').pop().toLowerCase();
            const iconClass = extension === 'csv' ? 'fa-file-csv text-success' : 'fa-file-alt text-info';
            
            return `
                <div class="file-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <i class="fas ${iconClass} fa-2x me-3"></i>
                        <div>
                            <div class="fw-bold text-light">${file.name}</div>
                            <small class="text-light">${formatFileSize(file.size)} • ${extension.toUpperCase()}</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }).join('');
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
        if (selectedFiles.length === 0) {
            showAlert('Please select at least one file to validate.', 'warning');
            return;
        }

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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const textResponse = await response.text();
                console.error('Server returned non-JSON response:', textResponse);
                throw new Error(`Server returned ${response.status} error. Check browser console for details.`);
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                displayValidationResults(result);
                importBtn.disabled = false;
            } else {
                showAlert('Validation failed: ' + (result.message || 'Unknown error'), 'danger');
            }

        } catch (error) {
            console.error('Validation error:', error);
            showAlert('Validation error: ' + error.message, 'danger');
        } finally {
            validateBtn.disabled = false;
            validateBtn.innerHTML = '<i class="fas fa-check me-1"></i> Validate';
        }
    }

    function displayValidationResults(result) {
        const validationDiv = document.getElementById('validationResults');
        const contentDiv = document.getElementById('validationContent');
        
        let fileDetails = '';
        if (result.files && result.files.length > 0) {
            fileDetails = result.files.map(file => {
                const statusClass = file.valid ? 'text-success' : 'text-danger';
                const statusIcon = file.valid ? 'fa-check-circle' : 'fa-exclamation-circle';
                
                return `
                    <div class="p-2 mb-2 rounded" style="background-color: var(--pustik-bg-dark); border: 1px solid var(--pustik-border);">
                        <div class="d-flex align-items-center">
                            <i class="fas ${statusIcon} ${statusClass} me-2"></i>
                            <strong class="text-light">${file.filename}</strong>
                            <span class="badge bg-secondary ms-auto">${file.size_formatted}</span>
                        </div>
                        ${file.errors.length > 0 ? `
                            <div class="text-danger small mt-1 ps-3">
                                <strong>Errors:</strong> ${file.errors.join(', ')}
                            </div>
                        ` : ''}
                        ${file.warnings.length > 0 ? `
                            <div class="text-warning small mt-1 ps-3">
                                <strong>Warnings:</strong> ${file.warnings.join(', ')}
                            </div>
                        ` : ''}
                        <div class="text-light small mt-1 ps-3">
                            Estimated ${file.estimated_records.toLocaleString()} records
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        contentDiv.innerHTML = `
            <div class="row text-center mb-3">
                <div class="col-4">
                    <div class="h5 text-primary">${result.total_files}</div>
                    <small class="text-light">Files</small>
                </div>
                <div class="col-4">
                    <div class="h5 text-info">${result.total_estimated_records.toLocaleString()}</div>
                    <small class="text-light">Est. Records</small>
                </div>
                <div class="col-4">
                    <div class="h5 text-light">${result.estimated_time}</div>
                    <small class="text-light">Est. Time</small>
                </div>
            </div>
            ${fileDetails ? `
                <div class="mb-3">
                    <h6 class="text-light">File Details:</h6>
                    ${fileDetails}
                </div>
            ` : ''}
        `;
        
        validationDiv.style.display = 'block';
        validationDiv.classList.add('fade-in');
    }

    async function startImport() {
        if (selectedFiles.length === 0) {
            showAlert('Please select files to import.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        const chunkSize = document.querySelector('select[name="chunk_size"]')?.value || '1000';
        const validateData = document.querySelector('input[name="validate_data"]')?.checked ? '1' : '0';
        
        formData.append('chunk_size', chunkSize);
        formData.append('validate_data', validateData);
        
        selectedFiles.forEach(file => {
            formData.append('csv_files[]', file);
        });

        importBtn.disabled = true;
        importBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Starting...';

        try {
            const response = await fetch('{{ route("dashboard.import-csv") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                importJobId = result.job_id;
                showProgress();
                
                if (result.processing_mode === 'background') {
                    startProgressTracking();
                    showAlert('Large file import started in background.', 'info');
                } else {
                    const imported = result.result?.records_imported || 0;
                    showAlert(`Import completed! ${imported.toLocaleString()} records imported.`, 'success');
                    setTimeout(() => window.location.href = '{{ route("dashboard.trade-data") }}', 2000);
                }
            } else {
                showAlert('Import failed: ' + result.message, 'danger');
            }

        } catch (error) {
            console.error('Import error:', error);
            showAlert('Import error: ' + error.message, 'danger');
        } finally {
            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Start Import';
        }
    }

    function showProgress() {
        const progressSection = document.getElementById('progressSection');
        if (progressSection) {
            progressSection.style.display = 'block';
            progressSection.classList.add('fade-in');
        }
    }

    function startProgressTracking() {
        if (!importJobId) return;

        progressInterval = setInterval(async () => {
            try {
                const response = await fetch(`/dashboard/import-progress/${importJobId}`);
                const progress = await response.json();
                
                updateProgressDisplay(progress);
                
                if (progress.status === 'completed' || progress.status === 'failed') {
                    clearInterval(progressInterval);
                    
                    if (progress.status === 'completed') {
                        const imported = progress.records_imported || 0;
                        const errors = progress.errors || 0;
                        showAlert(`Import completed! ${imported.toLocaleString()} records imported${errors > 0 ? ` (${errors} errors)` : ''}.`, 'success');
                        setTimeout(() => window.location.href = '{{ route("dashboard.trade-data") }}', 3000);
                    } else {
                        showAlert('Import failed: ' + (progress.message || 'Unknown error'), 'danger');
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
        
        if (progressBar) {
            const percentage = Math.min(100, Math.max(0, progress.progress || 0));
            progressBar.style.width = percentage + '%';
        }
        if (recordsImported) recordsImported.textContent = (progress.records_imported || 0).toLocaleString();
        if (currentFile) currentFile.textContent = `${progress.current_file || 0}/${progress.total_files || 0}`;
        if (processingSpeed) processingSpeed.textContent = progress.processing_speed || '0/sec';
        if (errorCount) errorCount.textContent = progress.errors || 0;
        if (statusMessage) statusMessage.textContent = progress.message || 'Processing...';
    }

    function formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }

    function showAlert(message, type = 'info') {
        const alertContainer = document.createElement('div');
        alertContainer.style.position = 'fixed';
        alertContainer.style.top = '20px';
        alertContainer.style.right = '20px';
        alertContainer.style.zIndex = '9999';

        const alertDiv = document.createElement('div');
        const alertClass = type === 'danger' ? 'alert-danger' : (type === 'warning' ? 'alert-warning' : 'alert-success');
        alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alertDiv);
        document.body.appendChild(alertContainer);
        
        setTimeout(() => {
            alertContainer.remove();
        }, 5000);
    }
</script>
@endpush
