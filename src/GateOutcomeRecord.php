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
 * The evidence record of a gate within a governance run: which gate,
 * which verdict, where it was bound in the pipeline, and who produced it.
 */
final readonly class GateOutcomeRecord
{
    /**
     * @param string      $gate          Identifier of the governance gate (e.g.: "phpstan")
     * @param GateOutcome $outcome       Verdict of the run
     * @param ?string     $boundTo       Pipeline point where it ran (e.g.: "ci:quality:phpstan")
     * @param ?string     $producerRef   Reference to the step/producer that emitted the verdict
     * @param ?string     $justification Justification, required in practice for WAIVED
     */
    public function __construct(
        public string $gate,
        public GateOutcome $outcome,
        public ?string $boundTo,
        public ?string $producerRef,
        public ?string $justification = null,
    ) {
    }

    /**
     * Serializes the record to an array for storage or transmission.
     *
     * @return array{gate: string, outcome: string, boundTo: ?string, producerRef: ?string, justification: ?string}
     */
    public function toArray(): array
    {
        return [
            'gate' => $this->gate,
            'outcome' => $this->outcome->value,
            'boundTo' => $this->boundTo,
            'producerRef' => $this->producerRef,
            'justification' => $this->justification,
        ];
    }
}
