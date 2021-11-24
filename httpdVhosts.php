<?php
// 获取host和vhosts配置地址
$config_path = __DIR__.DIRECTORY_SEPARATOR."config_txt".DIRECTORY_SEPARATOR."config_path.json";
$paths = file_get_contents($config_path);
$paths = json_decode($paths,true);
if(!array_key_exists("hosts_path",$paths)){
    $paths['hosts_path'] = "C:/Windows/System32/drivers/etc/hosts";
}
if(!array_key_exists("vhost_path",$paths)){
    $paths['vhost_path'] = "D:/xampp/apache/conf/extra/httpd-vhosts.conf";
}
// 设置hosts的配置据对地址
$hosts_path = $paths['hosts_path'];
// 设置xampp的Vhosts配置绝对地址
$vhost_path = $paths['vhost_path'];

// 检测是否存在配置文件,存在读取内容，不存在新建
$config_json_path = __DIR__ . DIRECTORY_SEPARATOR."config_txt".DIRECTORY_SEPARATOR."httpd-vhosts.json";
if(!is_file($config_json_path)){
	file_put_contents($config_json_path, "");
}
$config_data = json_decode(file_get_contents($config_json_path),true);
if(is_null($config_data)){
	$config_data = array();
}
// 判断是什么请求，调用不同的方法执行
if($_GET['type'] == "get_vhosts_table"){
	// 获取列表
	getVhostsTableData($config_data);
}else if($_GET['type'] == "add_vhosts"){
	// 新增vhosts配置
	addVhostsData($config_json_path,$config_data,$vhost_path,$hosts_path);
}else if($_GET['type'] == "del"){
	// 删除某个vhosts
	del($hosts_path,$vhost_path,$config_data,$config_json_path);
}else if($_GET['type'] == "get_path_list"){
    // 获取配置路径
    getPathList($paths);
}else if($_GET['type'] == "up_config_path"){
    // 更新配置路径
    upConfigPath($config_path );
}
/**
 * 更新配置路径
 *
 * @param $config_path string 存储配置路径
 */
function upConfigPath($config_path ){
    $paths = $_POST;
	if(!file_exists($paths['host'])){
		mesagess(200,"host文件不存在");
	}else if(!file_exists($paths['Vhost'])){
		mesagess(200,"vhosts文件不存在");
	}
	$config['hosts_path'] = $paths['host'];
	$config['vhost_path'] = $paths['Vhost'];
	file_put_contents($config_path,json_encode($config,256));
	mesagess(200,"更新配置路径成功！");
}
/**
 * 获取路径
 *
 * @param $paths array 配置数据集合
 */
function getPathList($paths){
    mesagess(200,"获取成功",$paths);
}
/**
 * 删除vhosts配置
 * 
 * @param  string $hosts_path  hosts配置路径
 * @param  string $vhost_path  vhost_path 配置路径
 * @param  array $config_data json配置数据
 */
function del($hosts_path,$vhost_path,$config_data,$config_json_path){
	$param = $_POST;
	$domain_name = $param['domain_name'];
	$new_config_data = array();
	// 删除json配置
	foreach ($config_data as $key => $value) {
		if($value['domain_name'] == $domain_name){
			continue;
		}else{
			$new_config_data[] = $value;
		}
	}
	$config_data = $new_config_data;
	file_put_contents($config_json_path, json_encode($config_data,256));
	// 删除hosts
	$host_txt = file_get_contents($hosts_path);
	$host_txt = str_replace("\n"."127.0.0.1 ".$param['domain_name'],"",$host_txt);
	file_put_contents($hosts_path, $host_txt);
	// 重新生成vhosts
	$httpd_vhosts_txt = file_get_contents("./config_txt/httpd-vhosts.conf");
	setVhosts($httpd_vhosts_txt,$config_data,$vhost_path);
}
/**
 * 把新的参数写入到对应的配置中
 * @param string $config_json_path json配置路径
 * @param array $config_data 当前配置数据集合
 * @param string $vhost_path vhosts配置路径
 * @param string $hosts_path host配置路径
 */
function addVhostsData($config_json_path,$config_data,$vhost_path,$hosts_path){
	$param = $_POST;
	$config_data[] = [
		'domain_name'=>$param['domain_name'],
		'port'=>$param['port'],
		'project_path'=>str_replace("\\","/",$param['project_path']),
		'creation_time'=>date("Y-m-d H:i:s")
	];
	// 把配置新增内容写入到配置文件中
	file_put_contents($config_json_path, json_encode($config_data,256));
	// 读取host
    $host_txt = file_get_contents($hosts_path);
    // 清理host配置，在添加
    $host_txt = str_replace("\n"."127.0.0.1 ".$param['domain_name'],"",$host_txt);
    // 新增hosts记录
	$host_txt = $host_txt."\n"."127.0.0.1 ".$param['domain_name'];
	file_put_contents($hosts_path,$host_txt);
	$httpd_vhosts_txt = file_get_contents("./config_txt/httpd-vhosts.conf");
	setVhosts($httpd_vhosts_txt,$config_data,$vhost_path);
}
/**
 * 写入Vhosts配置
 * @param string $httpd_vhosts_txt vhosts默认配置
 * @param array $config_data 配置数据集合
 * @param string $vhost_path 配置路径
 */
function setVhosts($httpd_vhosts_txt,$config_data,$vhost_path){
	foreach ($config_data as $key => $value) {
		$txt =
'<VirtualHost *:'.$value['port'].'>
	ServerAdmin '.$value['domain_name'].'
    DocumentRoot "'.$value['project_path'].'"
    ServerName '.$value['domain_name'].'
    ErrorLog "logs/'.$value['domain_name'].'-error.log"
    CustomLog "logs/'.$value['domain_name'].'-access.log" common
</VirtualHost>';
		$httpd_vhosts_txt = $httpd_vhosts_txt."\n".$txt;
	}
	file_put_contents($vhost_path,$httpd_vhosts_txt);
	mesagess(200,"操作成功,请重新启动xampp下的apache");
}

// 读取表格内的信息
function getVhostsTableData($config_data){
	$other['count'] = count($config_data);
	mesagess(200,"请求成功",$config_data,$other);
}
/**
 * 整合返回json操作
 * @param  int 	   $code     [返回值状态]
 * @param  string  $mesagess [返回值提示]
 * @param  array   $data     [返回值数据]
 * @param  array   $other    [其他返回值数据，会合并到data中]
 * @return string             [返回json值到前端]
 */
function mesagess($code=200,$mesagess= "操作成功",$data= array(),$other = array()){
	http_response_code($code);
	$res = ['code'=>$code,'data'=>$data,'mesagess'=>$mesagess];
	$data = array_merge($res,$other);
	echo json_encode($data,256);
	exit();
}
