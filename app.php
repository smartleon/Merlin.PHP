<?php
set_include_path(get_include_path() . PATH_SEPARATOR . 'library/phpseclib');
require 'library/MainFunction.php';
require 'Net/SSH2.php';
require_once 'library/Requests.php';
Requests::register_autoloader();

$merlin_php = new merlin_php();
// remote();
// exit;

$o = _GET("fun",'unknow');
$q = _GET('q','unknow');


if ($o == 'unknow') {
	// 無輸入，退出
	$merlin_php -> _review("no input");
	exit;
}

if ($q == 'unknow') {
	$o();
} else {
	$o($q);
}

ChmodCheck();

function ChmodCheck() {
	shell_exec("chmod +x /opt/share/www/bin/autoupdate/update.sh");
	shell_exec("chmod +x /opt/share/www/bin/autoupdate/check.sh");
	shell_exec("chmod +x /opt/share/www/bin/autoupdate/reinstall.sh");
	shell_exec("chmod +x /opt/share/www/bin/script/ssconfig.sh");
	shell_exec("chmod +x /opt/share/www/bin/script/netspeed.sh");
}


function ConnectTest($way){
	$o = array();
	$lan = trim(shell_exec('nvram get lan_ipaddr_rt'));
	switch ($way) {
		case 'baidu':
			$o = array('延遲' => shell_exec("curl -o /dev/null -s -w %{time_total} --connect-timeout 6 https://www.baidu.com"));
			break;
		case 'google':
			$o = array('延遲' => shell_exec("curl -o /dev/null -s -w %{time_total} --connect-timeout 10 --socks5-hostname ".$lan.":23456 https://www.google.com/"));
			break;
	}

	echo json_encode($o);
}

function IP ($isJSON = true) {
	$headers = array('Accept' => 'application/json');
	$request = Requests::get('http://ipinfo.io', $headers);
	if ($isJSON == true) {
		$return = $request->body;
	} else {
		$return = json_decode($request->body);
	}
	echo $return;
}

function SSConfig () {
	// $mode = trim(shell_exec('cat ss-mode'));
	$number = trim(shell_exec('nvram get ss_mode'));
	if ($number == 3) {
		$mode = 'game';
	}
	if ($number == 4) {
		$mode = 'v2';
	}
	switch ($mode) {
		case 'v2':
			$c=shell_exec("cat /koolshare/ss/koolgame/ss.json");
			$Config = json_decode($c);
			$Config_Output = array(
				'server' => $Config->server,
				'server_port' => $Config->server_port,
				);
			echo json_encode($Config_Output);
			break;
		case 'game':
			$c=shell_exec("cat /koolshare/ss/game/ss.json");
			$Config = json_decode($c);
			$Config_Output = array(
				'server' => $Config->server,
				'server_port' => $Config->server_port,
				'PID' => shell_exec("cat /var/run/shadowsocks.pid"),
				);
			echo json_encode($Config_Output);
			break;
	}

	// dump($Config);

}

function SSPID () {
	$PID = array('PID' => shell_exec("cat /var/run/shadowsocks.pid"));
	echo json_encode($PID);
}

function SwitchMode($mode) {
	// echo $mode;
	echo '<pre>';
	system("/koolshare/ss/stop.sh");
	sleep(1);
	switch ($mode) {
		case 'game':
			system('/koolshare/ss/game/start.sh');
			break;
		case 'v2':
			system('/koolshare/ss/koolgame/start.sh');
			break;
		default:
			break;
	}
	shell_exec('/opt/share/www/bin/script/ssconfig.sh');
	system('echo '.$mode.' > ss-mode');
	echo '</pre>';
}

function FastReboot() {
	$mode = trim(shell_exec('cat ss-mode'));
	switch ($mode) {
		case 'v2':
			$PID_P_O = shell_exec('cat /tmp/var/pdu.pid');
			$PID_G_O = shell_exec("cat /tmp/var/koolgame.pid");
			shell_exec('kill -9 '.trim($PID_G_O));
			shell_exec("start-stop-daemon -S -q -b -m -p /tmp/var/koolgame.pid -x /koolshare/ss/koolgame/koolgame -- -c /koolshare/ss/koolgame/ss.json");
			$PID_G = shell_exec("cat /tmp/var/koolgame.pid");
			$PID_P = shell_exec('cat /tmp/var/pdu.pid');
			$json = array('Mode' => $mode,'PDU PID' => trim($PID_P_O).' -> '.trim($PID_P),'koolgame PID' => trim($PID_G_O).' -> '.trim($PID_G));
			break;
		case 'game':
			// $c=shell_exec('ps|grep ss-redir');
			$PID_R_O=shell_exec('cat /var/run/shadowsocks.pid');
			shell_exec('kill -9 '.$PID_R_O);
			shell_exec('ss-redir -b 0.0.0.0 -u -c /koolshare/ss/game/ss.json -f /var/run/shadowsocks.pid');
			$PID_R=shell_exec('cat /var/run/shadowsocks.pid');
			$PID_L_O = shell_exec('cat /var/run/sslocal1.pid');
			shell_exec('kill -9 '.$PID_L_O);
			shell_exec('ss-local -b 0.0.0.0 -l 23456 -c /koolshare/ss/game/ss.json -u -f /var/run/sslocal1.pid');
			$PID_L = shell_exec('cat /var/run/sslocal1.pid');

			$json = array('Mode' => $mode,'ss-redir PID' => trim($PID_R_O).' -> '.$PID_R, 'ss-local PID' => trim($PID_R_O).' -> '.$PID_L);
			break;
	}
	shell_exec('service restart_dnsmasq');
	echo json_encode($json);

}


function GetExec($command) {
	system($command);
}

function SystemNetwork() {
	echo "<pre>";
	echo "service restart_dnsmasq: ";
	system('service restart_dnsmasq');
	echo "service restart_wan: ";
	system('service restart_wan');
	echo "</pre>";
}

function SystemCommand($command) {
	echo '<pre>';
	system($command);
	echo '</pre>';
}

function flush_buffers()
{
    ob_end_flush();
    ob_flush();
    flush();
    ob_start('ob_callback');
}

function ob_callback($buffer)
{
    return $buffer . str_repeat(' ', max(0, 4097 - strlen($buffer)));
}

/**
* merlin PHP 信息交互類
*/
class merlin_php
{

	function __construct() {
		# code...
	}

	function _review($data,$title = '錯誤') {
		dump($data,$title);
		exit;
	}

}


/**
* SS 配置
*/
class ssconfig extends merlin_php
{
	private $config;
	public $config_total;
	public $config_now;
	private $config_files = 'config/ss-configs.json';
	function __construct()
	{

		if (!file_exists($this->config_files)) {
			// 初始化配置
			shell_exec('/opt/share/www/bin/script/ssconfig.sh');
		}

		$this->config = json_decode(file_get_contents($this->config_files));
		$this->config_total = $this->config->Max;
	}

	function getAllConfig() {
		return $this->config;
	}

	function config($id) {
		if ($id > $this->config_total) {
			$id = $this->config_total;
		}
		$this->config_now = $this->config->$id;

		foreach ($this->config as $key => $value) {
			@$this->config->$key->working = 0;
		}

		$this->config->$id->working = 1;
		shell_exec('dbus set ss_basic_server="'.$this->config->$id->server.'"');
		shell_exec('dbus set ss_basic_port="'.$this->config->$id->server_port.'"');
		shell_exec('dbus set ss_basic_password="'.$this->config->$id->password.'"');
		shell_exec('dbus set ss_basic_method="'.$this->config->$id->method.'"');
		shell_exec('dbus set ss_basic_rss_protocol="'.$this->config->$id->protocol.'"');
		shell_exec('dbus set ss_basic_rss_obfs="'.$this->config->$id->obfs.'"');
		shell_exec('dbus set ss_basic_use_rss="0"');
		// shell_exec('dbus set shadowsocks_server_ip="'.$this->config->$id->server.'"');

		return $this->config->$id;
	}

	function rebuild() {
		shell_exec('/opt/share/www/bin/script/ssconfig.sh');
	}

	function get_ss_basic() {
		$json = array(
			'ss_basic_server' => shell_exec('dbus get ss_basic_server'),
			'ss_basic_port' => shell_exec('dbus get ss_basic_port'),
			// 'ss_basic_password' => shell_exec('dbus get ss_basic_password'),
			'ss_basic_method' => shell_exec('dbus get ss_basic_method'),
			'shadowsocks_server_ip' => shell_exec('dbus get shadowsocks_server_ip')

		);
		return $json;
	}

}

function ss_all_config() {
	$ssconfig = new ssconfig();
	echo json_encode($ssconfig->getAllConfig());
}

function ss_config($id = 0) {
	$ssconfig = new ssconfig();
	if ($id == 0) {
		echo json_encode($ssconfig->getAllConfig());
	} else {
		echo json_encode($ssconfig->config($id));
	}
}

function ss_rebuild() {
	$ssconfig = new ssconfig();
	$ssconfig->rebuild();
	$json = array(
		'config' => 'done'
		);
	echo json_encode($json);
}

function ss_basic() {
	$ssconfig = new ssconfig();
	echo json_encode($ssconfig->get_ss_basic());
}

/**
* 遠程工具
*/
class remote extends merlin_php
{
	public $config;
	public $config_total;
	public $config_now;
	public $ssh;
	private $config_files = 'config/config.json';
	private $config_template = 'config/config.template.json';
	private $dev = true;

	function __construct() {

		if (!file_exists($this->config_files)) {
			// 初始化配置模板
			echo '初始化配置模板';
			$d = file_get_contents($this->config_template);
			file_put_contents($this->config_files, $d);
			$this->_review($d, 'Config build form template');
			// exit;
		}

		// 讀取配置文件
		$this->config = json_decode(file_get_contents($this->config_files));
		$this->config_total = count((array)$this->config);

	}

	function getAllConfig() {
		return $this->config;
	}

	function config($id) {
		if ($id > $this->config_total) {
			$id = $this->config_total;
		}
		$this->config_now = $this->config->$id;

		$this->ssh = new Net_SSH2($this->config_now->server);
		if (!$this->ssh->login($this->config_now->name, $this->config_now->pass)) {
		    $this->_review($this->config_now, 'Login Failed');
		}

		return $this;
	}

	function command($command = 'whoami') {
		return $this->ssh->exec($command);
	}

	function clist() {
		foreach ($this->config as $k => $c) {
			$arr[$k] = $c->server;
		}

		return $arr;
	}

	function setActive($id) {

		foreach ($this->config as $key => $value) {
			$this->config->$key->active = false;
		}

		$this->config->$id->active = true;
		// dump(json_encode($this->config));
		return file_put_contents($this->config_files,json_encode($this->config));
	}

}

function remote($command = 'ls -a') {
	$remote = new remote();
	$o = $remote->config(1)->command($command);
	echo $o;
}

function remote_clist() {
	$remote = new remote();
	echo json_encode($remote->clist());
}

function remoteConfig() {
	$remote = new remote();
	echo json_encode($remote->getAllConfig());
}

function setActive($id) {
	$remote = new remote();
	echo json_encode($remote->setActive($id));
}

function json_update($json_file,$name,$var) {
	//todo: 未處理傳遞多個參數
	$j = json_decode(file_get_contents($json_file));
	$j->$name = $var;
	file_put_contents($json_file,json_encode($j));
	echo json_encode($j);
}








