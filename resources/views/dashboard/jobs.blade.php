@extends('layouts.dashboard')

@section('title', 'Job Dispatcher')

@section('content')
@include('components.trade-ticker')

<div class="container-fluid px-2" style="padding-top: 1rem;">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold mb-2 text-light">
                <i class="fas fa-cogs me-3"></i>
                Job Dispatcher
            </h2>
            <p class="text-light mb-0">Trigger and monitor background scraping tasks from here.</p>
        </div>
    </div>

    <!-- Job Control Cards -->
    <div class="row g-2">
        <!-- Granular Scrape -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-search-location me-2"></i>
                        Granular Scrape
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-light">Dispatch a job for a specific HS product code or a full scrape of all top-level products.</p>
                    <div class="mb-3">
                        <label for="productCode" class="form-label">HS Product Code (optional)</label>
                        <input type="text" class="form-control" id="productCode" placeholder="e.g., 27 or TOTAL">
                    </div>
                    <button id="dispatch-job-btn" class="btn btn-primary w-100">
                        <i class="fas fa-play-circle me-2"></i>Dispatch Scrape Job
                    </button>
                </div>
            </div>
        </div>
        <!-- Drill Down -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-project-diagram me-2"></i>
                        Drill-Down All HS2
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-light">Finds all 2-digit HS codes in the database and dispatches a separate scraping job for each to fetch HS4 data. This may take a long time.</p>
                    <button id="drill-down-btn" class="btn btn-outline-warning w-100">
                        <i class="fas fa-cogs me-2"></i>Dispatch All Drill-Down Jobs
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Section -->
    <div class="row mt-4" id="progress-section" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-spinner fa-spin me-2" id="progress-spinner"></i>
                        <span id="progress-title">Job Progress</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3" style="height: 8px;">
                        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-light mb-0" id="progress-message">Waiting for job to start...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dispatchBtn = document.getElementById('dispatch-job-btn');
    const drillDownBtn = document.getElementById('drill-down-btn');
    const productCodeInput = document.getElementById('productCode');
    
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('progress-bar');
    const progressMessage = document.getElementById('progress-message');
    const progressTitle = document.getElementById('progress-title');
    const progressSpinner = document.getElementById('progress-spinner');

    let progressInterval;

    // Dispatch single scrape job
    dispatchBtn.addEventListener('click', function() {
        const productCode = productCodeInput.value || 'TOTAL';
        dispatchBtn.disabled = true;
        dispatchBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Dispatching...';

        fetch('{{ route('dashboard.jobs.scrape') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ productCode: productCode })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server responded with status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.job_id) {
                showAlert(`Job dispatched for ${productCode}!`, 'success');
                startProgressTracking(data.job_id, `Job for ${productCode}`);
            } else {
                showAlert(data.message || 'An unknown error occurred (no job ID).', 'danger');
            }
        })
        .catch(error => {
            showAlert('An error occurred while dispatching the job: ' + error.message, 'danger');
            console.error('Error:', error);
        })
        .finally(() => {
            dispatchBtn.disabled = false;
            dispatchBtn.innerHTML = '<i class="fas fa-play-circle me-2"></i>Dispatch Scrape Job';
        });
    });

    // Dispatch drill-down jobs sequentially
    drillDownBtn.addEventListener('click', async function() {
        if (!confirm('Are you sure you want to dispatch a scraping job for every HS2 code? This may take a long time and consume significant resources.')) {
            return;
        }

        drillDownBtn.disabled = true;
        drillDownBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Fetching HS2 codes...';

        try {
            // 1. Get the list of codes from the backend
            const response = await fetch('{{ route('dashboard.jobs.get-hs2-codes') }}');
            if (!response.ok) {
                throw new Error('Failed to get HS2 codes from server.');
            }
            const codesToScrape = await response.json();

            if (!codesToScrape || codesToScrape.length === 0) {
                showAlert('No HS2 codes found to drill down.', 'warning');
                return;
            }

            showAlert(`Found ${codesToScrape.length} HS2 codes. Starting sequential dispatch...`, 'info');

            // 2. Loop through and dispatch jobs one by one
            for (const code of codesToScrape) {
                await dispatchAndTrack(code);
            }

            showAlert('All drill-down jobs completed!', 'success');

        } catch (error) {
            showAlert('An error occurred during the drill-down process: ' + error.message, 'danger');
            console.error('Error:', error);
        } finally {
            drillDownBtn.disabled = false;
            drillDownBtn.innerHTML = '<i class="fas fa-cogs me-2"></i>Dispatch All Drill-Down Jobs';
        }
    });

    /**
     * Helper function to dispatch a single job and wait for it to complete.
     * Returns a promise that resolves when the job is done or fails.
     */
    function dispatchAndTrack(productCode) {
        return new Promise((resolve, reject) => {
            fetch('{{ route('dashboard.jobs.scrape') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ productCode: productCode })
            })
            .then(response => response.json())
            .then(data => {
                if (data.job_id) {
                    const jobId = data.job_id;
                    const title = `Drill-Down: Job for ${productCode}`;

                    progressSection.style.display = 'block';
                    progressTitle.textContent = title;
                    updateProgressDisplay({ status: 'queued', message: `Job for ${productCode} is queued...` });

                    const interval = setInterval(() => {
                        fetch(`/dashboard/scrape-progress/${jobId}`)
                            .then(response => response.json())
                            .then(progress => {
                                updateProgressDisplay(progress);
                                if (progress.status === 'completed' || progress.status === 'failed') {
                                    clearInterval(interval);
                                    resolve(); // Job is done, resolve the promise
                                }
                            })
                            .catch(err => {
                                clearInterval(interval);
                                updateProgressDisplay({ status: 'failed', message: 'Could not fetch job progress.' });
                                reject(err); // Job failed, reject the promise
                            });
                    }, 3000);
                } else {
                    reject(new Error(data.message || 'Dispatch failed, no job ID.'));
                }
            })
            .catch(err => {
                reject(err);
            });
        });
    }

    function startProgressTracking(jobId, title) {
        if (progressInterval) {
            clearInterval(progressInterval);
        }

        progressSection.style.display = 'block';
        progressTitle.textContent = title;
        updateProgressDisplay({ status: 'queued', message: 'Job is queued...' });

        progressInterval = setInterval(() => {
            fetch(`/dashboard/scrape-progress/${jobId}`)
                .then(response => response.json())
                .then(progress => {
                    updateProgressDisplay(progress);
                    if (progress.status === 'completed' || progress.status === 'failed' || progress.status === 'not_found') {
                        clearInterval(progressInterval);
                        progressSpinner.classList.remove('fa-spin');
                    }
                })
                .catch(err => {
                    console.error("Progress fetch error:", err);
                    clearInterval(progressInterval);
                    updateProgressDisplay({ status: 'failed', message: 'Could not fetch job progress.' });
                    progressSpinner.classList.remove('fa-spin');
                });
        }, 3000);
    }

    function updateProgressDisplay(progress) {
        progressMessage.textContent = progress.message || '...';
        
        switch(progress.status) {
            case 'running':
                progressBar.style.width = '50%';
                progressBar.classList.add('progress-bar-animated');
                progressSpinner.classList.add('fa-spin');
                break;
            case 'completed':
                progressBar.style.width = '100%';
                progressBar.classList.remove('progress-bar-animated', 'bg-info');
                progressBar.classList.add('bg-success');
                showAlert('Scraping job completed successfully!', 'success');
                break;
            case 'failed':
                progressBar.style.width = '100%';
                progressBar.classList.remove('progress-bar-animated', 'bg-info');
                progressBar.classList.add('bg-danger');
                showAlert('Scraping job failed.', 'danger');
                break;
            case 'queued':
                progressBar.style.width = '5%';
                progressBar.classList.remove('bg-success', 'bg-danger'); // Reset classes
                progressBar.classList.add('progress-bar-animated', 'bg-info');
                break;
            default: // not_found
                progressBar.style.width = '100%';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-secondary');
                break;
        }
    }

    function showAlert(message, type = 'info') {
        // This function injects a Bootstrap-like alert into the top-right corner.
        const alertContainer = document.createElement('div');
        alertContainer.style.cssText = 'position: fixed; top: 1.5rem; right: 1.5rem; z-index: 1056;';
        
        const alertClasses = {
            info: 'bg-primary text-light',
            success: 'bg-success text-light',
            danger: 'bg-danger text-light',
            warning: 'bg-warning text-dark'
        };

        const alert = document.createElement('div');
        alert.className = `alert ${alertClasses[type] || alertClasses.info} alert-dismissible fade show`;
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        alertContainer.appendChild(alert);
        document.body.appendChild(alertContainer);

        // Auto-dismiss
        setTimeout(() => {
            if (alertContainer.parentNode) {
                alertContainer.remove();
            }
        }, 5000);
    }
});
</script>
@endpush