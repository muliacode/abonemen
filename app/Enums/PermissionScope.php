<?php

namespace App\Enums;

/**
 * How far a permission reaches among a Household's Subscriptions.
 */
enum PermissionScope: string
{
    case All = 'all';
    case Own = 'own';
    case Visible = 'visible';
}
