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

/**
 * Class ModuleQueueCallBackOrder
 * @package Modules\ModuleQueueCallBack\Models
 *
 *  * @Indexes(
 *     [name='date_src_call', columns=['date_src_call'], type=''],
 *     [name='count_calls', columns=['count_calls'], type=''],
 *     [name='last_result', columns=['last_result'], type='']
 * )
 */
class ModuleQueueCallBackOrder extends ModulesModelsBase
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
    public $number;

    /**
     * Password field example
     *
     * @Column(type="string", nullable=true)
     */
    public $link_src_call;

    /**
     * Password field example
     *
     * @Column(type="string", nullable=true)
     */
    public $date_src_call;

    /**
     * Password field example
     *
     * @Column(type="string", nullable=true)
     */
    public $last_result;

    /**
     * Integer field example
     *
     * @Column(type="integer", default="0", nullable=true)
     */
    public $count_calls;

    public function initialize(): void
    {
        $this->setSource('m_ModuleQueueCallBackOrder');
        parent::initialize();
    }


}