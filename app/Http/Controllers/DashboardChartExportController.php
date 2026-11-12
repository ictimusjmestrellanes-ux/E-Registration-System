<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardChartExportController extends Controller
{
    private const CHARTS = [
        'clientTrendChart' => 'Total Registered Clients',
        'transactionTrendChart' => 'Transaction Trend',
        'transactionDateChart' => 'Transactions by Transaction Date',
        'caravanTrendChart' => 'CARAVAN Transactions',
        'clientCategoryChart' => 'Client Category Distribution',
        'addressDistributionChart' => 'Address Distribution',
        'serviceCategoryChart' => 'Service Categories Distribution',
    ];

    public function store(Request $request, ActivityLogger $activityLogger): JsonResponse
    {
        $data = $request->validate([
            'chart_id' => ['required', 'string', Rule::in(array_keys(self::CHARTS))],
            'format' => ['required', 'string', Rule::in(['xlsx', 'csv', 'preview'])],
        ]);

        $chartTitle = self::CHARTS[$data['chart_id']];
        $format = strtoupper($data['format']);
        $formatLabel = $format === 'PREVIEW' ? 'chart preview (PNG/PDF)' : $format;

        $activityLogger->record(
            'dashboard_chart_exported',
            "Exported the {$chartTitle} dashboard chart as {$formatLabel}.",
            [
                'chart_id' => $data['chart_id'],
                'chart_title' => $chartTitle,
                'format' => $data['format'],
            ],
        );

        return response()->json(['success' => true]);
    }
}
