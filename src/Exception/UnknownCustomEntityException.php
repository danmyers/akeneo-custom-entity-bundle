<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Exception;

final class UnknownCustomEntityException extends \InvalidArgumentException
{
    public static function fromName(string $name): self
    {
        return new self(sprintf('No custom entity named "%s" is configured.', $name));
    }
}
