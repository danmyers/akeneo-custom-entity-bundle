<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity;

/**
 * Normalizes free text to a valid custom entity code: lowercase ASCII letters,
 * digits and single underscores, at most 100 characters.
 */
final class CodeGenerator
{
    public const MAX_LENGTH = 100;

    public static function normalize(string $raw): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw) : $raw;
        $code = strtolower($ascii);
        $code = (string) preg_replace('/[^a-z0-9]+/', '_', $code);
        $code = trim($code, '_');

        return rtrim(substr($code, 0, self::MAX_LENGTH), '_');
    }

    /**
     * Sets the generated code of an entity that has none yet.
     */
    public static function apply(AbstractCustomEntity $entity): void
    {
        if (!$entity instanceof CodeGeneratingInterface) {
            return;
        }
        if (null !== $entity->getCode() && '' !== $entity->getCode()) {
            return;
        }

        $code = self::normalize($entity->generateCode());
        if ('' !== $code) {
            $entity->setCode($code);
        }
    }
}
