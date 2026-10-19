<?php

declare(strict_types=1);

namespace Acme\Bundle\CustomEntityDemoBundle\Entity;

use DanMyers\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use DanMyers\CustomEntityBundle\Entity\Repository\TranslatableCustomEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A translatable custom entity: its label is translated per locale.
 */
#[ORM\Entity(repositoryClass: TranslatableCustomEntityRepository::class)]
#[ORM\Table(name: 'acme_demo_pictogram')]
class Pictogram extends AbstractTranslatableCustomEntity
{
    #[ORM\OneToMany(mappedBy: 'foreignKey', targetEntity: PictogramTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $translations;

    #[ORM\Column(name: 'icon_name', type: 'string', length: 100, nullable: true)]
    protected ?string $iconName = null;

    public function getTranslationFQCN(): string
    {
        return PictogramTranslation::class;
    }

    public function getIconName(): ?string
    {
        return $this->iconName;
    }

    public function setIconName(?string $iconName): self
    {
        $this->iconName = $iconName;

        return $this;
    }
}
