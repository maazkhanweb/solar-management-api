@extends('reports.layout')

@section('content')

<style>

    .report-section-title {

        font-size: 14px;

        font-weight: bold;

        margin-top: 14px;

        margin-bottom: 6px;

        color: #164e63;

    }

    .summary-table {

        width: 100%;

        table-layout: fixed;

        border-collapse: collapse;

        margin-bottom: 12px;

    }

    .summary-table th {

        background: #0f766e;

        color: white;

        font-size: 7px;

        padding: 4px 3px;

        text-align: left;

        overflow-wrap: break-word;

    }

    .summary-table td {

        font-size: 7px;

        padding: 4px 3px;

        border: 1px solid #d1d5db;

        overflow-wrap: break-word;
        word-wrap: break-word;

    }

    /* Compact column widths for the ten-column bill table on A4 portrait. */
    .summary-table th:nth-child(1), .summary-table td:nth-child(1) { width: 4%; }
    .summary-table th:nth-child(2), .summary-table td:nth-child(2) { width: 19%; }
    .summary-table th:nth-child(3), .summary-table td:nth-child(3) { width: 15%; }
    .summary-table th:nth-child(4), .summary-table td:nth-child(4) { width: 12%; }
    .summary-table th:nth-child(5), .summary-table td:nth-child(5) { width: 7%; }
    .summary-table th:nth-child(6), .summary-table td:nth-child(6) { width: 7%; }
    .summary-table th:nth-child(7), .summary-table td:nth-child(7) { width: 9%; }
    .summary-table th:nth-child(8), .summary-table td:nth-child(8) { width: 10%; }
    .summary-table th:nth-child(9), .summary-table td:nth-child(9) { width: 7%; }
    .summary-table th:nth-child(10), .summary-table td:nth-child(10) { width: 10%; }

    .analysis-table {

        width: 100%;

        border-collapse: collapse;

        margin-bottom: 12px;

    }

    .analysis-table th {

        background: #dbeafe;

        color: #1e3a8a;

        font-size: 9px;

        padding: 6px;

        text-align: left;

    }

    .analysis-table td {

        font-size: 9px;

        padding: 6px;

        border: 1px solid #d1d5db;

    }

    .report-divider {

        border: 0;

        border-top: 1px solid #9ca3af;

        margin-top: 10px;

        margin-bottom: 15px;

    }

    /*
     * Keep one bill's details and solar analysis together on the same page.
     * This prevents a bill heading/table header from being stranded at the
     * bottom of one page while its data row starts on the next page.
     */
    .bill-report-record {
        page-break-inside: avoid;
        break-inside: avoid;
    }

</style>


@if($rows->count() > 0)

    @foreach($rows as $bill)

        <div class="bill-report-record">

        @php

            $analysisService =
                app(
                    \App\Services\Analysis\BillAnalysisService::class
                );

            $analysisResult =
                $analysisService->analyze(

                    (float) $bill->units_consumed,

                    (float) $bill->generated_units,

                    (float) $bill->bill_amount,

                    $bill->generation_loss_reason

                );

            $analysis =
                $analysisResult['analysis'];

        @endphp


        {{-- ==========================================================
             BILL DETAILS
        =========================================================== --}}

        <div class="report-section-title">

            WAPDA Bill Details

        </div>


        <table class="summary-table">

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Consumer Name
                    </th>

                    <th>
                        Reference Number
                    </th>

                    <th>
                        Area
                    </th>

                    <th>
                        Bill Month
                    </th>

                    <th>
                        Bill Year
                    </th>

                    <th>
                        WAPDA Units
                    </th>

                    <th>
                        Bill Amount
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Created At
                    </th>

                </tr>

            </thead>


            <tbody>

                <tr>

                    <td>
                        {{ $bill->id }}
                    </td>

                    <td>
                        {{ $bill->consumer_name }}
                    </td>

                    <td>
                        {{ $bill->reference_number }}
                    </td>

                    <td>
                        {{ $bill->area?->area_name ?? '-' }}
                    </td>

                    <td>
                        {{ $bill->bill_month }}
                    </td>

                    <td>
                        {{ $bill->bill_year }}
                    </td>

                    <td>
                        {{ number_format(
                            $analysis['units_consumed'],
                            2
                        ) }}
                    </td>

                    <td>
                        {{ number_format(
                            $analysis['bill_amount'],
                            2
                        ) }}
                    </td>

                    <td>
                        {{ $bill->status }}
                    </td>

                    <td>
                        {{ optional(
                            $bill->created_at
                        )->format('d M Y') }}
                    </td>

                </tr>

            </tbody>

        </table>


        {{-- ==========================================================
             SOLAR ANALYSIS
        =========================================================== --}}

        <div class="report-section-title">

            Solar Generation & Bill Analysis

        </div>


        <table class="analysis-table">

            <thead>

                <tr>

                    <th>
                        Solar Generated Units
                    </th>

                    <th>
                        Difference Units
                    </th>

                    <th>
                        Generation Loss Reason
                    </th>

                </tr>

            </thead>


            <tbody>

                <tr>

                    <td>
                        {{ number_format(
                            $analysis['generated_units'],
                            2
                        ) }}
                    </td>

                    <td>
                        {{ number_format(
                            $analysis['difference_units'],
                            2
                        ) }}
                    </td>

                    <td>
                        {{ $analysis['generation_loss_reason'] ?? '-' }}
                    </td>

                </tr>

            </tbody>

        </table>


        <hr class="report-divider">

        </div>

    @endforeach


@else

    <table class="summary-table">

        <tbody>

            <tr>

                <td
                    style="text-align:center;"
                >

                    No WAPDA Bills Found

                </td>

            </tr>

        </tbody>

    </table>

@endif

@endsection