<?php

declare(strict_types=1);

namespace Acme\Bundle\CustomEntityDemoBundle\Entity;

use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntityTranslation;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'acme_demo_pictogram_translation')]
#[ORM\UniqueConstraint(name: 'acme_demo_pictogram_translation_locale', columns: ['foreign_key', 'locale'])]
class PictogramTranslation extends AbstractCustomEntityTranslation
{
    #[ORM\ManyToOne(targetEntity: Pictogram::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'foreign_key', nullable: false, onDelete: 'CASCADE')]
    protected $foreignKey;
}
