<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 12 2019
 */

namespace Modules\ModuleQueueCallBack\Lib;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\CustomFiles;
use MikoPBX\Common\Models\SoundFiles;
use MikoPBX\Core\System\PBX;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleQueueCallBack\Models\ModuleQueueCallBack;
use Modules\ModuleQueueCallBack\Models\ModuleQueueList;

class QueueCallBackConf extends ConfigClass
{
    public const QUEUE_CONTEXT = 'queue-callback-order';

    /**
     * Receive information about mikopbx main database changes
     *
     * @param $data
     */
    public function modelsEventChangeData($data): void
    {
        if ( $data['model'] === ModuleQueueList::class) {
            $moduleEnabled  = PbxExtensionUtils::isEnabled($this->moduleUniqueId);
            $this->generateCustomConfig($moduleEnabled);
        }
    }

    private function getPlabackString($setting, $name):string
    {
        $playbackConf = '';
        $filename = '';
        if($setting){
            $filename = $this->getSoundFileByID($setting->$name);
        }
        if(file_exists($filename.'.wav')){
            $playbackConf = "same => n,Playback($filename)".PHP_EOL."\t";
        }
        return $playbackConf;
    }

    /**
     * Генерация дополнительных контекстов.
     *
     * @return string
     */
    public function extensionGenContexts():string
    {
        /** @var ModuleQueueCallBack $setting */
        $setting      = ModuleQueueCallBack::findFirst();
        $playbackConf = $this->getPlabackString($setting, "alert_for_user");
        $playbackOk   = $this->getPlabackString($setting, "annonce_order_ok");

        $conf = "[".self::QUEUE_CONTEXT."]".PHP_EOL.
                "exten => 1,1,Agi({$this->moduleDir}/agi-bin/CallbackRegistrar.php)".PHP_EOL."\t".
                $playbackOk.
                "same => n,Hangup()".PHP_EOL.PHP_EOL;

        $conf.= '[internal-originate-v2]'.PHP_EOL.
                'exten => _X!,1,Set(MASTER_CHANNEL(ORIGINATE_DST_EXTEN)=${pt1c_cid})'.PHP_EOL."\t".
                'same => n,ExecIf($["${CUT(CHANNEL,\;,2)}" == "2"]?Set(__PT1C_SIP_HEADER=${SIPADDHEADER})) '.PHP_EOL."\t".
                'same => n,ExecIf($["${pt1c_cid}x" != "x"]?Set(CALLERID(num)=${pt1c_cid}))'.PHP_EOL."\t".
                'same => n,GosubIf($["${DIALPLAN_EXISTS(${CONTEXT}-custom,${EXTEN},1)}" == "1"]?${CONTEXT}-custom,${EXTEN},1)'.PHP_EOL."\t".
                'same => n,ExecIf($["${PJSIP_ENDPOINT(${EXTEN},auth)}x" == "x"]?Goto(internal-num-undefined,${EXTEN},1))'.PHP_EOL."\t".
                'same => n,Gosub(set-dial-contacts,${EXTEN},1)' . PHP_EOL . "\t" .
                'same => n,ExecIf($["${FIELDQTY(DST_CONTACT,&)}" != "1"]?Set(__PT1C_SIP_HEADER=${EMPTY_VAR}))'.PHP_EOL."\t".
                'same => n,ExecIf($["${DST_CONTACT}x" != "x"]?Dial(${DST_CONTACT},${ringlength},TtekKHhb(originate-create-chan,${EXTEN},1)U(originate-answer),s,1)))'.PHP_EOL.PHP_EOL.

                '[internal-originate-v2-queue]'.PHP_EOL.
                'exten => _X!,1,Set(MASTER_CHANNEL(ORIGINATE_DST_EXTEN)=${pt1c_cid})'.PHP_EOL."\t".
                'same => n,Set(_NOCDR=1)'.PHP_EOL."\t".
                'same => n,ExecIf($["${pt1c_cid}x" != "x"]?Set(CALLERID(num)=${pt1c_cid}))'.PHP_EOL."\t".
                'same => n,GosubIf($["${DIALPLAN_EXISTS(${CONTEXT}-custom,${EXTEN},1)}" == "1"]?${CONTEXT}-custom,${EXTEN},1)'.PHP_EOL."\t".
                'same => n,ExecIf($["${SRC_QUEUE}x" != "x"]?Queue(${SRC_QUEUE},kT,,,300,,,originate-answer))'.PHP_EOL.PHP_EOL.

                '[originate-create-chan] '.PHP_EOL.
                'exten => _.!,1,ExecIf($["${PT1C_SIP_HEADER}x" != "x"]?Set(PJSIP_HEADER(add,${CUT(PT1C_SIP_HEADER,:,1)})=${CUT(PT1C_SIP_HEADER,:,2)})) '.PHP_EOL."\t".
                'same => n,Set(__PT1C_SIP_HEADER=${UNDEFINED}) '.PHP_EOL."\t".
                'same => n,return'.PHP_EOL.PHP_EOL.

                '[originate-answer]'.PHP_EOL.
                'exten => s,1,Set(IS_ORGNT=${EMPTY})'.PHP_EOL."\t".
                'same => n,Set(orign_chan=${CHANNEL})'.PHP_EOL."\t".
                'same => n,ExecIf($[ "${CHANNEL:0:5}" == "Local" ]?Set(pl=${IF($["${CHANNEL:-1}" == "1"]?2:1)}))'.PHP_EOL."\t".
                'same => n,ExecIf($[ "${CHANNEL:0:5}" == "Local" ]?Set(orign_chan=${IMPORT(${CUT(CHANNEL,\;,1)}\;${pl},DIALEDPEERNAME)}))'.PHP_EOL."\t".
                'same => n,Set(MASTER_CHANNEL(ORIGINATE_SRC_CHANNEL)=${orign_chan})'.PHP_EOL."\t".
                $playbackConf.
                'same => n,return'.PHP_EOL.PHP_EOL.

                '[originate-wait]'.PHP_EOL.
                'exten => failed,1,Hangup()'.PHP_EOL.
                    'exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,ExecIf($[ "${EXTEN}" != "h" ]?ChannelRedirect(${ORIGINATE_SRC_CHANNEL},all_peers,${ORIGINATE_DST_EXTEN},1))'.PHP_EOL."\t".
                    'same => n,Hangup()'.PHP_EOL;

        return $conf;
    }

    /**
     * Генератор modules.conf
     *
     * @return string
     */
    public function generateModulesConf(): string
    {
        return 'load => app_channelredirect.so'.PHP_EOL;
    }

    /**
     * Добавление задач в crond.
     *
     * @param array $tasks
     */
    public function createCronTasks(array &$tasks): void
    {
        if ( ! is_array($tasks)) {
            return;
        }
        $workerPath   = "{$this->moduleDir}/bin/Dialer.php";
        $phpPath      = Util::which('php');
        $tasks[]      = "*/1 * * * * {$phpPath} -f {$workerPath} > /dev/null 2> /dev/null\n";
    }

    /**
     * Process some actions after module enable
     *
     * @return void
     */
    public function onAfterModuleEnable(): void
    {
        $this->generateCustomConfig();
        PBX::dialplanReload();
    }

    /**
     * Process module disable request
     *
     * @return bool
     */
    public function onBeforeModuleDisable(): bool
    {
        $this->generateCustomConfig(false);
        PBX::dialplanReload();
        return true;
    }

    /**
     * Правка файла queues.conf
     */
    public function generateCustomConfig($enable = true):void
    {
        $files = $this->getCustomQueueConf();
        if(!$files){
            return;
        }
        $data = base64_decode($files->content);
        $parser = new ParserIni('; ModuleQueueCallBack');
        $parser->parse($data);
        /** @var ModuleQueueList $queueData */
        $qList = ModuleQueueList::find();
        foreach ($qList as $queueData){
            /** @var CallQueues $q */
            $q = CallQueues::findFirst($queueData->idQueue);
            if(!$q){
                continue;
            }
            if($enable){
                $parser->set($q->uniqid, 'queue-thankyou', '', '', '=', '+');
                $parser->set($q->uniqid, 'context', self::QUEUE_CONTEXT, '', '=', '+');
            }else{
                $parser->unSet($q->uniqid, '+');
            }
            unset($q);
        }
        $files->mode = 'append';
        $files->content = base64_encode($parser->getResult());
        $files->save();
    }

    private function getCustomQueueConf():CustomFiles
    {
        $filename = $this->config->path('asterisk.astetcdir').'/queues.conf';
        $filter = [
            "filepath=:filepath:",
            'bind' => [
                'filepath'  => $filename
            ]
        ];
        /** @var CustomFiles $files */
        return CustomFiles::findFirst($filter);
    }

    /**
     * Возвращает путь к аудио файлу.
     * @param $sound_file_id
     * @return string
     */
    public function getSoundFileByID($sound_file_id):string{
        $filename = '';
        /** @var SoundFiles $fileData */
        $fileData = SoundFiles::findFirst("id='$sound_file_id'");
        if($fileData){
            $filename = Util::trimExtensionForFile($fileData->path);
        }
        return $filename;
    }
}