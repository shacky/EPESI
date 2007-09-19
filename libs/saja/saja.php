<?php
/*
	Example Usage:
	page url: http://www.yoursite.com/index.php
	saja url: http://www.yoursite.com/path/to/saja.php.
	
	For full documentation see: http://saja.sourceforge.net/
	
	---------- <index.php> -----------
	<?php
	include($_SERVER['DOCUMENT_ROOT'].'/path/to/saja.php');
	$saja = new saja;
	$saja->set_path('/path/to/');
	$saja->secure_http(); //uses session variables to encrypt HTTP data (optional)
	? >
	<div id=outputDiv>Some Text</div>
	<input type=text id=myInput>
	<button id=myButton onclick="<%=$saja->run("MyPhpFunction(myInput:value)->outputDiv:innerHTML");%>">do something</button>
	---------- </index.php> -----------

	---------- <saja.functions.php> -----------
	<?php
	function MyPhpFunction($myInput)
	{
		echo "You typed: [$myInput]";
		$saja = new saja;
		$saja->hide('myInput');
		$saja->text('Done!','myButton:innerHTML');
		$saja->alert("done!");
	}
	? >
	---------- </saja.functions.php> -----------
*/
class Saja {
	
	//configurable vars
	private $saja_path = '';								//default SAJA path - this can be set so you never have to call set_path() again
	private $saja_process_file = 'saja.functions.php';		//default process file to use
	private $saja_process_path = '';						//relative or full path to the directory that contains your process files (functions) i.e. "../myfunctions/", "/www/apache/htdocs/public/", etc.
	private $saja_process_class = 'myFunctions';			//default classname to use
	
	//leave these vars alone
	private $functionPadding = 15;							//pad functions names having less than this many characters in their name
	private $actions = array();
	private $salt;
	public $http_key;
	private $argument_separator = '>>>saja_arg<<';			//separator for function arguments
	
	function __construct()
	{
		if(!session_id())
			session_start();
		$this->salt();
	}
	
	public function clear_state(){
		unset($_SESSION['SAJA_SALT']);
		unset($_SESSION['SAJA_HTTP_KEY']);
		$this->salt = $this->http_key = null;
		$this->salt();
	}
	
	public function salt(){
		$this->salt = isset($_SESSION['SAJA_SALT']) ? $_SESSION['SAJA_SALT'] : $this->generate_key();
		$_SESSION['SAJA_SALT'] = $this->salt;
	}
	
	public function set_path($path)
	{
		$this->saja_path = $path;
	}
	
	public function secure_http()
	{
		$this->http_key = $_SESSION['SAJA_HTTP_KEY'] ? $_SESSION['SAJA_HTTP_KEY'] : $this->generate_key();
		$_SESSION['SAJA_HTTP_KEY'] = $this->http_key;
	}
	
	public function clear_secure_http()
	{
		$this->http_key = null;
		unset($_SESSION['SAJA_HTTP_KEY']);
	}
	
	public function generate_key()
	{
		return md5(uniqid(rand()));
	}
	
	public function saja_js()
	{
		$js  = '<script type="text/javascript">var SAJA_PATH="'.$this->saja_path.'"; var SAJA_HTTP_KEY="'.$this->http_key.'"</script>'."\n";
		$js .= '<script type="text/javascript" src="'.$this->saja_path.'saja.js"></script>'."\n";;
		return $js;
	}
	

	public function saja_status($style='', $string='Working...')
	{
		return "<span id=\"sajaStatus\" style=\"visibility:hidden;$style\">".htmlentities($string)."</span>";	
	}

	public function hasActions()
	{
		return (count($this->actions) > 0);
	}
	
	//example: set_process_path('myFunctions/');
	public function set_process_path($fpath)
	{
		$this->saja_process_path = $fpath;
	}
	
	public function set_process_class($name)
	{
		$this->saja_process_class = $name;
	}
	
	public function get_process_class()
	{
		return $this->saja_process_class;
	}
	
	//exaple: set_process_file('myOtherFunctions.php');
	public function set_process_file($filename)
	{
		$this->saja_process_file = $filename;
	}
	
	public function get_process_file()
	{
		return $this->saja_process_path . $this->saja_process_file;
	}

	public function run($commands, $process_file=null)
	{
		if(!$this->http_key)
			$this->clear_secure_http();
		
		if(!$process_file)
			$process_file = $this->get_process_file();
		return $this->ParseCommands($commands, $process_file);
	}

	private function ParseCommands($commands, $process_file)
	{
		$commands = $this->texplode(';', $commands);
		$all_commands = '';
		$request_id = '';
		foreach($commands as $command)
		{
			$inputType = '';
            $targets = '';
			
			$tmp = $this->texplode('->', $command);
			if(isset($tmp[0]))
				$functions = $tmp[0];
			if(isset($tmp[1]))
				$targets = $tmp[1];

			if(strstr($functions, '('))
			{
				$action = '';
				$target = '';
                $targetProperty = '';
                
				$inputArray = explode('(', $functions, 2);
				list($function, $args) = $inputArray;
				$args = substr($args, 0, -1);
				
				$tmp = $this->texplode(',', $targets);
				if(isset($tmp[0]))
					$target = $tmp[0];
				if(isset($tmp[1]))
					$action = $tmp[1];
				
				$tmp = $this->texplode(':', $target);
				if(isset($tmp[0]))
					$targetId = $tmp[0];
				if(isset($tmp[1]))
					$targetProperty = $tmp[1];
				
				if(!$action)
					$action = 'r';
				if(!$targetProperty)
					$targetProperty = 'innerHTML';
				if(!$targets)
					$action = $targetProperty = $targetId = '';
				
				if($function)
				{
					$request_id = md5($process_file.$function.$this->salt);
					$_SESSION['SAJA_PROCESS']['REQUESTS'][$request_id] = array(
						'FUNCTION' => $function,
						'PROCESS_FILE' => $process_file,
						'CLASS' => $this->get_process_class()
					);
					
					$session_id = session_id();
					$all_commands .= "saja.run('".$this->parseArgs($args, 'PHP')."','$targetId','$action','$targetProperty','$session_id','$request_id');";
				}
			}
		}
		return $all_commands;
	}

	private function parseArgs($args, $getType)
	{
		$i = 0;
		$inner = '';
		$args = $this->texplode(',',$args);
		if($args)
		foreach($args as $arg)
		{
			$id = $property = '';
			
			//shortcut for element:property syntax
			if(strstr($arg,':'))
			{
				$tmp = $this->texplode(':', $arg);
				if(isset($tmp[0]))
					$id = $tmp[0];
				if(isset($tmp[1]))
					$property = $tmp[1];
				$arg = '';
			}
			if($getType == 'PHP')
			{
				if($i) $inner .= $this->argument_separator;
				if($property)
					$inner .= "'+saja.Get('$id','$property')+'";
				else if($arg || is_numeric($arg))
					$inner .= "'+saja.Get($arg)+'";
				else
					$inner .= "'+saja.Get($id)+'";
			}
			$i++;
		}
		return $inner;
	}

	private function texplode($seperator, $str)
	{
		$vals = array();
		foreach(explode($seperator, $str) as $val){
			if(is_numeric($val)){
				$vals[] = $val;
			} else if(strlen($val) > 0){
				$vals[] = trim($val);
			}
		}	
		return $vals;
	}

	
################################################################################
#
#			SAJA RESPONSE FUNCTIONS
#
	//execute raw javascript code
	public function js($js)
	{
		$this->add_action($js);
	}
	
	//redirect the browser to a URL
	public function redirect($url='')
	{
		$this->add_action("window.location = '$url'");
	}
	
	//return a javascript alert
	public function alert($txt)
	{
		$this->add_action("alert('".str_replace('\'', '\\\'', $txt)."')");
	}
		
	//adds a new saja action to the queue
	public function exec($action)
	{
		$this->add_action($this->run($action));
	}

	//used for placing complex / long text into an element
	public function text($content, $target)
	{
		$x = $this->texplode(',', $target);
		if(!isset($x[1])) $x[1] = 'r';
		list($target, $action) = $x;
		$x = $this->texplode(':', $target);
		if(!isset($x[1])) $x[1] = 'innerHTML';
		list($targetId, $targetProperty) = $x;
		$action = 'saja.Put(decodeURIComponent'."('".rawurlencode(utf8_encode($content)) ."'),'$targetId','$action','$targetProperty')";
		//$action = "saja.Put('".str_replace('\'', '\\\'', $content)."','$targetId','$action','$targetProperty')";
		$this->add_action($action);
	}
	
	//hide an element
	public function hide($element)
	{
		$this->add_action("saja.Put('none','$element','r','style.display')");	
	}
	
	//show an element
	public function show($element)
	{
		$this->add_action("saja.Put('','$element','r','style.display')");
	}
	
	//set style for an element
	public function style($element, $styleString)
	{
		$this->add_action("saja.SetStyle('$element', '$styleString')");
	}
	
	//return response actions to javascript for execution
	public function send()
	{
		$ret = $this->get_actions();
		$this->actions = array();
		return $ret;
	}
	
	public function add_action($js){
		$this->actions[] = $js;
	}
	
	public function get_actions(){
		return ($this->hasActions() ? '<saja_split>' : '') . implode(';', $this->actions);
	}

################################################################################
#
#			REQUEST HANDLING
#

	public function runFunc($function, $args)
	{	
		//kill magic quotes
		if(get_magic_quotes_gpc()){
			$args = stripslashes($args);
		}
		
		//decode encrypted HTTP data if needed
		if(isset($_SESSION['SAJA_HTTP_KEY'])){
			$this->secure_http();
			$args = $this->rc4($this->http_key, utf8_decode(rawurldecode($args)));
		}
		
		$args = explode($this->argument_separator, $args, 100);//limited to 100 arguments for DNOS attack protection
		//echo 'args: '; print_r($args);
		for($i=0; $i<count($args); $i++){
				$args[$i] = $this->utf8_unserialize(rawurldecode($args[$i]));
		}

		if(method_exists($this, $function))
			echo call_user_func_array(array(&$this, $function), $args);
		else
			echo "ERROR: [$function] Not validated.";
	}
	
	private function utf8_unserialize($str){
		if(preg_match('/^a:[0-9]+:{s:[0-9]+:"/', $str)){
			$ret = array();
			$args = preg_split('/"?;?s:[0-9]+:"/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
			array_shift($args);
			$last = array_pop($args);
			$last = preg_replace('/";}$/', '', $last);
			$args[] = $last;
			for($i=0; $i<count($args); $i+=2){
				$ret[$args[$i]] = $args[$i+1];
			}
			return $ret;
		} else if(preg_match('/^a:[0-9]+:{i:[0-9]+;s:[0-9]+:"/', $str)){
			$args = preg_split('/"?;?i:[0-9]+;s:[0-9]+:"/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
			array_shift($args);
			$last = array_pop($args);
			$last = preg_replace('/";}$/', '', $last);
			$args[] = $last;
			return $args;
		} else {
			$args = preg_split('/^s:[0-9]+:"([\w\W]*?)";$/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
			return $args[1];
		}
	}

	//RC4 Encryption from http://sourceforge.net/projects/rc4crypt
	private function rc4($pwd, $data)
	{
		$cipher = '';
		$pwd_length = strlen($pwd);
		$data_length = strlen($data);
		for ($i = 0; $i < 256; $i++){
			$key[$i] = ord($pwd[$i % $pwd_length]);
			$box[$i] = $i;
		}
		for ($j = $i = 0; $i < 256; $i++){
			$j = ($j + $box[$i] + $key[$i]) % 256;
			$tmp = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}
		for ($a = $j = $i = 0; $i < $data_length; $i++){
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$tmp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp;
			$k = $box[(($box[$a] + $box[$j]) % 256)];
			$cipher .= chr(ord($data[$i]) ^ $k);
		}
		return $cipher;
	}
}
?>