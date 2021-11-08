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
use MikoPBX\Modules\Models\ModulesModelsBase;

class ModuleQueueList extends ModulesModelsBase
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
     * @Column(type="integer", nullable=true)
     */
    public $idQueue;

    /**
     * Text field example
     *
     * @Column(type="integer", nullable=true)
     */
    public $priority = '0';

    public function initialize(): void
    {
        $this->setSource('m_ModuleQueueList');
        parent::initialize();
    }


}