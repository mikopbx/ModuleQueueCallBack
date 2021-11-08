<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/*
 * https://docs.phalconphp.com/3.4/ru-ru/db-models-metadata
 *
 */


namespace Modules\ModuleQueueCallBack\Models;

use MikoPBX\Common\Models\Providers;
use MikoPBX\Modules\Models\ModulesModelsBase;
use Phalcon\Mvc\Model\Relation;

class ModuleQueueCallBack extends ModulesModelsBase
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;


    /**
     * Text field example
     *
     * @Column(type="string", nullable=true)
     */
    public $peer_number;

    /**
     * Integer field example
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $count_calls;

    /**
     * Integer field example
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $call_billsec;

    /**
     * Количество секунд для анализа пропущенных в истории звонков.
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $delta_no_answered_calls;

    /**
     * Уведомление, что сейчас произойдет соединение с клиентом.
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $alert_for_user;

    /**
     * Уведомление, что сейчас произойдет соединение с клиентом.
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $annonce_order_ok;

    /**
     *
     * @Column(type="integer", default="30", nullable=true)
     */
    public $delay;

    public function initialize(): void
    {
        $this->setSource('m_ModuleQueueCallBack');
        parent::initialize();
    }
}