<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The account's role in its organization.
 */
final class Relation
{
    public const string OWNER = 'OWNER';
    public const string ADMIN = 'ADMIN';
    public const string MEMBER = 'MEMBER';
}
