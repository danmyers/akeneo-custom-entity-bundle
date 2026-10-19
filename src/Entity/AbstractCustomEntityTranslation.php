<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity;

use Akeneo\Tool\Component\Localization\Model\AbstractTranslation;
use Akeneo\Tool\Component\Localization\Model\TranslationInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base class of a translatable custom entity's translations.
 *
 * The concrete class declares the owning side of the association:
 *
 *     #[ORM\Entity]
 *     #[ORM\Table(name: 'acme_pictogram_translation')]
 *     #[ORM\UniqueConstraint(name: 'acme_pictogram_translation_locale', columns: ['foreign_key', 'locale'])]
 *     class PictogramTranslation extends AbstractCustomEntityTranslation
 *     {
 *         #[ORM\ManyToOne(targetEntity: Pictogram::class, inversedBy: 'translations')]
 *         #[ORM\JoinColumn(name: 'foreign_key', onDelete: 'CASCADE')]
 *         protected $foreignKey;
 *     }
 */
#[ORM\MappedSuperclass]
abstract class AbstractCustomEntityTranslation extends AbstractTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(type: 'string', length: 20)]
    protected $locale;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $label = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }
}
