<?php

/**
 * This file is part of Milpa Governance — the governance-as-contract system of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/governance
 */

declare(strict_types=1);

namespace Milpa\Governance;

/**
 * Identity of the producer that emitted the evidence for a gate (e.g.: a CI script,
 * a static analysis tool, a human verifier).
 */
final readonly class ProducerIdentity
{
    /**
     * @param string  $kind    Type of producer (e.g.: "ci-check", "phpstan", "human")
     * @param string  $id      Identifier of the producer (e.g.: "scripts/ci-check.php")
     * @param ?string $version Version of the producer, if applicable
     */
    public function __construct(
        public string $kind,
        public string $id,
        public ?string $version,
    ) {
        if ($kind === '' || $id === '') {
            throw new \InvalidArgumentException('ProducerIdentity requires non-empty kind and id.');
        }
    }

    /**
     * Serializes the identity to an array for storage or transmission.
     *
     * @return array{kind: string, id: string, version: ?string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->id,
            'version' => $this->version,
        ];
    }
}
