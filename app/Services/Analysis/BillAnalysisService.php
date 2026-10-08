<?php

namespace App\Services\Analysis;

/**
 * ===========================================================
 * File:
 * app/Services/Analysis/BillAnalysisService.php
 *
 * Description:
 * Calculates WAPDA bill and solar generation analysis.
 * Difference units can now be positive or negative so the
 * frontend can identify surplus and shortage generation.
 * ===========================================================
 */

class BillAnalysisService
{
    /**
     * Analyze Bill
     */
    public function analyze(
        float $unitsConsumed,
        float $generatedUnits,
        float $billAmount,
        ?string $generationLossReason = null
    ): array {

        /*
        |------------------------------------------------------------------
        | Difference Units
        |------------------------------------------------------------------
        |
        | Positive:
        | Solar generated more units than consumed.
        |
        | Negative:
        | Solar generated fewer units than consumed.
        |
        */

        $differenceUnits =
            $generatedUnits -
            $unitsConsumed;


        /*
        |------------------------------------------------------------------
        | Response
        |------------------------------------------------------------------
        */

        return [

            "success" =>
                true,

            "message" =>
                "Bill analyzed successfully.",

            "analysis" => [

                "units_consumed" =>
                    round(
                        $unitsConsumed,
                        2
                    ),

                "generated_units" =>
                    round(
                        $generatedUnits,
                        2
                    ),

                "difference_units" =>
                    round(
                        $differenceUnits,
                        2
                    ),

                "bill_amount" =>
                    round(
                        $billAmount,
                        2
                    ),

                "generation_loss_reason" =>
                    $generationLossReason,

            ],

        ];

    }
}