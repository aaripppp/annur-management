<?php

namespace App\Services;

use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DaycareChildDeletionService
{
    public function delete(int $childId): int
    {
        $result = DB::transaction(function () use ($childId): array {
            $child = DaycareChild::query()->lockForUpdate()->findOrFail($childId);
            $payments = $child->payments()->lockForUpdate()->get(['id', 'proof_path']);
            $paymentIds = $payments->modelKeys();

            if ($paymentIds !== []) {
                DaycarePaymentDetail::query()->whereIn('daycare_payment_id', $paymentIds)->delete();
                DaycarePayment::query()->whereKey($paymentIds)->delete();
            }

            $child->delete();

            return [
                'count' => count($paymentIds),
                'proof_paths' => $payments->pluck('proof_path')->filter()->unique()->values()->all(),
            ];
        });

        foreach ($result['proof_paths'] as $proofPath) {
            Storage::disk('public')->delete($proofPath);
        }

        return $result['count'];
    }
}
