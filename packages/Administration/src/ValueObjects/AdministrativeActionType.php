<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

/**
 * The closed set of deliberate administrative decisions `admin_actions`
 * records (ADR-026 Architecture Refinements §3) — fixed in code, one
 * case per action type, never an arbitrary string. New cases are added
 * one at a time, as each sprint introduces the action it actually
 * needs (mirroring `AdminCapability`'s identical discipline).
 */
enum AdministrativeActionType: string
{
    case AccountSuspended = 'account_suspended';
    case AccountReinstated = 'account_reinstated';
}
