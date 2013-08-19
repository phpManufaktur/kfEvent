<?php

/**
 * Event
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/event
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\Event\Control\Backend;

use phpManufaktur\Event\Control\Backend\Backend;
use phpManufaktur\Event\Data\Event\Group as GroupData;
use phpManufaktur\Contact\Control\Contact as ContactControl;
use phpManufaktur\Event\Data\Event\ExtraType;
use phpManufaktur\Event\Data\Event\ExtraGroup;
use phpManufaktur\Event\Data\Event\OrganizerTag;
use phpManufaktur\Event\Data\Event\LocationTag;
use phpManufaktur\Event\Data\Event\ParticipantTag;
use Silex\Application;

class GroupEdit extends Backend {

    protected $GroupData = null;
    protected $ContactControl = null;
    protected $ExtraType = null;
    protected $ExtraGroup = null;
    protected $OrganizerTag = null;
    protected $LocationTag = null;
    protected $ParticipantTag = null;

    protected static $group_id = -1;

    public function __construct(Application $app=null)
    {
        parent::__construct($app);
        if (!is_null($app)) {
            $this->initialize($app);
        }
    }

    protected function initialize(Application $app)
    {
        parent::initialize($app);
        $this->GroupData = new GroupData($this->app);
        $this->ContactControl = new ContactControl($this->app);
        $this->ExtraType = new ExtraType($this->app);
        $this->ExtraGroup = new ExtraGroup($this->app);
        $this->OrganizerTag = new OrganizerTag($this->app);
        $this->LocationTag = new LocationTag($this->app);
        $this->ParticipantTag = new ParticipantTag($this->app);
    }

    /**
     * Set the group ID
     *
     * @param integer $group_id
     */
    public function setGroupID($group_id)
    {
        self::$group_id = $group_id;
    }

    protected function getFormFields($data)
    {
        // get the extra fields for this group
        $extra_field_ids = $this->ExtraGroup->selectTypeIDByGroupID(self::$group_id);

        $form = $this->app['form.factory']->createBuilder('form')
        ->add('group_id', 'hidden', array(
            'data' => $data['group_id']
        ))
        ->add('group_status', 'choice', array(
            'choices' => array('ACTIVE' => 'active', 'LOCKED' => 'locked', 'DELETED' => 'deleted'),
            'empty_value' => false,
            'expanded' => false,
            'multiple' => false,
            'required' => false,
            'label' => 'Status',
            'data' => $data['group_status']
        ))
        ->add('group_name', 'text', array(
            'read_only' => ($data['group_id'] > 0) ? true : false,
            'label' => 'Group name',
            'data' => $data['group_name']
        ))
        ->add('group_description', 'textarea', array(
            'required' => false,
            'label' => 'Description',
            'data' => $data['group_description']
        ))
        ->add('group_organizer_contact_tags', 'choice', array(
            'choices' => $this->ContactControl->getTagArrayForTwig(),
            'expanded' => true,
            'multiple' => true,
            'required' => true,
            'label' => 'Organizer Tags',
            'data' => $this->OrganizerTag->selectTagNamesByGroupID(self::$group_id)
        ))
        ->add('group_location_contact_tags', 'choice', array(
            'choices' => $this->ContactControl->getTagArrayForTwig(),
            'expanded' => true,
            'multiple' => true,
            'required' => true,
            'label' => 'Location Tags',
            'data' => $this->LocationTag->selectTagNamesByGroupID(self::$group_id)
        ))
        ->add('group_participant_contact_tags', 'choice', array(
            'choices' => $this->ContactControl->getTagArrayForTwig(),
            'expanded' => true,
            'multiple' => true,
            'required' => true,
            'label' => 'Participant Tags',
            'data' => $this->ParticipantTag->selectTagNamesByGroupID(self::$group_id)
        ))
        ->add('group_extra_fields', 'hidden', array(
            'data' => implode(',', $extra_field_ids)
        ));

        // insert the extra fields
        $choice_extra_field = $this->ExtraType->getArrayForTwig();
        foreach ($extra_field_ids as $type_id) {
            $type = $this->ExtraType->select($type_id);
            $form->add("extra_field_".$type_id, 'choice', array(
                'choices' => array($type['extra_type_type'] => ucfirst(strtolower($type['extra_type_type']))),
                'empty_value' => '- delete field -',
                'multiple' => false,
                'required' => false,
                'label' => ucfirst(str_replace('_', ' ', strtolower($type['extra_type_name']))),
                'data' => $type['extra_type_type']
            ));
            // remove the type name from the possible selections
            unset($choice_extra_field[$type['extra_type_name']]);
        }

        // add selection for an extra field
        $form->add('add_extra_field', 'choice', array(
            'choices' => $choice_extra_field,
            'empty_value' => '- please select -',
            'expanded' => false,
            'multiple' => false,
            'required' => false,
            'label' => 'Add extra field'
        ));

        return $form;
    }

    public function exec(Application $app, $group_id=null)
    {
        $this->initialize($app);
        if (!is_null($group_id)) {
            $this->setGroupID($group_id);
        }
        // check if a group ID isset
        $form_request = $this->app['request']->request->get('form', array());
        if (isset($form_request['group_id'])) {
            self::$group_id = $form_request['group_id'];
        }

        if (self::$group_id < 1) {
            $group = $this->GroupData->getDefaultRecord();
        }
        elseif (false === ($group = $this->GroupData->select(self::$group_id))) {
            $group = $this->GroupData->getDefaultRecord();
            $this->setMessage('The record with the ID %id% does not exists!', array('%id%' => self::$group_id));
            self::$group_id = -1;
        }

        $fields = $this->getFormFields($group);
        $form = $fields->getForm();

        if ('POST' == $this->app['request']->getMethod()) {
            // the form was submitted, bind the request
            $form->bind($this->app['request']);
            if ($form->isValid()) {
                $group = $form->getData();
                self::$group_id = $group['group_id'];

                $group_extra_fields = (!empty($group['group_extra_fields'])) ? explode(',', $group['group_extra_fields']) : array();
                foreach ($group_extra_fields as $extra_type_id) {
                    if (is_null($group["extra_field_$extra_type_id"])) {
                        // delete the field
                        $this->ExtraGroup->deleteTypeByGroup($extra_type_id, self::$group_id);
                    }
                }
                if (!is_null($group['add_extra_field'])) {
                    // ok - add an extra field!
                    if (false === ($type = $this->ExtraType->selectName($group['add_extra_field']))) {
                        throw new \Exception(sprintf('The extra type field %s does not exists!', $group['add_extra_field']));
                    }
                    $this->ExtraGroup->insert($type['extra_type_id'], self::$group_id);
                }

                if (self::$group_id < 1) {
                    // insert a new group
                    $check = true;
                    $group_name = str_replace(' ', '_', strtoupper($group['group_name']));
                    if (preg_match_all('/[^A-Z0-9_$]/', $group_name, $matches)) {
                        // name check fail
                        $this->setMessage('Allowed characters for the %identifier% identifier are only A-Z, 0-9 and the Underscore. The identifier will be always converted to uppercase.',
                            array('%identifier%' => $this->app['translator']->trans('Group name')));
                        $check = false;
                    }
                    elseif ($this->GroupData->existsGroupName($group_name)) {
                        // the tag already exists
                        $this->setMessage('The identifier %identifier% already exists!', array('%identifier%' => $group_name));
                        $check = false;
                    }
                    // go ahead with the checks
                    if (empty($group['group_organizer_contact_tags'])) {
                        $this->setMessage('Please select at minimum one tag for the %type%.',
                            array('%type%' => $this->app['translator']->trans('Organizer')));
                        $check = false;
                    }
                    if (empty($group['group_location_contact_tags'])) {
                        $this->setMessage('Please select at minimum one tag for the %type%.',
                            array('%type%' => $this->app['translator']->trans('Location')));
                        $check = false;
                    }
                    if (empty($group['group_participant_contact_tags'])) {
                        $this->setMessage('Please select at minimum one tag for the %type%.',
                            array('%type%' => $this->app['translator']->trans('Participant')));
                        $check = false;
                    }

                    if ($check) {
                        // first create the event group and set the new group_id
                        $data = array(
                            'group_name' => $group_name,
                            'group_status' => 'ACTIVE',
                            'group_description' => (!is_null($group['group_description'])) ? $group['group_description'] : '',
                        );
                        $this->GroupData->insert($data, self::$group_id);
                        $this->setMessage('The record with the ID %id% was successfull inserted.',
                            array('%id%' => self::$group_id));

                        // insert organizer tags
                        foreach ($group['group_organizer_contact_tags'] as $key => $value)
                            $this->OrganizerTag->insert(array(
                                'group_id' => self::$group_id,
                                'tag_name' => $value
                            ));

                        // insert location tags
                        foreach ($group['group_location_contact_tags'] as $key => $value)
                            $this->LocationTag->insert(array(
                                'group_id' => self::$group_id,
                                'tag_name' => $value
                            ));

                        // insert participant tags
                        foreach ($group['group_participant_contact_tags'] as $key => $value)
                            $this->ParticipantTag->insert(array(
                                'group_id' => self::$group_id,
                                'tag_name' => $value
                            ));


                    }
                }
                else {
                    // update a group
                    $check = true;
                    if (empty($group['group_organizer_contact_tags'])) {
                        $this->setMessage('Please select at minimum one tag for the %type%.',
                            array('%type%' => $this->app['translator']->trans('Organizer')));
                        $check = false;
                    }
                    if (empty($group['group_location_contact_tags'])) {
                        $this->setMessage('Please select at minimum one tag for the %type%.',
                            array('%type%' => $this->app['translator']->trans('Location')));
                        $check = false;
                    }
                    if (empty($group['group_participant_contact_tags'])) {
                        $this->setMessage('Please select at minimum one tag for the %type%.',
                            array('%type%' => $this->app['translator']->trans('Participant')));
                        $check = false;
                    }
                    if ($check) {
                        // get the actual organizer tags
                        $organizer_tags = $this->OrganizerTag->selectTagNamesByGroupID(self::$group_id);
                        foreach ($organizer_tags as $old_tag) {
                            if (!in_array($old_tag, $group['group_organizer_contact_tags'])) {
                                // delete this tag
                                $this->OrganizerTag->deleteTagByGroup($old_tag, self::$group_id);
                            }
                        }
                        foreach ($group['group_organizer_contact_tags'] as $new_tag) {
                            if (!in_array($new_tag, $organizer_tags)) {
                                // insert a new tag
                                $this->OrganizerTag->insert(array(
                                    'group_id' => self::$group_id,
                                    'tag_name' => $new_tag
                                ));
                            }
                        }

                        // get the actual location tags
                        $location_tags = $this->LocationTag->selectTagNamesByGroupID(self::$group_id);
                        foreach ($location_tags as $old_tag) {
                            if (!in_array($old_tag, $group['group_location_contact_tags'])) {
                                // delete this tag
                                $this->LocationTag->deleteTagByGroup($old_tag, self::$group_id);
                            }
                        }
                        foreach ($group['group_location_contact_tags'] as $new_tag) {
                            if (!in_array($new_tag, $location_tags)) {
                                // insert a new tag
                                $this->LocationTag->insert(array(
                                    'group_id' => self::$group_id,
                                    'tag_name' => $new_tag
                                ));
                            }
                        }

                        $participant_tags = $this->ParticipantTag->selectTagNamesByGroupID(self::$group_id);
                        foreach ($participant_tags as $old_tag) {
                            if (!in_array($old_tag, $group['group_participant_contact_tags'])) {
                                // delete this tag
                                $this->ParticipantTag->deleteTagByGroup($old_tag, self::$group_id);
                            }
                        }
                        foreach ($group['group_participant_contact_tags'] as $new_tag) {
                            if (!in_array($new_tag, $participant_tags)) {
                                // insert a new tag
                                $this->ParticipantTag->insert(array(
                                    'group_id' => self::$group_id,
                                    'tag_name' => $new_tag
                                ));
                            }
                        }

                        $data = array(
                            'group_status' => $group['group_status'],
                            'group_description' => $group['group_description'],
                        );
                        $this->GroupData->update($data, self::$group_id);
                        $this->setMessage('The record with the ID %id% was successfull updated.',
                            array('%id%' => self::$group_id));
                    }
                }

                if (self::$group_id > 0) {
                    // get the actual group
                    $group = $this->GroupData->select(self::$group_id);
                    $fields = $this->getFormFields($group);
                    $form = $fields->getForm();
                }
            }
            else {
                // general error (timeout, CSFR ...)
                $this->setMessage('The form is not valid, please check your input and try again!');
            }
        }

        return $this->app['twig']->render($this->app['utils']->templateFile('@phpManufaktur/Event/Template', 'backend/group.edit.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('group'),
                'message' => $this->getMessage(),
                'form' => $form->createView(),
            ));
    }

}
