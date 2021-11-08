#!/usr/bin/php
<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2021 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

use MikoPBX\Core\Asterisk\AGI;
use Modules\ModuleQueueCallBack\Models\ModuleQueueCallBackOrder;
use MikoPBX\Core\System\Util;
use Modules\ModuleQueueCallBack\Models\ModuleQueueCallBack;

require_once 'Globals.php';

$agi    = new AGI();
$number = $agi->request['agi_callerid'];

$callBackOrder = ModuleQueueCallBackOrder::findFirst("number='{$number}' AND count_calls<>'-1'");
if(!$callBackOrder){
    $callBackOrder = new ModuleQueueCallBackOrder();
    $callBackOrder->number = $number;
}

/** @var ModuleQueueCallBack $settings */
$settings = ModuleQueueCallBack::findFirst();
$count_calls = $settings->count_calls ?? 5;

$callBackOrder->date_src_call = Util::getNowDate();
$callBackOrder->link_src_call = $agi->get_variable("CHANNEL(linkedid)", true);
$callBackOrder->count_calls   = $count_calls;
$callBackOrder->last_result   = '';
$callBackOrder->save();

$agi->set_variable('MASTER_CHANNEL(M_DIALSTATUS)', 'ANSWER');
