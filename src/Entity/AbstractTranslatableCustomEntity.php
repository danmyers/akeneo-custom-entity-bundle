<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity;

use Akeneo\Tool\Component\Localization\Model\TranslatableInterface;
use Akeneo\Tool\Component\Localization\Model\TranslationInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base class of custom entities with a label translated per locale.
 *
 * The concrete class declares the translations association, because Doctrine
 * needs the concrete translation class:
 *
 *     #[ORM\Entity(repositoryClass: TranslatableCustomEntityRepository::class)]
 *     class Pictogram extends AbstractTranslatableCustomEntity
 *     {
 *         #[ORM\OneToMany(mappedBy: 'foreignKey', targetEntity: PictogramTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
 *         protected Collection $translations;
 *
 *         public function getTranslationFQCN(): string
 *         {
 *             return PictogramTranslation::class;
 *         }
 *     }
 */
#[ORM\MappedSuperclass]
abstract class AbstractTranslatableCustomEntity extends AbstractCustomEntity implements TranslatableInterface
{
    protected Collection $translations;

    protected ?string $locale = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * @return class-string<AbstractCustomEntityTranslation>
     */
    abstract public function getTranslationFQCN();

    public static function getLabelProperty()
    {
        return 'label';
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * Returns the translation of the given locale (or of the current locale),
     * creating it when it does not exist yet.
     */
    public function getTranslation(?string $locale = null): ?AbstractCustomEntityTranslation
    {
        $locale ??= $this->locale;
        if (null === $locale) {
            return null;
        }

        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        $class = $this->getTranslationFQCN();
        /** @var AbstractCustomEntityTranslation $translation */
        $translation = new $class();
        $translation->setLocale($locale);
        $translation->setForeignKey($this);
        $this->addTranslation($translation);

        return $translation;
    }

    /**
     * Returns the existing translation of a locale without creating it.
     */
    public function findTranslation(string $locale): ?AbstractCustomEntityTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(TranslationInterface $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): static
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    public function getLabel(?string $locale = null): ?string
    {
        $locale ??= $this->locale;
        $translation = null === $locale ? null : $this->findTranslation($locale);

        return $translation?->getLabel();
    }

    public function setLabel(?string $label, ?string $locale = null): static
    {
        $this->getTranslation($locale)?->setLabel($label);

        return $this;
    }

    /**
     * @return array<string, string> labels indexed by locale code
     */
    public function getLabels(): array
    {
        $labels = [];
        foreach ($this->translations as $translation) {
            if (null !== $translation->getLabel() && '' !== $translation->getLabel()) {
                $labels[$translation->getLocale()] = $translation->getLabel();
            }
        }
        ksort($labels);

        return $labels;
    }

    public function __toString()
    {
        $label = $this->getLabel();

        return null !== $label && '' !== $label ? $label : sprintf('[%s]', $this->code);
    }
}
