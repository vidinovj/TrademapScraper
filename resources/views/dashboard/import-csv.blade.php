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
                                    <input type="file" id="csvFiles" name="csv_files[]" multiple accept=".csv,.txt" class="d-none">
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
            
            // FIXED: More permissive file filtering
            const files = Array.from(e.dataTransfer.files).filter(file => {
                const extension = file.name.split('.').pop().toLowerCase();
                return ['csv', 'txt'].includes(extension);
            });
            
            // Show warning if some files were filtered out
            const totalFiles = e.dataTransfer.files.length;
            if (files.length !== totalFiles) {
                showAlert(`${totalFiles - files.length} file(s) were filtered out. Only CSV and TXT files are allowed.`, 'warning');
            }
            
            addFiles(files);
        }

        function addFiles(files) {
            // FIXED: Validate file extensions before adding
            const validFiles = files.filter(file => {
                const extension = file.name.split('.').pop().toLowerCase();
                return ['csv', 'txt'].includes(extension);
            });
            
            // Show warning if some files were filtered out
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
                // FIXED: Show different icons for different file types
                const extension = file.name.split('.').pop().toLowerCase();
                const iconClass = extension === 'csv' ? 'fa-file-csv text-success' : 'fa-file-alt text-info';
                
                return `
                    <div class="file-item">
                        <div class="d-flex align-items-center flex-grow-1">
                            <i class="fas ${iconClass} me-3"></i>
                            <div>
                                <div class="fw-bold">${file.name}</div>
                                <small class="text-muted">${formatFileSize(file.size)} • ${extension.toUpperCase()}</small>
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

        // FIXED: Validation function with proper FormData
        async function validateFiles() {
            if (selectedFiles.length === 0) {
                showAlert('Please select at least one file to validate.', 'warning');
                return;
            }

            validateBtn.disabled = true;
            validateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Validating...';

            // Create clean FormData - no duplication
            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('csv_files[]', file);
            });

            try {
                const response = await fetch('/dashboard/validate-csv', {
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
            
            // ENHANCED: Show more detailed validation results
            let fileDetails = '';
            if (result.files && result.files.length > 0) {
                fileDetails = result.files.map(file => {
                    const statusClass = file.valid ? 'text-success' : 'text-danger';
                    const statusIcon = file.valid ? 'fa-check-circle' : 'fa-exclamation-circle';
                    
                    return `
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fas ${statusIcon} ${statusClass} me-2"></i>
                                <strong>${file.filename}</strong>
                                <span class="badge bg-light text-dark ms-auto">${file.size_formatted}</span>
                            </div>
                            ${file.errors.length > 0 ? `
                                <div class="text-danger small mt-1">
                                    <strong>Errors:</strong> ${file.errors.join(', ')}
                                </div>
                            ` : ''}
                            ${file.warnings.length > 0 ? `
                                <div class="text-warning small mt-1">
                                    <strong>Warnings:</strong> ${file.warnings.join(', ')}
                                </div>
                            ` : ''}
                            <div class="text-muted small mt-1">
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
                        <small>Files</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-info">${result.total_estimated_records.toLocaleString()}</div>
                        <small>Est. Records</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-muted">${result.estimated_time}</div>
                        <small>Est. Time</small>
                    </div>
                </div>
                ${fileDetails ? `
                    <div class="mb-3">
                        <h6>File Details:</h6>
                        ${fileDetails}
                    </div>
                ` : ''}
            `;
            
            validationDiv.style.display = 'block';
            validationDiv.classList.add('fade-in');
        }

        // Add this function to debug what's being sent
        function debugFormData(formData) {
            console.log('=== FORM DATA DEBUG ===');
            
            // Log all form entries
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(`${key}:`, {
                        name: value.name,
                        size: value.size,
                        type: value.type,
                        lastModified: value.lastModified
                    });
                } else {
                    console.log(`${key}:`, value);
                }
            }
            
            // Log selected files array
            console.log('selectedFiles array:', selectedFiles.map(file => ({
                name: file.name,
                size: file.size,
                type: file.type
            })));
            
            // Check form element
            const form = document.getElementById('importForm');
            console.log('Form element:', form);
            console.log('Form data from FormData(form):', [...new FormData(form).entries()]);
        }

// Update your startImport to use this debug function
// Add this line after creating formData:
// debugFormData(formData);

        // FIXED: Proper FormData construction
        async function startImport() {
            if (selectedFiles.length === 0) {
                showAlert('Please select files to import.', 'warning');
                return;
            }

            // Option 1: Manual FormData (Recommended)
            const formData = new FormData();
            
            // Add CSRF token
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            
            // Add form fields
            const chunkSize = document.querySelector('select[name="chunk_size"]')?.value || '1000';
            const validateData = document.querySelector('input[name="validate_data"]')?.checked ? '1' : '0';
            
            formData.append('chunk_size', chunkSize);
            formData.append('validate_data', validateData);
            
            // Add files manually (clean approach)
            selectedFiles.forEach(file => {
                formData.append('csv_files[]', file);
            });

            // Debug what we're sending
            console.log('=== SENDING FORM DATA ===');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(`${key}:`, {name: value.name, size: value.size, type: value.type});
                } else {
                    console.log(`${key}:`, value);
                }
            }

            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Starting...';

            try {
                const response = await fetch('/dashboard/import-csv', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                const responseText = await response.text();
                console.log('Response:', responseText);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = JSON.parse(responseText);
                
                if (result.success) {
                    if (result.processing_mode === 'debug') {
                        showAlert('✅ Debug mode: Files validated successfully! Ready for real import.', 'info');
                    } else {
                        importJobId = result.job_id;
                        showProgress();
                        
                        if (result.processing_mode === 'background') {
                            startProgressTracking();
                            showAlert('Large file import started in background.', 'info');
                        } else {
                            const imported = result.result?.records_imported || 0;
                            showAlert(`Import completed! ${imported.toLocaleString()} records imported.`, 'success');
                            setTimeout(() => window.location.href = '/dashboard', 2000);
                        }
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
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    
                    // FIXED: Check content type for progress tracking too
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        console.warn('Progress tracking returned non-JSON response');
                        return; // Don't throw error for progress tracking
                    }

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
                            importBtn.disabled = false;
                            importBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Start Import';
                        }
                    }
                    
                } catch (error) {
                    console.error('Progress tracking error:', error);
                    // Don't show alerts for progress tracking errors to avoid spam
                }
            }, 2000);
        }

        function updateProgressDisplay(progress) {
            // Safely update progress elements
            const progressBar = document.getElementById('progressBar');
            const recordsImported = document.getElementById('recordsImported');
            const currentFile = document.getElementById('currentFile');
            const processingSpeed = document.getElementById('processingSpeed');
            const errorCount = document.getElementById('errorCount');
            const statusMessage = document.getElementById('statusMessage');
            
            if (progressBar) {
                const percentage = Math.min(100, Math.max(0, progress.progress || 0));
                progressBar.style.width = percentage + '%';
                progressBar.setAttribute('aria-valuenow', percentage);
            }
            
            if (recordsImported) {
                recordsImported.textContent = (progress.records_imported || 0).toLocaleString();
            }
            
            if (currentFile) {
                currentFile.textContent = `${progress.current_file || 0}/${progress.total_files || 0}`;
            }
            
            if (processingSpeed) {
                processingSpeed.textContent = progress.processing_speed || '0/sec';
            }
            
            if (errorCount) {
                errorCount.textContent = progress.errors || 0;
                errorCount.className = (progress.errors || 0) > 0 ? 'text-warning' : 'text-muted';
            }
            
            if (statusMessage) {
                statusMessage.textContent = progress.message || 'Processing...';
            }
        }

        function formatFileSize(bytes) {
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 Bytes';
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        // ENHANCED: Better alert system with different types
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
            
            // Different icons for different alert types
            const icons = {
                'success': 'fa-check-circle',
                'danger': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            };
            
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas ${icons[type] || icons.info} me-2"></i>
                    <div class="flex-grow-1">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds (except for errors, which stay longer)
            const timeout = type === 'danger' ? 10000 : 5000;
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, timeout);
        }
    </script>
</body>
</html>