<?php

namespace App\Enums;

enum GuestPaymentState: string
{
    case AwaitingApproval = 'awaiting_approval';
    case PaymentAvailable = 'payment_available';
    case ReceiptPending = 'receipt_pending';
    case FullyPaid = 'fully_paid';
    case Closed = 'closed';
    case OnSiteBalance = 'onsite_balance';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'Waiting for resort approval',
            self::PaymentAvailable => 'Payment available',
            self::ReceiptPending => 'Receipt awaiting verification',
            self::FullyPaid => 'Fully paid',
            self::Closed => 'Payment closed',
            self::OnSiteBalance => 'Balance payable at check in',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'Waiting for resort approval',
            self::PaymentAvailable => 'Submit deposit',
            self::ReceiptPending => 'Receipt awaiting verification',
            self::FullyPaid => 'Fully paid',
            self::Closed => 'View payment history',
            self::OnSiteBalance => 'View payment history',
        };
    }
}
