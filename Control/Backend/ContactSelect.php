<?php

/**
 * Event
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/propangas24
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\Event\Control\Backend;

use Silex\Application;
use phpManufaktur\Event\Control\Backend\Backend;
use phpManufaktur\Contact\Control\Dialog\Simple\ContactSelect as SimpleContactSelect;

class ContactSelect extends Backend {

    protected $SimpleContactSelect = null;

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->SimpleContactSelect = new SimpleContactSelect($this->app, array(
            'template' => array(
                'namespace' => '@phpManufaktur/Event/Template',
                'message' => 'backend/message.twig',
                'select' => 'backend/contact.select.twig'
            ),
            'route' => array(
                'action' => '/admin/event/contact/select?usage='.self::$usage,
                'contact' => array(
                    'person' => array(
                        'create' => '/admin/event/contact/person/edit?usage='.self::$usage,
                        'edit' => '/admin/event/contact/person/edit/id/{contact_id}?usage='.self::$usage
                    ),
                    'company' => array(
                        'create' => '/admin/event/contact/company/edit?usage='.self::$usage,
                        'edit' => '/admin/event/contact/company/edit/id/{contact_id}?usage='.self::$usage
                    )
                )
            )
        ));
    }

    public function setContactID($contact_id)
    {
        $this->SimpleContactSelect->setContactID($contact_id);
    }

    public function exec()
    {

        $extra = array(
            'usage' => self::$usage,
            'toolbar' => $this->getToolbar('contact_edit')
        );
        return $this->SimpleContactSelect->exec($extra);
    }

}