@extends('layouts.dashboard')

@section('content')
<div class="container">
    <h1 class="h2">Job Dispatcher</h1>
    <p>Trigger background tasks from here.</p>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Trademap Scraper</h5>
            <p class="card-text">Dispatch the job to scrape the latest trade data from Trademap.org. This will run in the background.</p>
            <button id="scrape-button" class="btn btn-primary">Dispatch Scrape Job</button>
        </div>
    </div>
</div>

<script>
    document.getElementById('scrape-button').addEventListener('click', function() {
        fetch('{{ route('jobs.scrape') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('An error occurred while dispatching the job.');
        });
    });
</script>
@endsection
