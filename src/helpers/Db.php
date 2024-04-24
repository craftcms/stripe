<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\helpers;

use craft\db\QueryParam;
use craft\elements\db\ElementQueryInterface;

/**
 * Db Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Db
{
    /**
     * Return URL to edit the customer in Stripe Dashboard by the passed customer id.
     *
     * @param string $param
     * @return string
     */
    public static function prepareForLikeSearch(ElementQueryInterface $query, string $param): mixed
    {
        // E.g. price currencies are stored as a string representation of an array.
        // In order to support the usual syntax e.g. ['GBP', 'USD'] or ['not', 'GBP', 'USD']
        // we need to search with `like` condition.
        // So if the parameter is an array, all the query values need to start and end with '*'.

        $result = $query->{$param};

        if (is_array($query->{$param})) {
            $queryParam = QueryParam::parse($query->{$param});
            if (!empty($queryParam->values)) {
                $queryParam->values = array_map(function ($val) {
                    if (!str_starts_with($val, ':')) {
                        return "*" . $val . "*";
                    }
                    return $val;

                }, $queryParam->values);

                $result = array_merge([$queryParam->operator], $queryParam->values);
            }
        }

        if (is_string($query->{$param})) {
            $result = '*' . $query->{$param} . '*';
        }

        return $result;
    }
}
