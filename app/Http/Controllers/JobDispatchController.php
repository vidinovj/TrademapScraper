<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ScrapeTrademapDataJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\TbTrade;
use Illuminate\Support\Facades\DB;

class JobDispatchController extends Controller
{
    /**
     * Dispatch the Trademap data scraping job.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function scrape(Request $request): JsonResponse
    {
        try {
            $productCode = $request->input('productCode', 'TOTAL');
            $jobId = (string) Str::uuid();

            ScrapeTrademapDataJob::dispatch($productCode, $jobId);

            return response()->json([
                'message' => 'Scraping job dispatched successfully!',
                'job_id' => $jobId,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to dispatch scraping job.'], 500);
        }
    }

    /**
     * Get all distinct HS2 codes from the database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHs2Codes(): JsonResponse
    {
        try {
            $hs2Codes = TbTrade::select('kode_hs')
                                ->where(DB::raw("LENGTH(REPLACE(kode_hs, '.', ''))"), 2)
                                ->distinct()
                                ->orderBy('kode_hs')
                                ->pluck('kode_hs');

            return response()->json($hs2Codes);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve HS2 codes.'], 500);
        }
    }

    /**
     * Get the progress of a scraping job.
     *
     * @param string $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScrapeProgress(string $jobId): JsonResponse
    {
        $progress = Cache::get("scrape_progress_{$jobId}", [
            'status' => 'not_found',
            'message' => 'Job status not found. It may still be queued or has expired.',
        ]);

        return response()->json($progress);
    }
}