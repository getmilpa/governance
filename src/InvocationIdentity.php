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
 * Identity of whoever invoked a governance run (human, agent, CI).
 */
final readonly class InvocationIdentity
{
    /**
     * @param string $kind Type of invoker (e.g.: "human", "agent", "ci")
     * @param string $id   Identifier of the invoker (e.g.: "rodrigo", "claude-code")
     */
    public function __construct(
        public string $kind,
        public string $id,
    ) {
        if ($kind === '' || $id === '') {
            throw new \InvalidArgumentException('InvocationIdentity requires non-empty kind and id.');
        }
    }

    /**
     * Serializes the identity to an array for storage or transmission.
     *
     * @return array{kind: string, id: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'id' => $this->id,
        ];
    }
}
