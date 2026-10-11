<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case BankStatement = 'bank_statement';
    case Supporting = 'supporting';
    case Deliverable = 'deliverable';
    case Other = 'other';
}
