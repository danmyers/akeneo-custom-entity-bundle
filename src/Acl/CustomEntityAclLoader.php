<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Acl;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\Loader\AclAnnotationLoaderInterface;
use Oro\Bundle\SecurityBundle\Metadata\AclAnnotationStorage;

/**
 * Declares the ACL resources of every custom entity, shown in the
 * "Custom entities" group of the role permissions screen:
 * <prefix>_index, _create, _edit, _delete and _history.
 */
class CustomEntityAclLoader implements AclAnnotationLoaderInterface
{
    public const ACL_GROUP = 'danmyers_custom_entity.acl_group.custom_entities';

    private const PERMISSIONS = [
        'index' => 'List %s',
        'create' => 'Create %s',
        'edit' => 'Edit %s',
        'delete' => 'Delete %s',
        'history' => 'View %s history',
    ];

    /**
     * @param array<string, array> $entities normalized bundle configuration
     */
    public function __construct(private readonly array $entities)
    {
    }

    public function load(AclAnnotationStorage $storage): void
    {
        $order = 0;
        foreach ($this->entities as $entity) {
            foreach (self::PERMISSIONS as $permission => $label) {
                $storage->add(new Acl([
                    'id' => $entity['acl'][$permission],
                    'type' => 'action',
                    'label' => sprintf($label, $entity['label']),
                    'group_name' => self::ACL_GROUP,
                    'order' => $order++,
                ]));
            }
        }
    }
}
