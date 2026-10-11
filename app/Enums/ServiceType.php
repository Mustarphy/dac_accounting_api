<?php

namespace App\Enums;

enum ServiceType: string
{
    case Bookkeeping = 'bookkeeping';
    case TaxFiling = 'tax_filing';
    case Payroll = 'payroll';
    case FinancialStatements = 'financial_statements';
    case AuditSupport = 'audit_support';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bookkeeping => 'Bookkeeping',
            self::TaxFiling => 'Tax Filing',
            self::Payroll => 'Payroll',
            self::FinancialStatements => 'Financial Statements',
            self::AuditSupport => 'Audit Support',
            self::Other => 'Other',
        };
    }
}
