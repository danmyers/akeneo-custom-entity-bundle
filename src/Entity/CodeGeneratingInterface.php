<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity;

/**
 * Implemented by custom entities whose code is derived from their other
 * fields instead of being typed by users.
 *
 * The code is generated once, when the entity is created without a code, then
 * kept as is: products and imports reference it. Two entities generating the
 * same code are rejected as duplicates.
 */
interface CodeGeneratingInterface
{
    /**
     * Raw code, e.g. "2026 Jeep Wrangler Unlimited JL". It is normalized to a
     * valid code ("2026_jeep_wrangler_unlimited_jl") by CodeGenerator.
     */
    public function generateCode(): string;
}
