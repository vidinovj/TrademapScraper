<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ScrapeTrademapDataJob;
use Illuminate\Http\JsonResponse;

class JobDispatchController extends Controller
{
    /**
     * Dispatch the Trademap data scraping job.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function scrape(): JsonResponse
    {
        try {
            ScrapeTrademapDataJob::dispatch();

            return response()->json(['message' => 'Scraping job dispatched successfully!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to dispatch scraping job.'], 500);
        }
    }
}