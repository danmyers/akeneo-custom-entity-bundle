<?php

declare(strict_types=1);

namespace Acme\Bundle\CustomEntityDemoBundle\Entity;

use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\Repository\CustomEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A custom entity linked to several other custom entities (colors).
 */
#[ORM\Entity(repositoryClass: CustomEntityRepository::class)]
#[ORM\Table(name: 'acme_demo_fabric')]
class Fabric extends AbstractCustomEntity
{
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    protected ?string $name = null;

    #[ORM\Column(name: 'alternative_name', type: 'string', length: 255, nullable: true)]
    protected ?string $alternativeName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(name: 'weight_gsm', type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    protected ?int $weightGsm = null;

    #[ORM\ManyToMany(targetEntity: Color::class)]
    #[ORM\JoinTable(name: 'acme_demo_fabric_color')]
    #[ORM\JoinColumn(name: 'fabric_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'color_id', onDelete: 'CASCADE')]
    protected Collection $colors;

    public function __construct()
    {
        $this->colors = new ArrayCollection();
    }

    public static function getLabelProperty()
    {
        return 'name';
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAlternativeName(): ?string
    {
        return $this->alternativeName;
    }

    public function setAlternativeName(?string $alternativeName): self
    {
        $this->alternativeName = $alternativeName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getWeightGsm(): ?int
    {
        return $this->weightGsm;
    }

    public function setWeightGsm(?int $weightGsm): self
    {
        $this->weightGsm = $weightGsm;

        return $this;
    }

    public function getColors(): Collection
    {
        return $this->colors;
    }

    public function addColor(Color $color): self
    {
        if (!$this->colors->contains($color)) {
            $this->colors->add($color);
        }

        return $this;
    }

    public function removeColor(Color $color): self
    {
        $this->colors->removeElement($color);

        return $this;
    }
}
