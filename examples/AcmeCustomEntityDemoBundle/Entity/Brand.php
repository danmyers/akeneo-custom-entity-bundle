<?php

declare(strict_types=1);

namespace Acme\Bundle\CustomEntityDemoBundle\Entity;

use Akeneo\Tool\Component\FileStorage\Model\FileInfo;
use Akeneo\Tool\Component\FileStorage\Model\FileInfoInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\Repository\CustomEntityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A custom entity with a link to another custom entity, a date and an image.
 */
#[ORM\Entity(repositoryClass: CustomEntityRepository::class)]
#[ORM\Table(name: 'acme_demo_brand')]
class Brand extends AbstractCustomEntity
{
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    protected ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $website = null;

    #[ORM\Column(name: 'founded_on', type: 'date', nullable: true)]
    protected ?\DateTimeInterface $foundedOn = null;

    #[ORM\ManyToOne(targetEntity: Fabric::class)]
    #[ORM\JoinColumn(name: 'signature_fabric_id', nullable: true, onDelete: 'SET NULL')]
    protected ?Fabric $signatureFabric = null;

    #[ORM\ManyToOne(targetEntity: FileInfo::class)]
    #[ORM\JoinColumn(name: 'logo_id', nullable: true, onDelete: 'SET NULL')]
    protected ?FileInfoInterface $logo = null;

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

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getFoundedOn(): ?\DateTimeInterface
    {
        return $this->foundedOn;
    }

    public function setFoundedOn(?\DateTimeInterface $foundedOn): self
    {
        $this->foundedOn = $foundedOn;

        return $this;
    }

    public function getSignatureFabric(): ?Fabric
    {
        return $this->signatureFabric;
    }

    public function setSignatureFabric(?Fabric $signatureFabric): self
    {
        $this->signatureFabric = $signatureFabric;

        return $this;
    }

    public function getLogo(): ?FileInfoInterface
    {
        return $this->logo;
    }

    public function setLogo(?FileInfoInterface $logo): self
    {
        $this->logo = $logo;

        return $this;
    }
}
