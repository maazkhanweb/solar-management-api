@extends('reports.layout')

@section('content')

<style>

    .report-section-title {

        font-size: 16px;

        font-weight: bold;

        margin-top: 18px;

        margin-bottom: 8px;

        color: #164e63;

    }

    .summary-table {

        width: 100%;

        border-collapse: collapse;

        margin-bottom: 15px;

    }

    .summary-table th {

        background: #0f766e;

        color: white;

        font-size: 9px;

        padding: 6px;

        text-align: left;

    }

    .summary-table td {

        font-size: 9px;

        padding: 6px;

        border: 1px solid #d1d5db;

    }

    .analysis-table {

        width: 100%;

        border-collapse: collapse;

        margin-bottom: 18px;

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

</style>


@if($rows->count() > 0)

    @foreach($rows as $bill)

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
                        Solar Coverage
                    </th>

                    

                    <th>
                        Estimated Saving
                    </th>

                    <th>
                        Efficiency
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
                        {{ number_format(
                            $analysis['unit_rate'],
                            2
                        ) }}
                    </td>

                    <td>
                        {{ number_format(
                            $analysis['solar_coverage'],
                            2
                        ) }}%
                    </td>

                    <td>
                        {{ number_format(
                            $analysis['wapda_dependency'],
                            2
                        ) }}%
                    </td>

                    <td>
                        {{ number_format(
                            $analysis['estimated_saving'],
                            2
                        ) }}
                    </td>

                    <td>
                        {{ $analysis['efficiency'] }}
                    </td>

                    <td>
                        {{ $analysis['generation_loss_reason'] ?? '-' }}
                    </td>

                </tr>

            </tbody>

        </table>


        <hr class="report-divider">

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