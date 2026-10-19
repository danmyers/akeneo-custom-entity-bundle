<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Event;

final class CustomEntityEvents
{
    /** Dispatched before a REST action runs, with a CustomEntityActionEvent. */
    public const PRE_ACTION = 'danmyers_custom_entity.pre_action';

    /** Dispatched after a REST action ran, with a CustomEntityActionEvent. */
    public const POST_ACTION = 'danmyers_custom_entity.post_action';

    public const ACTION_GET = 'get';
    public const ACTION_LIST = 'list';
    public const ACTION_SEARCH = 'search';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    private function __construct()
    {
    }
}
