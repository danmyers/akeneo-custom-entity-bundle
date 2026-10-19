<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Manager;

use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\CodeGenerator;
use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Default manager: Doctrine for lookups, Akeneo's base saver and remover for
 * writes (so storage events, history and the removal guard apply), the
 * bundle updater and the Symfony validator.
 */
class CustomEntityManager implements CustomEntityManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ObjectUpdaterInterface $updater,
        private readonly ValidatorInterface $validator,
        private readonly SaverInterface $saver,
        private readonly RemoverInterface $remover,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function create(CustomEntityDefinition $definition, array $data = []): AbstractCustomEntity
    {
        $class = $definition->getClass();
        /** @var AbstractCustomEntity $entity */
        $entity = new $class();
        if ([] !== $data) {
            $this->update($entity, $data);
        }

        return $entity;
    }

    public function find(CustomEntityDefinition $definition, int $id): ?AbstractCustomEntity
    {
        return $this->entityManager->getRepository($definition->getClass())->find($id);
    }

    public function findByCode(CustomEntityDefinition $definition, string $code): ?AbstractCustomEntity
    {
        return $this->entityManager->getRepository($definition->getClass())->findOneBy(['code' => $code]);
    }

    public function findAll(CustomEntityDefinition $definition): array
    {
        return $this->entityManager->getRepository($definition->getClass())->findBy([], ['sortOrder' => 'DESC', 'code' => 'ASC']);
    }

    /**
     * Also generates the code of new entities implementing
     * CodeGeneratingInterface, once their fields are set.
     */
    public function update(AbstractCustomEntity $entity, array $data): void
    {
        $this->updater->update($entity, $data);
        CodeGenerator::apply($entity);
    }

    public function validate(AbstractCustomEntity $entity): ConstraintViolationListInterface
    {
        return $this->validator->validate($entity);
    }

    public function save(AbstractCustomEntity $entity, array $options = []): void
    {
        $this->saver->save($entity, $options);
    }

    public function remove(AbstractCustomEntity $entity, array $options = []): void
    {
        $this->remover->remove($entity, $options);
    }

    public function normalize(AbstractCustomEntity $entity, string $format = 'internal_api', array $context = []): array
    {
        return $this->normalizer->normalize($entity, $format, $context);
    }
}
