<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\User;
use App\Models\ScheduledRepayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        return DB::transaction(function () use ($user, $amount, $currencyCode, $terms, $processedAt) {
            $loan = Loan::create([
                'user_id'            => $user->id,
                'amount'             => $amount,
                'currency_code'      => $currencyCode,
                'terms'              => $terms,
                'processed_at'       => Carbon::parse($processedAt),
                'outstanding_amount' => $amount,
                'status'             => Loan::STATUS_DUE,
            ]);

            // Calculate installments - distribute remainder to last payment
            $baseInstallment = intval($amount / $terms);
            $remainder = $amount % $terms;

            for ($i = 1; $i <= $terms; $i++) {
                $installmentAmount = $baseInstallment;
                
                // Add remainder to last installment
                if ($i === $terms) {
                    $installmentAmount += $remainder;
                }

                ScheduledRepayment::create([
                    'loan_id'            => $loan->id,
                    'amount'             => $installmentAmount,
                    'outstanding_amount' => $installmentAmount,
                    'currency_code'      => $currencyCode,
                    'due_date'           => Carbon::parse($processedAt)->addMonths($i),
                    'status'             => ScheduledRepayment::STATUS_DUE,
                ]);
            }

            return $loan->fresh(['scheduledRepayments']);
        });
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return Loan
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): Loan
    {
        return DB::transaction(function () use ($loan, $amount, $currencyCode, $receivedAt) {
            
            // Create received repayment record
            ReceivedRepayment::create([
                'loan_id'       => $loan->id,
                'amount'        => $amount,
                'currency_code' => $currencyCode,
                'received_at'   => Carbon::parse($receivedAt),
            ]);

            // Process payments against scheduled repayments in order
            $remainingAmount = $amount;
            $scheduledRepayments = $loan->scheduledRepayments()
                                       ->where('status', '!=', ScheduledRepayment::STATUS_REPAID)
                                       ->orderBy('due_date')
                                       ->get();

            foreach ($scheduledRepayments as $scheduledRepayment) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $outstandingAmount = $scheduledRepayment->outstanding_amount;
                
                if ($remainingAmount >= $outstandingAmount) {
                    // Full payment of this scheduled repayment
                    $scheduledRepayment->outstanding_amount = 0;
                    $scheduledRepayment->status = ScheduledRepayment::STATUS_REPAID;
                    $remainingAmount -= $outstandingAmount;
                } else {
                    // Partial payment
                    $scheduledRepayment->outstanding_amount -= $remainingAmount;
                    $scheduledRepayment->status = ScheduledRepayment::STATUS_PARTIAL;
                    $remainingAmount = 0;
                }
                
                $scheduledRepayment->save();
            }

            // Update loan status and outstanding amount
            $loan->outstanding_amount -= $amount;
            
            if ($loan->outstanding_amount <= 0) {
                $loan->outstanding_amount = 0;
                $loan->status = Loan::STATUS_REPAID;
            }
            
            $loan->save();

            return $loan->fresh(['scheduledRepayments']);
        });
    }
}