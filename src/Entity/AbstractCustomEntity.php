<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity;

use Akeneo\Pim\Enrichment\Component\Product\Model\AbstractReferenceData;
use Akeneo\Tool\Component\Versioning\Model\VersionableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Base class of every custom entity.
 *
 * Provides the identifier, the unique code, the sort order used by Akeneo's
 * reference data selects, and created/updated timestamps. Entities are
 * versioned (history tab) out of the box.
 *
 *     #[ORM\Entity(repositoryClass: CustomEntityRepository::class)]
 *     #[ORM\Table(name: 'acme_color')]
 *     class Color extends AbstractCustomEntity
 *     {
 *         #[ORM\Column(length: 255, nullable: true)]
 *         protected ?string $name = null;
 *     }
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'danmyers_custom_entity.code.unique')]
abstract class AbstractCustomEntity extends AbstractReferenceData implements VersionableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_]+$/', message: 'danmyers_custom_entity.code.format')]
    protected $code;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 1])]
    #[Assert\Type('integer')]
    protected $sortOrder = 1;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $created = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updated = null;

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(?\DateTimeInterface $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }

    public function setUpdated(?\DateTimeInterface $updated): static
    {
        $this->updated = $updated;

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $this->created ??= $now;
        $this->updated = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedTimestamp(): void
    {
        $this->updated = new \DateTime('now', new \DateTimeZone('UTC'));
    }

    /**
     * Property used as the human readable label in selects, datagrids and
     * page titles. Null means "use the code".
     */
    public static function getLabelProperty()
    {
        return null;
    }

    /**
     * Property used to sort entities in reference data selects, after the
     * sort order.
     */
    public static function getSortOrderColumn(): string
    {
        return 'code';
    }
}
