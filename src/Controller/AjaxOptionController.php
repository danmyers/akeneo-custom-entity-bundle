<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Controller;

use Akeneo\Platform\Bundle\UIBundle\Controller\AjaxOptionController as BaseAjaxOptionController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Akeneo's option controller powers the reference data selects of the
 * product edit form and product grid filters. This override forwards the
 * catalog locale to the repository search so that translatable custom
 * entities display their labels in the user's locale.
 */
class AjaxOptionController extends BaseAjaxOptionController
{
    public function listAction(Request $request)
    {
        $dataLocale = $request->get('dataLocale');
        $options = $request->get('options', []);

        if (null !== $dataLocale && is_array($options) && !isset($options['dataLocale'])) {
            $options['dataLocale'] = $dataLocale;
            $request->query->set('options', $options);
            $request->request->remove('options');
            $request->attributes->remove('options');
        }

        return parent::listAction($request);
    }
}
