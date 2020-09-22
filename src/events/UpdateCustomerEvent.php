<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\stripe\events;

use craft\events\CancelableEvent;

/**
 * Class UpdateUserEvent
 *
 * @author Levinriegner
 * @since 1.0
 */
class UpdateCustomerEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var array The customer data.
     */
    public $customer;

    /**
     * @var array Customer updated data
     */
    public $updatedData;

    public  function init ( ) {
        parent::init();

        //Not valid by default, meaning the user won't be updated unless explicitly specified
        $this->isValid = false;
    }
}
