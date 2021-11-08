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

namespace Modules\ModuleQueueCallBack\bin;

use MikoPBX\Common\Models\CallQueueMembers;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Core\Asterisk\AsteriskManager;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerCdr;
use Modules\ModuleQueueCallBack\Models\ModuleQueueCallBack;
use Modules\ModuleQueueCallBack\Models\ModuleQueueCallBackOrder;
use Throwable;

require_once 'Globals.php';

class Dialer
{
    private AsteriskManager $am;
    private string $peerNumber  = '';
    private int    $countCalls  = 5;
    private int    $callBillsec = 5;
    private int    $deltaHistory = 300;
    private int    $delayTime = 30;
    private string $queueID     = '';
    private bool   $badNumber   = false;
    private array  $queue;
    private const  QUEUE_LIST_FILE = '/tmp/queue-list-file.dmp';

    /**
     * Dialer constructor.
     */
    public function __construct()
    {
        $this->am         = Util::getAstManager('off');

        /** @var ModuleQueueCallBack $settings */
        $settings = ModuleQueueCallBack::findFirst();
        if($settings){
            $this->countCalls   = $settings->count_calls;
            $this->callBillsec  = $settings->call_billsec;
            $this->peerNumber   = $settings->peer_number;
            $this->delayTime    = $settings->delay;
            $this->deltaHistory = (int) $settings->delta_no_answered_calls;
        }

        /** @var CallQueues $queueData */
        $queueData   = CallQueues::findFirst($this->peerNumber);
        if($queueData){
            $this->queueID = (string)$queueData->uniqid;
            unset($queueData);
        }else{
            $this->badNumber = true;
        }

        $this->queue = [];
        if(file_exists(self::QUEUE_LIST_FILE)){
            try {
                $queue = json_decode(file_get_contents(self::QUEUE_LIST_FILE), true, 512, JSON_THROW_ON_ERROR);
                if(is_array($queue)){
                    $this->queue = $queue;
                }
            }catch (Throwable $e){
                Util::sysLogMsg(__CLASS__, 'Error parce file '.self::QUEUE_LIST_FILE);
            }
        }

    }

    /**
     * Начало работы Dialer.
     */
    public function start():void{

        if($this->badNumber){
            return;
        }
        $this->checkMissed();
        $callbackOrders = ModuleQueueCallBackOrder::find('count_calls > 0');

        /** @var ModuleQueueCallBackOrder $order */
        foreach ($callbackOrders as $order){
            $forUnSet = [];
            // Проверка кэша номеров на частое выполнение.
            foreach ($this->queue as $number => $dataJob){
                $delta = time() - $dataJob;
                if($delta > $this->delayTime){
                    $forUnSet[] = $number;
                }
            }
            // Чистка старого кэш.
            foreach ($forUnSet as $number){
                unset($this->queue[$number]);
            }
            // Анализ как давно был последний вызов на номер.
            if(isset($this->queue[$order->number])){
                $delta = time() - $this->queue[$order->number];
            }else{
                $delta = $this->delayTime + 1;
                $this->queue[$order->number] = time();
            }
            if($delta > $this->delayTime){
                $this->invokeOrder($order);
            }
            sleep(2);
        }

        try {
            file_put_contents(self::QUEUE_LIST_FILE, json_encode($this->queue, JSON_THROW_ON_ERROR));
        }catch (Throwable $e){
            Util::sysLogMsg(__CLASS__, 'Error parce file '.self::QUEUE_LIST_FILE);
        }
    }

    /**
     * Проверка наличия пропущенных вызовов.
     */
    private function checkMissed():void{
        // sqlite3 /storage/usbdisk1/mikopbx/custom_modules/ModuleQueueCallBack/db/module.db 'select * from m_ModuleQueueCallBackOrder WHERE count_calls<>"-1"'
        // sqlite3 /storage/usbdisk1/mikopbx/astlogs/asterisk/cdr.db "select start,linkedid,src_num,dst_num,billsec from cdr_general where disposition<>'ANSWERED' AND start>'2021-03-09 16:50'";

        $extensionLength = PbxSettings::getValueByKey('PBXInternalExtensionLength');
        $time = date('Y-m-d H:i', time() - $this->deltaHistory);
        $missedCalls = [];

        $answeredCalls = [];

        $calls = $this->getTempCdr(["start>'$time'"], false);
        foreach ($calls as $call) {
            if($call['disposition'] === 'ANSWERED'){
                unset($missedCalls[$call['src_num']]);
                $answeredCalls[] = $call['linkedid'];
            }
            if(in_array($call['linkedid'], $answeredCalls, true)){
                continue;
            }
            if(!is_numeric($call['src_num']) || strlen($call['src_num']) <= $extensionLength){
                continue;
            }
            $missedCalls[$call['src_num']] = [
                'date_src_call' => $call['start'],
                'link_src_call' => $call['linkedid'],
            ];
        }
        unset($answeredCalls);

        foreach ($missedCalls as $number => $data){
            $callBackOrder = ModuleQueueCallBackOrder::findFirst("number='$number' AND date_src_call>'$time'");
            if($callBackOrder){
                continue;
            }
            $callBackOrder = new ModuleQueueCallBackOrder();
            $callBackOrder->number        = $number;
            $callBackOrder->date_src_call = $data['date_src_call'];
            $callBackOrder->link_src_call = $data['link_src_call'];
            $callBackOrder->count_calls   = $this->countCalls;
            $callBackOrder->last_result   = '';
            $callBackOrder->save();
        }
    }

    /**
     * Проверка наличия свободных агентов.
     * @return bool
     */
    private function haveFreePeer():bool{
        $haveFree = false;

        $peers  = $this->am->getPjSipPeers();
        $availPeers = [];
        foreach ($peers as $peer){
            if($peer['state'] === 'OK'){
                $availPeers[] = $peer['id'];
            }
        }
        if(empty($this->queueID) && in_array($this->peerNumber, $availPeers, true)){
            return true;
        }

        if(empty($this->queueID)){
            return false;
        }

        /** @var CallQueueMembers $member */
        $membersData = CallQueueMembers::find("queue='$this->queueID'");
        foreach ($membersData as $member){
            if(in_array($member->extension, $availPeers, true)){
                $haveFree = true;
                break;
            }
        }
        return $haveFree;
    }

    /**
     * Возвращает все завершенные временные CDR.
     * @param array $filter
     * @param bool  $fromTmpTable
     * @return array
     */
    public function getTempCdr(array $filter = [], bool $fromTmpTable = true):array
    {
        $filter['miko_result_in_file'] = true;
        if($fromTmpTable){
            $filter['miko_tmp_db'] = true;
        }
        $client = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
        try {
            $result   = $client->request(json_encode($filter, JSON_THROW_ON_ERROR), 2);
            $filename = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        }catch (Throwable $e){
            $filename = '';
        }
        $result_data = [];
        if (file_exists($filename)) {
            try {
                $result_data = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);
            }catch (Throwable $e){
                Util::sysLogMsg('SELECT_CDR_TUBE', 'Error parse response.');
            }
            unlink($filename);
        }

        return $result_data;
    }

    /**
     * Выполнение задания на обзвон.
     * @param ModuleQueueCallBackOrder $order
     */
    private function invokeOrder(ModuleQueueCallBackOrder $order):void
    {
        $dst    = preg_replace("/^\D/", '', $order->number);
        $arrDst = [];
        if(mb_strlen($dst) === 11){
            $arrDst[] = '7'.substr($dst, 1);
            $arrDst[] = '8'.substr($dst, 1);
        }else{
            $arrDst[] = $dst;
        }

        if(!$this->isRelevant($order)){
            $order->count_calls = -3;
            $order->save();
            return;
        }

        $count_calls = $this->haveAnsweredCalls($order, $arrDst);
        if ($count_calls < 0) {
            $order->count_calls = -1;
            $order->save();
            return;
        }
        if($this->haveActiveCalls($order, $arrDst) || !$this->haveFreePeer() ){
            return;
        }
        $variable = "pt1c_cid=$dst";
        if(empty($this->queueID)){
            $channel  = 'Local/' . $this->peerNumber . '@internal-originate-v2';
        }else{
            $channel  = 'Local/' . $this->peerNumber . '@internal-originate-v2-queue';
            $variable.= ",SRC_QUEUE=$this->queueID";
        }
        $context  = 'originate-wait';
        $this->am->Originate($channel, $dst, $context, '1', null, null, null, null, $variable, null, true);
    }

    private function isRelevant($order):bool{
        $d = \DateTime::createFromFormat('Y-m-d H:i:s.v', $order->date_src_call);
        if ($d === false) {
            return true;
        }
        $result = true;
        if($this->deltaHistory < (time() - $d->getTimestamp())){
            $result = false;
        }
        return $result;
    }

    /**
     * Проверка наличия отвеченных.
     * @param $order
     * @param $arrDst
     * @return int
     */
    private function haveAnsweredCalls($order, $arrDst):int
    {
        $count_calls = (int)$order->count_calls;
        $filter = [
            "start>:start: AND dst_num IN ({numbers:array})",
            'bind' => [
                'start'     => $order->date_src_call,
                'numbers'   => $arrDst
            ],
            'columns' => 'billsec',
        ];

        $calls = $this->getTempCdr($filter, false);
        foreach ($calls as $call) {
            if ($call['billsec'] > $this->callBillsec) {
                $count_calls = -1;
            }
        }
        if (count($calls) >= $this->countCalls) {
            unset($calls);
            // Слишком много попыток звонка.
            $count_calls = -1;
        }
        return $count_calls;
    }

    /**
     * Проверка наличия активных звонков.
     * @param $order
     * @param $arrDst
     * @return bool
     */
    private function haveActiveCalls($order, $arrDst):bool
    {
        $result = false;
        $filter = [
            "start>:start: AND (src_num IN ({numbers:array}) OR dst_num IN ({numbers:array}))",
            'bind' => [
                'start'     => $order->date_src_call,
                'numbers'   => $arrDst
            ],
        ];
        $calls = $this->getTempCdr($filter);
        if (!empty($calls)) {
            unset($calls);
            // Есть текущие вызовы по этому номеру.
            $result = true;
        }
        return $result;
    }
}

$pidFile = '/var/run/callback-queue-dialer.pid';
if(file_exists($pidFile)){
    $pid     = file_get_contents($pidFile);
    $result  = shell_exec("/bin/ps -A -o pid | /bin/grep '^$pid\$' " );
    echo $result;
    if(!empty($result)){
        exit(1);
    }
}
$pid = getmypid();
file_put_contents($pidFile, $pid);

$dialer = new Dialer();
$dialer->start();



