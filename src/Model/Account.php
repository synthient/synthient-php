<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

/**
 * The account that owns the API key, from Client::account().
 */
final readonly class Account
{
    /**
     * @param list<string> $scopes the permissions granted to the key, see Scope
     * @param AccountApiKey|null $apiKey the key that made the request
     */
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public Organization $organization,
        #[Wire(list: 'string')]
        public array $scopes,
        public LookupQuota $lookupQuota,
        public ?AccountApiKey $apiKey = null,
    ) {}
}
