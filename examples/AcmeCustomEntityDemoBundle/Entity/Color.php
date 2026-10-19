<?php

declare(strict_types=1);

namespace Acme\Bundle\CustomEntityDemoBundle\Entity;

use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\Repository\CustomEntityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A simple custom entity with scalar fields.
 */
#[ORM\Entity(repositoryClass: CustomEntityRepository::class)]
#[ORM\Table(name: 'acme_demo_color')]
class Color extends AbstractCustomEntity
{
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'The hex code must look like #a1b2c3.')]
    protected ?string $hex = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $metallic = false;

    public static function getLabelProperty()
    {
        return 'name';
    }

    public static function getSortOrderColumn(): string
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

    public function getHex(): ?string
    {
        return $this->hex;
    }

    public function setHex(?string $hex): self
    {
        $this->hex = $hex;

        return $this;
    }

    public function isMetallic(): bool
    {
        return $this->metallic;
    }

    public function setMetallic(bool $metallic): self
    {
        $this->metallic = $metallic;

        return $this;
    }
}
