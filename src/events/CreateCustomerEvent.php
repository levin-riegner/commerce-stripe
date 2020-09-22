<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\stripe\events;

use yii\base\Event;

/**
 * Class CreateCustomerEvent
 *
 * @author Levinriegner
 * @since 1.0
 */
class CreateCustomerEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array The customer data.
     */
    public $customerData;
}
