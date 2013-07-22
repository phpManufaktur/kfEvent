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

use phpManufaktur\Event\Control\Backend\Backend;
use phpManufaktur\Event\Data\Event\ExtraType;

class ExtraFieldEdit extends Backend {

    protected $ExtraType = null;
    protected static $type_id = -1;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->ExtraType = new ExtraType($this->app);
    }

    public function setTypeID($type_id)
    {
        self::$type_id = $type_id;
    }

    protected function getFormFields($data)
    {
        $fields = $this->app['form.factory']->createBuilder('form', $data)
        ->add('extra_type_id', 'hidden')
        ->add('extra_type_name', 'text', array(
            'read_only' => ($data['extra_type_id'] > 0) ? true : false,
            'label' => 'Field name'
        ))
        ->add('extra_type_type', 'choice', array(
            'choices' => $this->ExtraType->getTypeArrayForTwig(),
            'empty_value' => '- please select -',
            'expanded' => false,
            'multiple' => false,
            'label' => 'Field type'
        ))
        ->add('extra_type_description', 'textarea', array(
            'required' => false,
            'label' => 'Description'
        ))
        ->add('delete', 'choice', array(
            'choices' => array('DELETE' => 'delete this extra field'),
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'label' => 'Delete'
        ))
        ;
        return $fields;
    }

    public function exec()
    {
        if (self::$type_id < 1) {
            $type = $this->ExtraType->getDefaultRecord();
        }
        elseif (false === ($type = $this->ExtraType->select(self::$type_id))) {
            $type = $this->ExtraType->getDefaultRecord();
            $this->setMessage('The extra type with the ID %type_id% does not exists!', array('%type_id%' => self::$type_id));
            self::$type_id = -1;
        }

        $fields = $this->getFormFields($type);
        $form = $fields->getForm();

        if ('POST' == $this->app['request']->getMethod()) {
            // the form was submitted, bind the request
            $form->bind($this->app['request']);
            if ($form->isValid()) {
                $type = $form->getData();
                self::$type_id = $type['extra_type_id'];
                if (self::$type_id < 1) {
                    // insert a new extra field
                    $matches = array();
                    $type_name = str_replace(' ', '_', strtoupper($type['extra_type_name']));
                    if (preg_match_all('/[^A-Z0-9_$]/', $type_name, $matches)) {
                        // name check fail
                        $this->setMessage('Allowed characters for the %identifier% identifier are only A-Z, 0-9 and the Underscore. The identifier will be always converted to uppercase.',
                            array('%identifier%' => $this->app['tranlator']->trans('Extra field')));
                    }
                    elseif ($this->ExtraType->existsTypeName($type_name)) {
                        // the tag already exists
                        $this->setMessage('The identifier %identifier% already exists!', array('%identifier%' => $type_name));
                    }
                    else {
                        $data = array(
                            'extra_type_type' => $type['extra_type_type'],
                            'extra_type_name' => $type_name,
                            'extra_type_description' => $type['extra_type_description']
                        );
                        $this->ExtraType->insert($data, self::$type_id);
                        $this->setMessage('The record with the ID %id% was successfull inserted.',
                            array('%id%' => self::$type_id));
                    }
                }
                else {
                    // update a extra field
                    $data = array(
                        'extra_type_type' => $type['extra_type_type'],
                        'extra_type_description' => $type['extra_type_description']
                    );
                    $this->ExtraType->update($data, self::$type_id);
                    $this->setMessage('The record with the ID %id% was successfull updated.',
                        array('%id%' => self::$type_id));
                }

                if (self::$type_id > 0) {
                    // get the actual extra field
                    $type = $this->ExtraType->select(self::$type_id);
                    $fields = $this->getFormFields($type);
                    $form = $fields->getForm();
                }
            }
        }

        return $this->app['twig']->render($this->app['utils']->templateFile('@phpManufaktur/Event/Template', 'backend/extra.field.edit.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('group'),
                'message' => $this->getMessage(),
                'form' => $form->createView()
            ));
    }

}