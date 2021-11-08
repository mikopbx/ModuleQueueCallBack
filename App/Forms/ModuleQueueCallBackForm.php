<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 9 2018
 *
 */
namespace Modules\ModuleQueueCallBack\App\Forms;

use Phalcon\Forms\Form;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\Numeric;
use Phalcon\Forms\Element\Password;
use Phalcon\Forms\Element\Check;
use Phalcon\Forms\Element\TextArea;
use Phalcon\Forms\Element\Hidden;
use Phalcon\Forms\Element\Select;


class ModuleQueueCallBackForm extends Form
{

    public function initialize($entity = null, $options = null) :void
    {

        // id
        $this->add(new Hidden('id', ['value' => $entity->id]));

        // integer_field
        $this->add(new Numeric('call_billsec', [
            'maxlength'    => 2,
            'style'        => 'width: 80px;',
            'defaultValue' => 5,
        ]));
        // integer_field
        $this->add(new Numeric('delta_no_answered_calls', [
            'maxlength'    => 5,
            'style'        => 'width: 160px;',
            'defaultValue' => 300,
        ]));

        $this->add(new Numeric('delay', [
            'maxlength'    => 3,
            'style'        => 'width: 160px;',
            'defaultValue' => 30,
        ]));

        $this->add(new Numeric('count_calls', [
            'maxlength'    => 2,
            'style'        => 'width: 80px;',
            'defaultValue' => 5,
        ]));

        $sounds = new Select('alert_for_user', $options['sounds'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => true,
            'class'    => 'ui selection dropdown provider-select',
        ]);
        $this->add($sounds);

        $sounds = new Select('annonce_order_ok', $options['sounds'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => true,
            'class'    => 'ui selection dropdown provider-select',
        ]);
        $this->add($sounds);

        // dropdown_field
        $providers = new Select('peer_number', $options['queues'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => true,
            'class'    => 'ui selection dropdown provider-select',
        ]);
        $this->add($providers);
    }
}