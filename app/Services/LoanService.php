<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
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
        //  
        return DB::transaction(function () use ($user, $amount, $currencyCode, $terms, $processedAt) {

            // Create Loan
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'terms' => $terms,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            // Create Scheduled Repayments
            $repaymentAmount = intdiv($amount, $terms);
            for ($i = 1; $i <= $terms; $i++) {
                ScheduledRepayment::create([
                    'loan_id' => $loan->id,
                    'amount' => $repaymentAmount,
                    'currency_code' => $loan->currency_code,
                    'due_date' => date('Y-m-d', strtotime("+$i month", strtotime($processedAt))),
                    'status' => ScheduledRepayment::STATUS_DUE,
                ]);
            }

            return $loan;
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
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        //
        return DB::transaction(function () use ($loan, $amount, $currencyCode, $receivedAt) {

            // Validate currency
            if ($currencyCode !== $loan->currency_code) {
                throw new \Exception('Invalid currency');
            }

            // Find earliest due/partial scheduled repayment
            $firstScheduled = $loan->scheduledRepayments()
                ->whereIn('status', [
                    ScheduledRepayment::STATUS_DUE,
                    ScheduledRepayment::STATUS_PARTIAL
                ])
                ->orderBy('due_date')
                ->first();

            // Log the received repayment
            $receivedRepayment = ReceivedRepayment::create([
                'loan_id'                 => $loan->id,
                'scheduled_repayment_id'  => $firstScheduled?->id,
                'amount'                  => $amount,
                'received_at'             => $receivedAt,
            ]);

            // Update scheduled repayments
            $remaining = $amount;

            $scheduledRepayments = $loan->scheduledRepayments()
                ->whereIn('status', [
                    ScheduledRepayment::STATUS_DUE,
                    ScheduledRepayment::STATUS_PARTIAL
                ])
                ->orderBy('due_date')
                ->get();

            foreach ($scheduledRepayments as $scheduled) {
                if ($remaining <= 0) {
                    break;
                }

                if ($remaining >= $scheduled->amount) {
                    // Full payment
                    $remaining -= $scheduled->amount;
                    $scheduled->status = ScheduledRepayment::STATUS_REPAID;
                    $scheduled->save();
                } else {
                    // Partial payment
                    $scheduled->status = ScheduledRepayment::STATUS_PARTIAL;
                    $scheduled->save();
                    $remaining = 0;
                }
            }

            // Update Loan outstanding amount
            $loan->outstanding_amount -= $amount;
            if ($loan->outstanding_amount <= 0) {
                $loan->outstanding_amount = 0;
                $loan->status = Loan::STATUS_REPAID;
            }
            $loan->save();

            return $receivedRepayment;
        });
    }
}
