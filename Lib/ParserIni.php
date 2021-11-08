<?php

namespace Modules\ModuleQueueCallBack\Lib;

class ParserIni {
    private string $separator;
    private bool $separatorExists;
    private int $separatorFound = 0;
    protected array $sections = [];
    protected array $additionalSections = [];
    protected string $head 	 = '';
    protected string $footer = '';
    public const ADD_SECTION = 'additionalSections';
    public const SECTION     = 'sections';

    /**
     * ParserIni constructor.
     * @param string $separator
     */
    public function __construct(string $separator = '')
    {
        $this->separator = $separator;
        $this->separatorExists = !empty($separator);
    }

    public function parse(string $data):void{
        $rows = explode(PHP_EOL, $data);
        $this->sections = [];
        $section        = '';
        $key            = '';
        $sectionValName = '';

        $this->separatorFound  = 0;
        foreach($rows as $line) {
            if($this->parseSeparator($line)){
                continue;
            }
            if(preg_match('/^\s*(;.*)?$/', $line)) {
                $this->parseComment($section, $key, $line);
            } elseif(preg_match('/\[(.*)]/', $line, $match)) {
                $this->parseSection($line, $match, $section, $sectionValName, $key);
            } elseif(preg_match('/^\s*(.*?)\s*((=>)|=)\s*(.*?)\s*(;.*)?$/', $line, $match)) {
                $key   = $match[1]??'';
                $value = $match[4]??'';
                $this->$sectionValName[$section][$key]= [
                    'data' => $line,
                    'value' => $value
                ];
            }
        }
    }

    /**
     * Ищет в исходном тексте раздилитель.
     * @param $line
     * @return bool
     */
    private function parseSeparator($line):bool{
        $needContinue = false;
        if($this->separatorExists){
            if(strpos($line, $this->separator)===0) {
                $this->separatorFound++;
                if($this->separatorFound === 1){
                    $this->head .= $line.PHP_EOL;
                }
                $needContinue = true;
            }
            if($this->separatorFound === 0){
                $this->head .= $line.PHP_EOL;
                $needContinue = true;
            }elseif($this->separatorFound >=2){
                $this->footer .= $line.PHP_EOL;
                $needContinue = true;
            }
        }

        return $needContinue;
    }

    /**
     * Разбор строки с именем секции.
     * @param $line
     * @param $match
     * @param $section
     * @param $sectionValName
     * @param $key
     */
    public function parseSection($line, $match, &$section, &$sectionValName, &$key):void{
        if(preg_match('/\[(.*)]\s*\(\+\)/',$line)){
            // Это дополнение к секции.
            $sectionValName = self::ADD_SECTION;
        }else{
            $sectionValName = self::SECTION;
        }
        $section = $match[1];
        if(!(array_key_exists($section, $this->$sectionValName)) ){
            $this->$sectionValName[$section] = [];
            if($sectionValName === self::ADD_SECTION){
                $this->$sectionValName[$section]['postfix'] = '+';
            }
            $key='';
        }
    }

    /**
     * Парсер комментария
     * @param $section
     * @param $key
     * @param $line
     */
    private function parseComment($section, $key, $line):void{
        if($section===''){
            // Комментарий перед секцией
            if(array_key_exists('comment', $this->sections)){
                $this->sections['comment'].=$line;
            }else{
                $this->sections['comment']=$line;
            }
        }elseif($key===''){
            // комментарий после секции
            if(array_key_exists($section, $this->sections) && array_key_exists('comment',$this->sections[$section])){
                $this->sections[$section]['comment'].=$line;
            }else{
                $this->sections[$section]['comment']=$line;
            }
        }
    }

	// получение значения параметра для секции
    public function get($section, $_key) {
        if(!is_array($this->sections) || !array_key_exists($section, $this->sections)){
	        return '';
        } 
        $arr_loc_keys = $this->sections[$section];
		if( !(is_array($arr_loc_keys)  && array_key_exists($_key, $this->sections[$section]) )) {
	        return '';
		}
	    return $arr_loc_keys[$_key]['value'];
    }

    /**
     * Удаление настройки.
     * @param        $section
     * @param string $postfix
     */
    public function unSet($section, $postfix=''):void
    {
        if($postfix === '+'){
            $sectionValName = self::ADD_SECTION ;
        }else{
            $sectionValName = self::SECTION;
        }
        unset($this->$sectionValName[$section]);
        if($this->separatorExists && empty($this->additionalSections) && empty($this->sections)){
            $this->head   = rtrim(str_replace($this->separator.' ; START'.PHP_EOL,'', $this->head));
            $this->footer = ltrim(str_replace($this->separator.' ; END'.PHP_EOL,'', $this->footer));
        }
    }

	// Установка значения параметра для секции
    public function set($section, $_key, $value, $comment='',$separator='=', $postfix=''):void {
        if($this->separatorExists && empty($this->additionalSections) && empty($this->sections)){
            $this->head .= $this->separator.' ; START'.PHP_EOL;
            $this->footer = $this->separator.' ; END'.$this->footer.PHP_EOL;
        }
        if($comment!==''){
            $comment='; '.$comment;
        }
        if($postfix === '+'){
            $sectionValName = self::ADD_SECTION ;
        }else{
            $sectionValName = self::SECTION;
        }
        $this->$sectionValName[$section][$_key]['value'] = $value;
        $this->$sectionValName[$section][$_key]['data']  = $_key.$separator.$value."$comment\n";
        if($postfix!==''){
			$this->$sectionValName[$section]['postfix']  = $postfix;
        }

    }

    public function getResult():string {
        $result = $this->head;
        $arr = [
            self::SECTION,
            self::ADD_SECTION
        ];
        foreach ($arr as $name){
            foreach($this->$name as $index => $section) {
                if(!is_array($section)) {
                    $result .=$section . PHP_EOL;
                    continue;
                }
                if(array_key_exists('postfix', $section) ){
                    $result .="[$index](".$section['postfix'].")" . PHP_EOL;
                }else{
                    $result .="[$index]" . PHP_EOL;
                }
                // Комментарии после определения секции
                if(array_key_exists('comment', $section)){
                    $result .=$section['comment'] . PHP_EOL;
                }
                // Обход ключей секции
                foreach($section as $vr_index  => $_key) {
                    if($vr_index==='postfix'){
                        continue;
                    }
                    $result .= trim($_key['data']) . PHP_EOL;
                }
            }
        }
        $result .= $this->footer;

		return $result;
    }
    
}