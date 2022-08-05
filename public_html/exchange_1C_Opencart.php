<?php
//******************************************************************
//****Модуль интеграции Opencart и 1С Управление торговлей 10.3/11**
//**********************Версия 2.6.1********************************
//*********************site-with-1c.ru******************************
//**********************Copyright 2022******************************
//******************************************************************
//ini_set('display_errors', 0);
//ini_set('display_startup_errors', 0);
//ini_set('html_errors', 'off');
//error_reporting(E_ALL);
header('X-Accel-Buffering: no'); 
@ini_set('output_buffering', 'Off'); 
@ini_set('output_handler', ''); 
@ini_set('zlib.output_handler',''); 
@ini_set('zlib.output_compression', 'Off'); 
error_reporting  (E_ALL & ~E_NOTICE);
@ignore_user_abort(true);
@set_time_limit(6000);
//******************************************************************
//******************************************************************
$lincekey = 'rwPbcpz0';
//******************************************************************
//******************************************************************
//*********************Системые настройки***************************
define( 'STORE_ID', 0 );// ID магазина по умолчанию (используется для работы Opencart в режиме мультимагазинов)
define( 'VM_NDS_SHIP', 20 );//НДС на доставку
define( 'VM_CATALOG_IMAGE', 'catalog' );//каталог картинок
define( 'VM_CATALOG_IMAGE_ALL', 'image/catalog' );//каталог картинок полный путь
define( '_JEXEC', 1 );
define ( 'VM_ZIPSIZE', 1073741824 ); // Максимальный размер отправляемого архива в байтах (по умолчанию 1 гб) 


if(!defined('DS'))
{
   define('DS',DIRECTORY_SEPARATOR);
}
if(!defined('VERSION')){
   define('VERSION','2.3');
}

$directory = search_dir();
define ( 'JPATH_BASE', dirname ( __FILE__ ) ); //Путь к директории где установлен движок Opencart.
require_once ( JPATH_BASE .DS.'config.php' );
require_once ( JPATH_BASE .DS.'database.php');

// initialize the application Opencart
// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Config
$config = new Config();
$registry->set('config', $config);

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);
setVersionOpencart();

// Database
if (VERSION_OC15 == 0){
	if (!defined('DB_PORT')) {
		define( 'DB_PORT', '' );
	}
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);	
}else{
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
}
$registry->set('db', $db);
ob_start("fatal_error_handler");
set_error_handler('error_handler');
set_exception_handler('exception_handler');
//Функции
$directory_image = JPATH_BASE.DS.VM_CATALOG_IMAGE_ALL ;
if (!file_exists($directory_image)) {
	mkdir($directory_image, 0755);
}
$directory_temp = JPATH_BASE.DS."TEMP" ;
if (!file_exists($directory_temp)) {
	mkdir($directory_temp, 0755);
}

$TimeBefore = 0;
$FilenameUpload = '';
$posix = Posix::generatePosix();
$ThisPage = str_replace('/', '', str_replace(__DIR__, '',__FILE__));
$ThisPage = str_replace("\\", '', $ThisPage);
$CategoryArray = array ();
$ShopperGroupsArray = array();
$TovarIdFeatureArray = array();
$StopNameCreateSvoistvaArray = array('Производитель');
ReadSetting();
usleep(10000);

// Language
set_language_id();
$languages = array();
$query = $db->query("SELECT * FROM " . DB_PREFIX . "language"); 
foreach ($query->rows as $result) {
	$languages[$result['code']] = array(
		'language_id'	=> $result['language_id'],
		'name'		=> $result['name'],
		'code'		=> $result['code'],
		'directory'	=> $result['directory']
	);
}

function search_dir() {
	$dir_file = dirname(__FILE__);
	$dir_dir = dirname ( __DIR__ );
	$directory_public_html_1 = str_replace($dir_dir,"",$dir_file);
	$directory_public_html_2 = str_replace("/","",$directory_public_html_1);
	$directory_public_html_cc = stripcslashes($directory_public_html_2);
	return $directory_public_html_cc;
} 

function error_handler($code, $msg, $file, $line) {
	$allNameError = "Произошла ошибка $msg ($code)\n $file ($line)";
	write_log($allNameError);
	if (($code == E_ERROR) or ($code == E_WARNING) or ($code == E_USER_ERROR) or ($code == E_USER_NOTICE)){
		global $db;
		$query = "UPDATE " . DB_PREFIX . "status_exchange_1c SET status='stop', error='ERROR upload'";
		$db->query ($query);
	}
	
	$pos = strpos($allNameError, "mysql");
	if ($pos === false) {
		//не найдена ошибка mysql
	} else {
		if ((isset($_REQUEST ['mode'])) and ($_REQUEST ['mode'] == 'import') and (isset($_REQUEST ['filename']))){
			$filename = $_REQUEST ['filename'];
			$filename = getFileFromPath($filename);
			if (isset($filename)){
				$findXml = strpos($filename, '.xml');
				if ($findXml === false) {
					//false
				} else {
					global $db;
					$query = "UPDATE " . DB_PREFIX . "status_exchange_1c SET status='stop', error='ERROR upload: ".$allNameError."' WHERE filename='".$filename."'";
					$db->query ($query);
					print("failure\n $allNameError");
					exit();
				}
			}
		}
	}
	return;
}

function exception_handler($exception) {
    $trace = $exception->getTrace();
	$msg = $exception->getMessage();
	$file = $trace[0]['file'];
	$line = $trace[0]['line'];
	$allNameError = "Произошла ошибка $msg \n $file ($line)";
	write_log($allNameError);
	return;
}

function fatal_error_handler($buffer) {
  if (preg_match("|(Fatal error</b>:)(.+)(<br)|", $buffer, $regs) ) {
  	 write_log($buffer);
  }
  return $buffer;
}

register_shutdown_function('shutdown');
function shutdown() {

	$connection_status = 'UNKNOWN';
	$connection_status_id = connection_status();
	switch ((string)$connection_status_id) {
		case '0':
			$connection_status = 'NORMAL';
		break;
		case '1':
			$connection_status = 'ABORTED';
		break;
		case '2':
			$connection_status = 'TIMEOUT';
		break;
		case '3':
			$connection_status = 'ABORTED and TIMEOUT';
		break;
		case 0:
			$connection_status = 'NORMAL';
		break;
		case 1:
			$connection_status = 'ABORTED';
		break;
		case 2:
			$connection_status = 'TIMEOUT';
		break;
		case 3:
			$connection_status = 'ABORTED and TIMEOUT';
		break;
	}
	
	if (($connection_status_id <> 0) or ($connection_status_id <> '0')){
		global $posix;
		write_log('Процесс('.$posix.') был прерван. connection_status = '.$connection_status);
		global $db;
		$status_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "status_exchange_1c"); 
		if ($status_query->num_rows) {
			foreach (($status_query->rows) as $status_exchange){		
				if ((isset($status_exchange['status'])) and ($status_exchange['status'] == 'progress')){
					$filename = $status_exchange['filename'];
					$connection_status = connection_status();
					saveStatusProgress ($filename, 'stop', 'ERROR! connection_status ='.$connection_status);
					write_log("Процесс(".$posix."). Завершение чтения файла ".$filename.". connection_status =".$connection_status);		
				}
			}
		}
	}
}

function set_language_id() {
global $db;
	
	$config_language = 'config_language';
	$language_setting = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $config_language . "'"); 
	if ($language_setting->num_rows) {
		$language_value = (string)$language_setting->row['value'];
		$language_query = $db->query("SELECT language_id FROM " . DB_PREFIX . "language WHERE code = '" . $language_value . "'");
		if ($language_query->num_rows) {
			$language_id = (int)$language_query->row['language_id'];
			define ( 'LANGUAGE_ID', $language_id );
		}
	}else{
		define ( 'LANGUAGE_ID', 1 );//Идентификатор языка сайта, по умолчанию 1 (Русский)
	}	
	unset($language_setting);
} 

$domain = parseDM($_SERVER['HTTP_HOST']);
$print_key = md5($domain.$lincekey);
$full_url_site = getFullUrlSite();

function ReadSetting() {	
global $db;
global $StopNameCreateSvoistvaArray;

	$setting_params = $db->query("SELECT name_setting, value FROM `" . DB_PREFIX . "setting_exchange_1c`");
	if($setting_params->num_rows){
		foreach ($setting_params->rows as $setting) {
			$name_setting = $setting['name_setting'];
			$value = $setting['value'];
			define ( $name_setting, $value );
		}
	}else{
		write_log('Ошибка! Отсутствуют системные настройки setting_exchange_1c');
		exit();
	}

	if (defined('VM_PROPERTY_STOP_LIST')){
		if ((VM_PROPERTY_STOP_LIST <> "") or (VM_PROPERTY_STOP_LIST <> 1) or (VM_PROPERTY_STOP_LIST <> 0)){
			$StopNameCreateSvoistvaArray = array();
			$std_setting = json_decode(VM_PROPERTY_STOP_LIST, false);
			foreach($std_setting as $setting){
				$id_property = $setting->input_id;
				$name_property = $setting->input_value;		
				$StopNameCreateSvoistvaArray[$id_property] = $name_property;
			}
		}	
	}
	
	$config_customer_group_id = 0;
	if (VERSION_OC15 == 1){
		$config_query = $db->Query ( "SELECT * FROM `" . DB_PREFIX . "setting`"); 
	}else{
		$config_query = $db->Query ( "SELECT * FROM `" . DB_PREFIX . "setting` WHERE `code` ='config'"); 
	}
	if ($config_query->num_rows) {
		foreach ( ($config_query->rows) as $config_result ){
			if ($config_result['key'] == 'config_customer_group_id' ){
				$value = $config_result['value'];
				if ((!empty($value[0])) and ($value[0] == '[')){
					$new_value = str_replace('[','',$config_result['value']);
					$new_value = str_replace(']','',$new_value);
					$new_value = trim($new_value);
					$value_array = explode(",", $new_value);
					$final_array = array();
					foreach($value_array as $value_res){
						$final_array[]= str_replace('"','',$value_res);
					}
					$value = $final_array;
				}
				$config_customer_group_id = $value;
			}				
		}
	}
	define ( 'VM_CONFIG_CUSTOMER_GROUP_DEFAULT', $config_customer_group_id );
}

function authorization($username, $password) {
global $db;
global $registry;
global $print_key;

		//.../system/library/cart/user.php
		//$user_query = $db->query("SELECT * FROM " . DB_PREFIX . "user WHERE username = '" . $db->escape($username) . "' AND password = '" . $db->escape(md5($password)) . "' AND status = '1'"); // for Opencart 1.5
		//$user_query = $db->query("SELECT * FROM " . DB_PREFIX . "user WHERE username = '" . $db->escape($username) . "' AND (password = SHA1(CONCAT(salt, SHA1(CONCAT(salt, SHA1('" . $db->escape($password) . "'))))) OR password = '" . $db->escape(md5($password)) . "') AND status = '1'");
		$user_query = $db->query("SELECT * FROM " . DB_PREFIX . "user WHERE username = '" . $db->escape($username) . "' AND (password = SHA1(CONCAT(salt, SHA1(CONCAT(salt, SHA1('" . $db->escape(htmlspecialchars($password, ENT_QUOTES)) . "'))))) OR password = '" . $db->escape(md5($password)) . "') AND status = '1'");
		if ($user_query->num_rows) {
			return connecting($print_key);
		} else {			
			return 'false user name or password';			
		}
}


function CheckAuthUser() {
global $print_key;
	if (VM_PASSWORD == 1){
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$username	=	($_SERVER['PHP_AUTH_USER']);
			$password	=	($_SERVER['PHP_AUTH_PW']);
			//выполняем авторизацию
			return authorization($username, $password);	
		}else {			
			return 'false user name or password';			
		}
	}else{
		return connecting($print_key);	
	}
}


function LoadFile($filename_a) {
	if (isset ( $filename_a )) {
		/*
		//код для загрузки данных из OC Linux
		$PosDS = strpos (  $filename_a,  '/' );
		$lunex = 0;
		if ($PosDS === false) {
		} else {
			if ($PosDS == 0){
				$filename_a = substr($filename_a, 1);
				$lunex = 1;
			}
		}
		
		$PosDS = strpos (  $filename_a,  '/' );	
		if ($PosDS > 0){
		$parts   = explode( '/' , $filename_a );
			if ($lunex == 0){
				$filename_a   = $parts[2];
			}else{
				$filename_a   = $parts[4];
			}
		}*/
		//--код для загрузки данных из OC Linux
		
		$filename_a = getFileFromPath($filename_a);					
		$isXML = false;
		if (isset($filename_a)){
			$findXml = strpos($filename_a, '.xml');
			if ($findXml === false) {
				$isXML = false;
			} else {
				$isXML = true;
				saveStatusProgress ($filename_a, 'start', 'new load file ='.$filename_a.'');
				HeartBeat::clearElementUploadAll($filename_a);
			}
		}			
		$filename_to_save = JPATH_BASE . DS .'TEMP'. DS . $filename_a;	
		if (function_exists('file_get_contents')) {
            $data = file_get_contents('php://input');
        } elseif (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            $data = $GLOBALS['HTTP_RAW_POST_DATA'];
        } else {
            $data = false;
        }
		if ($data !== false) {				
			if ($png_file = fopen ( $filename_to_save, "wb" )) {
				$result = fwrite ( $png_file, $data );
					if (isset($result)) {
						fclose ( $png_file );
						chmod($filename_to_save , 0777);			
						$isZip = false;
						$file_explode = explode('.', $filename_a);
						$extension = end($file_explode);
						if (($extension == 'zip') or ($extension == 'rar')){
							$isZip = true;
							$zip = new ZipArchive;
							if ($zip->open($filename_to_save) === TRUE) {
								$zip->extractTo(JPATH_BASE . DS .'TEMP');
								$zip->close();
								unlink($filename_to_save);
							}
						}	
						if((STOP_PROGRESS == 1) and ($isXML == false) and ($isZip == false)){
							$copy_result = copyFileToImageFolder($filename_a, 'Неопределено');
						}
						unset($data, $filename_to_save, $png_file, $result, $filename_a);
						return "success";
					}else{
						write_log("Не удалось прочитать файл:".$filename_a);
						return "error upload FILE";
					}			
			}else{
				write_log("Ошибка открытия файла:".$filename_a);
				return "error upload FILE";
			}			
		}else{
			write_log ("Не удалось получить файл:".$filename_a);
			return "error upload FILE";
		}
	}	
	write_log ("Ошибка загрузки файла");
	return "error POST";	
}

function LoadFileZakaz() {
global $db;
	
	$filename_a =  $_REQUEST ['filename'];
	unset($_REQUEST ['filename']);
	$filename_a = getFileFromPath($filename_a);
	
	$use_bitrix = false;
	$PosDS = strpos (  $filename_a,  "documents" );
	if ($PosDS !== false){
		$use_bitrix = true;
	}
	
	$filename_to_save = JPATH_BASE . DS .'TEMP'. DS . $filename_a;
	if (function_exists('file_get_contents')) {
        $data = file_get_contents('php://input');
    } elseif (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $data = $GLOBALS['HTTP_RAW_POST_DATA'];
    } else {
        $data = false;
    }
	if ($data !== false) { 
		if ($png_file = fopen ( $filename_to_save, "wb" )) {
			$result = fwrite ( $png_file, $data );
			usleep(1000);
			if ($result === strlen($data)) {
				fclose ( $png_file );
				chmod($filename_to_save , 0777);
				unset($result, $data);
				$file_zakaz =  simplexml_load_file ($filename_to_save);
				
				if (isset($file_zakaz->Документ)){
					$document = $file_zakaz->Документ;
					$use_bitrix = false;
				}
				if (isset($file_zakaz->Контейнер)){
					$document = $file_zakaz->Контейнер;
					$use_bitrix = true;
				}
				if (isset($document)){
					if ($use_bitrix == true){
						foreach ($document as $container){
							foreach ($container as $zakaz_1c){
								LoadZakaz($zakaz_1c);
							}
						}
					}else{
						foreach ($document as $zakaz_1c){
							LoadZakaz($zakaz_1c); 	
						}
					}
				}else{
					write_log('В файле '.$filename_to_save.' отсутствуют документы для обработки');
				}
				if (VM_DELETE_TEMP == 1){
					clear_files_temp($filename_to_save);	
				}
			}	
		}else{
			write_log ('error upload FILE='.$filename_a);
			return "error upload FILE";
		}
	}
	return "success";
}

function LoadZakaz($zakaz_1c) {
global $db;

	if (empty($zakaz_1c)){
		return;
	}
				
	$nomer = (isset($zakaz_1c->Номер)) ? (string)$zakaz_1c->Номер : '';
	$date  = (isset($zakaz_1c->Дата)) ? (string)$zakaz_1c->Дата : '';
	$order_array  = $db->query ( "SELECT order_id FROM " . DB_PREFIX . "order where order_id = '" . (int)$nomer . "'" );
	if ($order_array->num_rows){
		if (VM_UPDATE_STATUS_ORDER == 1){
			if (isset($zakaz_1c->ЗначенияРеквизитов->ЗначениеРеквизита)){
				foreach ($zakaz_1c->ЗначенияРеквизитов->ЗначениеРеквизита as $zakaz_data){
					$name_param = (isset($zakaz_data->Наименование)) ? (string)$zakaz_data->Наименование : '';
					$value_param = (isset($zakaz_data->Значение)) ? (string)$zakaz_data->Значение : '';
					if ($name_param == "Дата оплаты по 1С"){
						$oplata_date = $value_param;
					}
					if (($name_param == "Оплачен") and ($value_param == "true")){
						$oplata_date = $date;
					}
					if ($name_param == "Номер отгрузки по 1С"){
						$nomer_real = $value_param;
					}
					if ($name_param == "Дата отгрузки по 1С" and $value_param <> 'T'){
						$date_real = $value_param;
					}
					if (($name_param == "Отгружен") and ($value_param == "true")){
						$nomer_real = $nomer;
						$date_real  = $date;
					}
					if ($name_param == "ПометкаУдаления"){
						$delete_order = $value_param;
					}
				}
			}
			//отражаем статус оплаты 
			if (isset ( $oplata_date)) {	
				$order_status_oplacheno = OrderStatusReturn ('Оплачено');							
				$oplata_update  = $db->query ( "UPDATE " . DB_PREFIX . "order  SET order_status_id='" . $order_status_oplacheno . "' where order_id = '" . (int)$nomer . "'" );
			}
								
			//отражаем статус отгрузки 		
			if (isset ( $nomer_real,$date_real)) {
				$order_status_dostavleno = OrderStatusReturn ('Доставлено');
				$oplata_update  = $db->query ( "UPDATE " . DB_PREFIX . "order  SET order_status_id='" . $order_status_dostavleno . "' where order_id = '" . (int)$nomer . "'" );		
			}

			//отражаем статус отмены заказа
			if (isset ($delete_order)) {
				if  (($delete_order == 'true') or ($delete_order == 'Истина')){
					$order_status_otmena = OrderStatusReturn ('Отменено');							
					$oplata_update  = $db->query ( "UPDATE " . DB_PREFIX . "order  SET order_status_id='" . $order_status_otmena . "' where order_id = '" . (int)$nomer . "'" );
				}
			}
			$oplata_date = NULL;
			$nomer_real	= NULL;
			$date_real = NULL;
			$delete_order = NULL;
		}
		if (VM_UPDATE_ORDERDATA1C == 1){
			if (isset($zakaz_1c->Товары->Товар)){
				$OrderTotal1c = (isset($zakaz_1c->Сумма)) ? (float)$zakaz_1c->Сумма : '';
				$sub_total = 0;
				$db->query( "DELETE FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$nomer . "'" );
				foreach ($zakaz_1c->Товары->Товар as $product_data){
					$IdTovar1c = (isset($product_data->Ид)) ? (string)$product_data->Ид : '';
					$Artikul = (isset($product_data->Артикул)) ? (string)$product_data->Артикул : '';
					$Artikul = !empty($Artikul) ? $Artikul : 'Не указано';
					$Name = (isset($product_data->Наименование)) ? (string)$product_data->Наименование : 'Наименование не заполнено';
					$Price = (isset($product_data->ЦенаЗаЕдиницу)) ? (float)$product_data->ЦенаЗаЕдиницу : 0;
					$Quantity = (isset($product_data->Количество)) ? (float)$product_data->Количество : 0;
					$Summ = (isset($product_data->Сумма)) ? (float)$product_data->Сумма : 0;
										
					$typeProduct = 'Товар';
						if (isset($product_data->ЗначенияРеквизитов->ЗначениеРеквизита)){
							foreach ($product_data->ЗначенияРеквизитов->ЗначениеРеквизита as $property_value_data){
								$name_property = (isset($property_value_data->Наименование)) ? (string)$property_value_data->Наименование : '';
								$value_property = (isset($property_value_data->Значение)) ? (string)$property_value_data->Значение : '';
								if ($name_property == "ТипНоменклатуры"){
									if ($value_property == 'Услуга'){
										$typeProduct = 'Услуга';
									}
								}
							}
						}
						if ($typeProduct == 'Товар'){
							insertOrderProduct($IdTovar1c, $nomer, $Name, $Artikul, $Quantity, $Price, $Summ);
							$sub_total = $sub_total + $Summ;
						}
						if ($typeProduct == 'Услуга'){
							$words_array = array('достав', 'посылк', 'бандерол', 'курьер');
							$isProduct = true;
							foreach($words_array as $word){
								$pos = strrpos($Name, $word);
								if (!$pos === false) { 
									$isProduct = false;
								}
							}
							if ($isProduct == true){
								//insertOrderProduct($IdTovar1c, $nomer, $Name, $Artikul, $Quantity, $Price, $Summ); //если добавлять услуги как товары, то в 1С будут дубли услуг
							}else{
								$name_shipping = substr($Name, 0, 127);
								$update_order  = $db->query ( "UPDATE " . DB_PREFIX . "order  SET shipping_method ='".$name_shipping."' where order_id = '" . (int)$nomer . "'" );
								$name_shipping = substr($Name, 0, 254);
								$shipping_query = $db->query ( "SELECT * FROM " . DB_PREFIX ."order_total where  order_id = '" . (int)$nomer . "' and code = 'shipping'" );
								if ($shipping_query->num_rows) {
									$update_order  = $db->query ( "UPDATE " . DB_PREFIX . "order_total  SET value='" . $Summ . "', title='".$name_shipping."' where order_id = '" . (int)$nomer . "' AND code = 'shipping'" );
								}else{
									$ins = new stdClass ();
									$ins->order_total_id = NULL;
									$ins->code = 'shipping';
									$ins->title = $name_shipping;
									$ins->value = $Summ;
									$ins->sort_order = 0;
									insertObject ( "" . DB_PREFIX ."order_total", $ins) ;
								} 	
							}
						}									
				}
				$date_modified = date('Y-m-d H:i:s');
				$update_order  = $db->query ( "UPDATE " . DB_PREFIX . "order  SET total='" . $OrderTotal1c . "', date_modified = '".$date_modified."' where order_id = '" . (int)$nomer . "'" );
				$update_order  = $db->query ( "UPDATE " . DB_PREFIX . "order_total  SET value='" . $OrderTotal1c . "' where order_id = '" . (int)$nomer . "' AND code = 'total'" );
				$update_order  = $db->query ( "UPDATE " . DB_PREFIX . "order_total  SET value='" . $sub_total . "' where order_id = '" . (int)$nomer . "' AND code = 'sub_total'" );
			}
		}
	}else{
		write_log('Заказ '.$nomer.' не найден на сайте');
	}			
}

function insertOrderProduct($IdTovar1c, $order_id, $Name, $Artikul, $Quantity, $Price, $Summ) {
global $db;	
	
	$product_id = 0;
	$pos = strrpos($IdTovar1c, "#");
	if ($pos === false) { 
		$product_id_query = $db->query ( "SELECT product_id FROM " . DB_PREFIX ."product where product_1c_id = '" . $IdTovar1c . "'" );
		if ($product_id_query->num_rows) {
			$product_id = (int)$product_id_query->row['product_id'];
			$product_desc_query = $db->query ( "SELECT name FROM " . DB_PREFIX ."product_description where product_id = '".(int)$product_id."' and language_id = '".LANGUAGE_ID."'" );
			if ($product_desc_query->num_rows) {
				$Name = $product_desc_query->row['name'];
			}		
		}
	}else{
		$str=strpos($IdTovar1c, "#");
		$product_id_1c=substr($IdTovar1c, 0, $str);
		$product_option_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "product_option_value_1c WHERE product_option_value_1c_id = '" . $IdTovar1c . "'" );		
		if ($product_option_query->num_rows){	
			$product_id = (int)$product_option_query->row['product_id'];
		}	
	}

	$ins = new stdClass ();
	$ins->order_product_id = NULL;
	$ins->order_id = (int)$order_id;
	$ins->product_id = (int)$product_id;
	$ins->name = substr($Name, 0, 254);
	$ins->model = substr($Artikul, 0, 63);
	$ins->quantity = $Quantity;
	$ins->price = $Price;
	$ins->total = $Summ;
	$ins->tax = 18;
	$ins->reward = 0;
	insertObject ( "" . DB_PREFIX ."order_product", $ins) ;
}

function new_shopper_group($shopper_group_name , $id_1c) {
global $db;

	$ins = new stdClass ();
	$ins->customer_group_id = NULL;
	$ins->approval = '0';
	$ins->sort_order = '0';
	insertObject ( "" . DB_PREFIX ."customer_group", $ins) ;
	
	$customer_group_id = (int)getLastId("".DB_PREFIX ."customer_group", 'customer_group_id');
	
	$ins = new stdClass ();
	$ins->customer_group_id = $customer_group_id;
	$ins->language_id = LANGUAGE_ID;
	$ins->name = $shopper_group_name;
	$ins->description = '';
	$ins->customer_group_1c_id = $id_1c;
	insertObject ( "" . DB_PREFIX ."customer_group_description", $ins ) ;	
			
	return $customer_group_id;
}

Function ShopperGroupsArrayFillPackageOffers($ShopperGroupsArray) {
global $db;
	
	$result_customer_group_array = $db->query ( "SELECT customer_group_id, customer_group_1c_id  FROM " . DB_PREFIX ."customer_group_description where customer_group_1c_id <> ''");
	if ($result_customer_group_array->num_rows){	
		foreach ( ($result_customer_group_array->rows) as $result_customer_group ){
			$ShopperGroupsArray[$result_customer_group['customer_group_1c_id']]['id_vm']=	$result_customer_group['customer_group_id'] ;
		}
	}
	return $ShopperGroupsArray;
}

function ShopperGroupsArrayFill($offers, $ShopperGroupsArray) {
global $db;
	
	if (!isset($offers)){
		return $ShopperGroupsArray;
	}
	
	if (!isset($offers->ТипЦены)){
		return $ShopperGroupsArray;
	}
	
	foreach ($offers->ТипЦены as $shopper_group_data){
		$name = (isset($shopper_group_data->Наименование)) ? (string)$shopper_group_data->Наименование : '';
		$id_1c = (isset($shopper_group_data->Ид)) ? (string)$shopper_group_data->Ид : '';
		$currency = (isset($shopper_group_data->Валюта)) ? (string)$shopper_group_data->Валюта : 'RUB';
	
		$result_customer_group_id = $db->query ( "SELECT customer_group_id  FROM " . DB_PREFIX ."customer_group_description where name = '".$name."'" );
		if (!($result_customer_group_id->num_rows)) { 
			// нет такого пипа цен, значит заводим новый
			$ShopperGroupsArray [$id_1c] ['name'] = $name;
		    $ShopperGroupsArray [$id_1c] ['currency'] = $currency;
		    $ShopperGroupsArray [$id_1c] ['id_vm'] = new_shopper_group ( $name , $id_1c );
		}else{
			$ShopperGroupsArray [$id_1c] ['name'] = $name;
			$ShopperGroupsArray [$id_1c] ['currency'] = $currency;
			$shopper_group_id_array = $result_customer_group_id->row['customer_group_id'];
			$ShopperGroupsArray [$id_1c] ['id_vm'] = $shopper_group_id_array;
			
			$db->query( "UPDATE " . DB_PREFIX . "customer_group_description SET customer_group_1c_id ='".$id_1c."' where name = '".$name."'" );
			
		}			
	}
	return $ShopperGroupsArray;
}

function NewCategory($CategoryName, $Category1c_id) {	
global $db;
global $languages;

	$ins = new stdClass ();
	$ins->category_id = NULL;
	$ins->parent_id = 0;
	$ins->top = 1;
	$ins->column = 0;
	$ins->sort_order = 1;
	$ins->status = 1;
	$ins->date_added = date ('Y-m-d H:i:s');
	$ins->date_modified = date ('Y-m-d H:i:s');
	$ins->category_1c_id = $Category1c_id;
	insertObject ( "" . DB_PREFIX ."category", $ins, 'category_id'  ) ;
	$category_id = ( int )$ins->category_id;
	
	foreach ($languages as $lang) {
		$ins = new stdClass ();
		$ins->category_id = $category_id;
		$ins->language_id = $lang['language_id'];
		$ins->name = $CategoryName;
		$ins->description = $CategoryName;
		if (VERSION_OC15 == 0){
			$ins->meta_title = 'Купить '.$CategoryName;
		}
		$ins->meta_description = $CategoryName;
		$ins->meta_keyword = '';
		insertObject ( "" . DB_PREFIX ."category_description", $ins );
	}
	
	$ins = new stdClass ();
	$ins->category_id = $category_id;
	$ins->path_id = $category_id;
	$ins->level = 0;
	insertObject ( "" . DB_PREFIX ."category_path", $ins ) ;
	
	$ins = new stdClass ();
	$ins->category_id = $category_id;
	$ins->store_id = STORE_ID;
	insertObject ( "" . DB_PREFIX ."category_to_store", $ins ) ;
	
	$ins = new stdClass ();
	$ins->category_id = $category_id;
	$ins->store_id = STORE_ID;
	$ins->layout_id = 0;
	insertObject ( "" . DB_PREFIX ."category_to_layout", $ins ) ;
	
	update_url_alias ($category_id, $CategoryName, 'category_id');

	return $category_id;
}


function CategoryXrefFill($CategoryArray) {
global $db;
	if (VM_HIERARCHY_FOLDER == 1){
		foreach ( $CategoryArray as $category ) {
			//поиск группы  по id
			$parent_id_query  = $db->query ( "SELECT  parent_id FROM " . DB_PREFIX . "category where category_id = '" . (int)$category ['category_id'] . "'" );
			if ($parent_id_query->num_rows) {
				$CategoryParentIdArray = $parent_id_query->row['parent_id'];
				$CategoryParentId = $CategoryParentIdArray;
			}else{
				return;	
			}
			
			$categoryowner = (int)$category ['owner'];
			$CategoryParentIdInt = (int)$CategoryParentId;
			if ($CategoryParentIdInt != $categoryowner ) {
			//случай категория входит не в ту родительскую категорию, переписываем
				$db->query( "UPDATE " . DB_PREFIX . "category SET parent_id ='".$category ['owner']."' where category_id ='" . (int)$category ['category_id'] . "'" );
				
				if ($category ['category_id'] <> $category ['owner']){
					$level = 1;
				} else {
					$level = 0;
				}
				
				$db->query( "UPDATE " . DB_PREFIX . "category_path SET level ='".$level."' where category_id ='" . (int)$category ['category_id'] . "'" );
				
				$path_id_query  = $db->query ( "SELECT level FROM " . DB_PREFIX . "category_path where category_id = '" . (int)$category ['category_id'] . "' AND path_id = '" . $category ['owner'] . "'" );
				if ($path_id_query->num_rows) {				
					if ($level == 1){
						//ничего не делаем	
					}else{				
						$db->query( "DELETE FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" .(int)$category ['category_id']. "'" );
					}		
				}else{		
					if ($level == 1){
						$ins = new stdClass ();
						$ins->category_id = (int)$category ['category_id'];
						$ins->path_id = $category ['owner'];
						$ins->level = 0;
						insertObject ( "" . DB_PREFIX ."category_path", $ins ) ;
					}else{
						//ничего не делаем
					}
				}
			}else {
				//случай строки нет, тогда создаем новую
			}

			$query_store_id  = $db->query ( "SELECT * FROM " . DB_PREFIX . "category_to_store WHERE category_id = '" . (int)$category ['category_id'] . "' AND store_id = '".STORE_ID."'" );
			if (!$query_store_id->num_rows) {
				$ins = new stdClass ();
				$ins->category_id = $category ['category_id'];
				$ins->store_id = STORE_ID;
				insertObject ( "" . DB_PREFIX ."category_to_store", $ins ) ;
								
				$ins = new stdClass ();
				$ins->category_id = $category ['category_id'];
				$ins->store_id = STORE_ID;
				$ins->layout_id = 0;
				insertObject ( "" . DB_PREFIX ."category_to_layout", $ins ) ;
			}	
		}
	}
}

function CategoryArrayFill($xml, $CategoryArray, $owner) {	
global $db;
	//рекурсия
	if (!isset($xml->Группы)){
		return $CategoryArray;
	}
	
	foreach ($xml->Группы as $GroupCategory){
		if (isset($GroupCategory->Группа)){
			foreach ($GroupCategory->Группа as $Category){
				$name = (isset($Category->Наименование)) ? (string)$Category->Наименование : 'Наименование не задано';
				$cnt = (isset($Category->Ид)) ? (string)$Category->Ид : 'empty';
				$name = htmlentities($name, ENT_QUOTES, "UTF-8");
				$image = (isset($Category->Картинка)) ? (string)$Category->Картинка : '';

				if (isset($CategoryArray[$cnt]['category_id'])){
					continue;
				}

				$CategoryArray [$cnt] ['name'] = $name;
				$CategoryArray [$cnt] ['owner'] = $owner;
				
				//заполнение ИД для существующих категорий
				$category_1c_id_query = $db->query ( "SELECT c.category_1c_id as category_1c_id, c.category_id as category_id FROM " . DB_PREFIX ."category_description AS cd LEFT OUTER JOIN " . DB_PREFIX ."category AS c ON cd.category_id = c.category_id  where cd.name = '" . $name . "'" );			
				if ($category_1c_id_query->num_rows) {
					$Category1cIdResult = $category_1c_id_query->row['category_1c_id'];
					if (empty($Category1cIdResult)){
						$CategoryId = $category_1c_id_query->row['category_id'];
						$category_update = $db->query(  "UPDATE " . DB_PREFIX . "category SET category_1c_id='".$cnt."', date_modified = '".date('Y-m-d H:i:s')."' where category_id ='".(int)$CategoryId."'");
					}	
				}
				
				//поиск группы(категории) по ИД
				$category_id_query = $db->query ( "SELECT category_id FROM " . DB_PREFIX . "category where category_1c_id = '".$cnt."'" );
				if ($category_id_query->num_rows) {
					$CategoryId = $category_id_query->row['category_id'];
					$CategoryArray [$cnt] ['category_id'] = $CategoryId; 
					$CategoryNameUpdate = $db->query(  "UPDATE " . DB_PREFIX . "category_description SET name='".$name."' where category_id ='".(int)$CategoryId."' AND language_id = '" . LANGUAGE_ID . "'");
					$category_update = $db->query(  "UPDATE " . DB_PREFIX . "category SET date_modified = '".date ('Y-m-d H:i:s')."' where category_id ='".(int)$CategoryId."'");
				}else{	
					$CategoryArray[$cnt]['category_id'] = NewCategory ($name, $cnt);
					$CategoryId = $CategoryArray[$cnt]['category_id'];
				}

				if (!empty($image)){
					$PicturePath = $image;
					$PicturePath = getFileFromPath($PicturePath);
					$copy_result = copyFileToImageFolder($PicturePath, $name);
					if ($copy_result['status_result'] == 'true'){
						$image_update = $db->query (  "UPDATE " . DB_PREFIX . "category SET image='".$copy_result['file_path_db']."' where category_id ='".$CategoryId."'");	
					}
					if (($copy_result['status_result'] == 'false') and (STOP_PROGRESS == 1)){
						$image_update = $db->query (  "UPDATE " . DB_PREFIX . "category SET image='".$copy_result['file_path_db']."' where category_id ='".$CategoryId."'");
					}
				}

				$new_owner = $CategoryArray[$cnt]['category_id'];
				$CategoryArray = CategoryArrayFill($Category, $CategoryArray, $new_owner);
			}
		}
	}
	return $CategoryArray;
}

function dropCategoryWithOneOwner($CategoryArray){
global $db;
	if (VM_DROP_CATEGORY_WITH_ONE_OWNER == 0){
		return $CategoryArray;
	}
	$one_categories_array = array();
	foreach ( $CategoryArray as $category_cnt => $category ) {
		if ((isset($category ['owner'])) and ($category ['owner'] == 0)){
			if (isset($category ['category_id'])){
				$one_categories_array[$category_cnt]['category_id'] = $category ['category_id']; 
				$one_categories_array[$category_cnt]['name'] = $category ['name']; 				
			}	
		}
	}
	$CategoryArrayNew = array();
	if (count($one_categories_array) == 1){
		foreach ( $one_categories_array as $one_category_cnt => $one_category ) {
			foreach ( $CategoryArray as $category_cnt => $category  ) {				
				if ($category_cnt == $one_category_cnt){	
					$update_category = $db->query("UPDATE " . DB_PREFIX . "category SET status='0' where category_id ='".$category['category_id']."'");;						
					write_log("Согласно настройкам загрузки категорий (VM_DROP_CATEGORY_WITH_ONE_OWNER=".VM_DROP_CATEGORY_WITH_ONE_OWNER.") была СКРЫТА видимость категории: ".$category['name']." (".$category['category_id'].")");
					continue;
				}
				if ($category ['owner'] == $one_category['category_id']){
					$category ['owner'] = 0;
					$CategoryArrayNew[$category_cnt] = $category;
				}else{
					$CategoryArrayNew[$category_cnt] = $category;
				}		
			}
		}
	}else{
		foreach ( $one_categories_array as $one_category_cnt => $category ) {
			$update_category = $db->query("UPDATE " . DB_PREFIX . "category SET status='1' where category_id ='".$category['category_id']."'");;						
			write_log("Согласно настройкам загрузки категорий (VM_DROP_CATEGORY_WITH_ONE_OWNER=".VM_DROP_CATEGORY_WITH_ONE_OWNER.") была ВКЛЮЧЕНА видимость категории: ".$category['name']." (".$category['category_id'].")");
		}
	}
	if (count($CategoryArrayNew) > 0){
		$CategoryArray = $CategoryArrayNew;
	}
	return $CategoryArray;
}

function UpdateManufactureProduct($Izgotovitel,$product_id){
	
	//Загрузка производителей 
	if ($Izgotovitel != "") {
		CreateManufactureForProduct($Izgotovitel, $product_id);		
	} else {
		CreateManufactureForProduct('Производитель не указан', $product_id);	
	}		
}

function CreateManufactureForProduct($Izgotovitel, $product_id){
global $db;
global $languages;
		
	$Izgotovitel = $db->escape($Izgotovitel); //only Opencart CMS
		
	//проверяем наличие изготовителя в базе
	$manufacturer_id_query  = $db->query ( "SELECT manufacturer_id FROM " . DB_PREFIX . "manufacturer where name = '" . $Izgotovitel . "'" );
	if (!($manufacturer_id_query->num_rows)) {		
		//создаем производителя
		$ins = new stdClass ();
		$ins->manufacturer_id = NULL;
		$ins->name = $Izgotovitel;
		$ins->image = "";
		$ins->sort_order = "0";
		insertObject ( "" . DB_PREFIX ."manufacturer", $ins ) ;
			
		$manufacturer_id = (int)getLastId("".DB_PREFIX ."manufacturer", 'manufacturer_id');	

		$ins = new stdClass ();
		$ins->manufacturer_id = $manufacturer_id;
		$ins->store_id = STORE_ID;
		insertObject ( "" . DB_PREFIX ."manufacturer_to_store", $ins  ) ;

		$manufacturer_description_query = $db->query ( "SHOW TABLES LIKE '" . DB_PREFIX . "manufacturer_description'" );
		if ($manufacturer_description_query->num_rows){
			foreach ($languages as $lang){
				$ins = new stdClass ();
				$ins->manufacturer_id = $manufacturer_id;
				$ins->language_id = $lang['language_id'];
				if ((VERSION_OC15 == 0) and (VM_MANUFACTURER_DESCRIPTION == 1)){
					$ins->name = $Izgotovitel;
					$ins->meta_title = $Izgotovitel;
				}
				$ins->description = "";
				$ins->meta_h1 = $Izgotovitel;
				$ins->meta_description = $Izgotovitel;
				$ins->meta_keyword = "";
				insertObject ( "" . DB_PREFIX ."manufacturer_description", $ins  ) ;
			}
		}		
		$manufacturer_id_update = $db->query (  "UPDATE " . DB_PREFIX . "product SET manufacturer_id='".(int)$manufacturer_id."' where product_id ='".(int)$product_id."'");									
	}else {
		$manufacturer_id = (int)$manufacturer_id_query->row['manufacturer_id'];
		$manufacturer_id_update = $db->query (  "UPDATE " . DB_PREFIX . "product SET manufacturer_id='".(int)$manufacturer_id."' where product_id ='".(int)$product_id."'");
	}
}

function NewProductsXref($category_id, $product_id) {
global $db;
	$product_category_query  = $db->query ( "SELECT product_id FROM " . DB_PREFIX . "product_to_category where category_id = '" . $category_id . "'" );
	if ($product_category_query->num_rows) {
		$product_id_int = (int)$product_id;
		$product_category_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" .$product_id_int. "'");
	}
	$ins = new stdClass ();
	$ins->product_id = (int)$product_id;
	$ins->category_id = (int)$category_id;

	$search_column_name  = $db->query ( "SELECT COUNT(COLUMN_NAME) AS 'countcolums' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . DB_PREFIX . "product_to_category' AND TABLE_SCHEMA = '" . DB_DATABASE . "'" );
	if ($search_column_name->num_rows){
		$countcolums = (int)$search_column_name->row['countcolums'];
		if ($countcolums > 2){
			$ins->main_category = 1;
		}
	}

	usleep(10000);
	insertObject ( "" . DB_PREFIX ."product_to_category", $ins ) ;

	if ((VM_PRODUCT_LOAD_IN_PARENTCATEGORY == 1) AND (VM_FOLDER == 1)){
		ParentCategoryFillProduct($category_id, $product_id);
	}
}

function ParentCategoryFillProduct($category_id, $product_id) { //рекурсия
global $db;
	$search_parent_category  = $db->query ( "SELECT parent_id FROM " . DB_PREFIX . "category where category_id = '" . (int)$category_id . "'" );
	if ($search_parent_category->num_rows) {
		$parent_id = (int)$search_parent_category->row['parent_id'];
		if($parent_id > 0){
			$ins = new stdClass ();
			$ins->product_id = (int)$product_id;
			$ins->category_id = (int)$parent_id;
	
			$search_column_name  = $db->query ( "SELECT COUNT(COLUMN_NAME) AS 'countcolums' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . DB_PREFIX . "product_to_category' AND TABLE_SCHEMA = '" . DB_DATABASE . "'" );
			if ($search_column_name->num_rows){
				$countcolums = (int)$search_column_name->row['countcolums'];
				if ($countcolums > 2){
					$ins->main_category = 0;
				}
			}

			usleep(10000);
			insertObject ( "" . DB_PREFIX ."product_to_category", $ins ) ;
			ParentCategoryFillProduct($parent_id, $product_id);
		}
	}	
}

function NewProduct($product_SKU, $product_name, $product_desc, $product_full_image, $product_thumb_image,$IdTovar1c, $Opisanie, $Izgotovitel, $Weight, $Length, $Width, $Height, $Code, $mark_delete) {
global $db;
global $languages;
	
	//в таблицу product
	$ins = new stdClass ();
	$ins->product_id = NULL;
	$ins->model = $product_SKU;
	$ins->sku = $product_SKU;
	$ins->upc = $Code;
	if (VERSION_OC15 == 0){
		$ins->ean = '';
		$ins->jan = '';
		$ins->isbn = '';
		$ins->mpn = '';
		$ins->location = '';
	}
	$ins->quantity = '0';
	$ins->stock_status_id = VM_STOCK_STATUS;
	$ins->image = $product_full_image;
	$ins->manufacturer_id = '0';
	$ins->shipping = '1';
	$ins->price = '0';
	$ins->points = '0';
	$ins->tax_class_id = '9';
	$ins->date_available =date('Y-m-d');
	
	if (!empty($Weight)){
		$ins->weight = $Weight;
	}else{
		$ins->weight = '0';
	}
	$ins->weight_class_id = '1';
	if (!empty($Length)){
		$ins->length = $Length;
	}else{
		$ins->length = '0';
	}
	if (!empty($Width)){
		$ins->width = $Width;
	}else{
		$ins->width = '0';
	}
	if (!empty($Height)){
		$ins->height = $Height;
	}else{
		$ins->height = '0';
	}
	
	$ins->length_class_id = '1';
	$ins->subtract = '1';
	$ins->minimum = '1';
	$ins->sort_order = '0';

	$product_publish = '1';
	if ((VM_PRODUCT_VIEW_PRICE0 == 0) or (VM_PRODUCT_VIEW_COUNT0 == 0)){ 
		$product_publish = '0';
	}
	if (($mark_delete == true) and (VM_DELETE_MARK_PRODUCT == 'HIDE')){
		$product_publish = '0';
	}
	$ins->status = $product_publish;

	$ins->viewed = '0';
	$ins->date_added = date('Y-m-d H:i:s');
	$ins->date_modified = date('Y-m-d H:i:s');
	$ins->product_1c_id = $IdTovar1c;
	insertObject ( "" . DB_PREFIX ."product", $ins, 'product_id' );
	
	$product_id = (int)$ins->product_id;
	
	//в таблицу product_description
	foreach ($languages as $lang) {
		$ins = new stdClass ();
		$ins->product_id = $product_id;
		$ins->language_id = $lang['language_id'];
		$ins->name = $product_name;
		$ins->description = $Opisanie;
		
		if (VERSION_OC15 == 0){
			if (VM_TAG_CREATE == 1){
				$ins->tag = $product_name;
			}else{
				$ins->tag = "";	
			}
			$ins->meta_title = 'Купить '.$product_name;
		}
		$ins->meta_description = $product_name;
		
		$words = explode(' ', $product_name);
		$meta_keyword = implode(',', $words) . ',' . $product_name;
		$ins->meta_keyword = $meta_keyword;
		insertObject ( "" . DB_PREFIX ."product_description", $ins);
	}
	
	//в таблицу product_to_store
	$ins = new stdClass ();
	$ins->product_id = $product_id;
	$ins->store_id = STORE_ID;
	insertObject ( "" . DB_PREFIX ."product_to_store", $ins); //Не удаляет записи через удаление товаров в админке
	
	//Загрузка производителей 
	if (($Izgotovitel != "") and (VM_MANUFACTURER_1C == 1)) {
		CreateManufactureForProduct($Izgotovitel, $product_id);		
	}elseif(VM_MANUFACTURER_1C == 1){
		CreateManufactureForProduct('Производитель не указан', $product_id);	
	}
	//******
	update_url_alias ($product_id, $product_name, 'product_id');
	
	return $product_id;
}

function AddDirectorySvoistva($xml_product , $xml_all_svoistva){ //$xml_all){
global $db;
global $StopNameCreateSvoistvaArray;

	if (VM_SVOISTVA_1C == 1){
		 
		$PropertyStd = '';
		if (isset($xml_all_svoistva->Свойство)) {
			$PropertyStd = $xml_all_svoistva->Свойство;
		}
		if (isset($xml_all_svoistva->СвойствоНоменклатуры)) {
			$PropertyStd = $xml_all_svoistva->СвойствоНоменклатуры;
		} 
		 
		if (!empty($PropertyStd)) {
			foreach ($PropertyStd as $Svoistvo) {
				$id_svoistva = (isset($Svoistvo->Ид)) ? (string)$Svoistvo->Ид : '';
				$id_svoistva = formatString($id_svoistva);
				$name_svoistva = (isset($Svoistvo->Наименование)) ? (string)$Svoistvo->Наименование : '';
				$name_svoistva = formatString($name_svoistva, 1);
						
				if (!in_array($name_svoistva, $StopNameCreateSvoistvaArray)){
					$type_value = (isset($Svoistvo->ТипЗначений)) ? (string)$Svoistvo->ТипЗначений : '';
					if (isset($Svoistvo->ТипЗначений)) {
						if ((string)$Svoistvo->ТипЗначений == 'Справочник') {			
							//загружаем в бд
							$svoistvo_urlArray = $db->query ( "SELECT attribute_id FROM " . DB_PREFIX . "attribute WHERE attribute_1c_id ='" .$id_svoistva. "'" );
							if (!($svoistvo_urlArray->num_rows)) { 			
								$attribute_id = createAttribute($name_svoistva, $id_svoistva);
							}
							foreach($Svoistvo->ВариантыЗначений->Справочник as $option_result){
								if ((string)$option_result->Значение != '') {
									$id_option = (string)$option_result->ИдЗначения;
									$option_value = formatString((string)$option_result->Значение, 1);
									$SvoistvoValues[$id_svoistva][$id_option] = $option_value;
								}
							}
						}								
					}
				}
						
			}
		
			//СЧИТЫВАЕМ СВОЙСТВА ТОВАРОВ
			if (isset($xml_product->Товар)){
				foreach ($xml_product->Товар as $Tovar){
					$tovar_id =(string)$Tovar->Ид ;
					$product_id_query = $db->query ( "SELECT product_id FROM " . DB_PREFIX ."product where product_1c_id = '" . $tovar_id . "'" );
					if (($product_id_query->num_rows)){
						$product_id = $product_id_query->row['product_id'];	
						if (isset($Tovar->ЗначенияСвойств->ЗначенияСвойства)){
							foreach ($Tovar->ЗначенияСвойств->ЗначенияСвойства as $ZnachSvoistvaTovar){
								$id_svoistvo_tovar = (isset($ZnachSvoistvaTovar->Ид)) ? (string)$ZnachSvoistvaTovar->Ид : '';
								$znach_svoistvo_tovar = (isset($ZnachSvoistvaTovar->Значение)) ? (string)$ZnachSvoistvaTovar->Значение : '';
								$znach_svoistvo_tovar = formatString($znach_svoistvo_tovar, 1);
								if (isset($SvoistvoValues[$id_svoistvo_tovar][$znach_svoistvo_tovar])){	//проверяем наличие свойств в массиве		
									$attribute_query = $db->query ( "SELECT attribute_id FROM " . DB_PREFIX . "attribute WHERE attribute_1c_id ='" .$id_svoistvo_tovar. "'" );
									if ($attribute_query->num_rows) { 
										$attribute_id = $attribute_query->row['attribute_id'];		
										insertAttributeToProduct($attribute_id, $SvoistvoValues[$id_svoistvo_tovar][$znach_svoistvo_tovar], $product_id);
									}		
								}
							}	
						}
					}
				}
			}
		}
	}	
}

function CreateNewCustoms($xml_all_svoistva) {
global $db;
global $StopNameCreateSvoistvaArray;
$property_type_array = array();

	$PropertyStd = '';
	if (isset($xml_all_svoistva->Свойство)) {
		$PropertyStd = $xml_all_svoistva->Свойство;
	}
	if (isset($xml_all_svoistva->СвойствоНоменклатуры)) {
		$PropertyStd = $xml_all_svoistva->СвойствоНоменклатуры;
	}
	
	if (!empty($PropertyStd)) {		
		foreach ($PropertyStd as $Svoistvo) {
			$id_svoistva = (isset($Svoistvo->Ид)) ? (string)$Svoistvo->Ид : '';
			$name_svoistva = (isset($Svoistvo->Наименование)) ? (string)$Svoistvo->Наименование : '';
			$name_svoistva = formatString($name_svoistva, 1);
			
			$type_value = '';
			if (isset($Svoistvo->ТипЗначений)) {
				$type_value = (isset($Svoistvo->ТипЗначений)) ? (string)$Svoistvo->ТипЗначений : '';
				$type_value = formatString($type_value);
			}
			//для УТ 10.3
			if (UT_10_3 == 1){
				if ((!isset($Svoistvo->ТипЗначений)) and (!isset($Svoistvo->ВариантыЗначений))){
					$type_value = 'Строка';
				}
			}
			$property_type_array[$id_svoistva] = $type_value;
			
			$svoistvo_urlArray = $db->query ( "SELECT attribute_id FROM " . DB_PREFIX . "attribute WHERE attribute_1c_id ='" .$id_svoistva. "'" );
			if (!($svoistvo_urlArray->num_rows) AND (($type_value == 'Строка') or ($type_value == 'Число') or ($type_value == 'Время'))) { 
							
				if (UT_10_3 == 0){
				//для УТ 11			
					if (!in_array($name_svoistva, $StopNameCreateSvoistvaArray)){
						$attribute_id = createAttribute($name_svoistva, $id_svoistva);
					}
				}
				if (UT_10_3 == 1){
				//для УТ 10.3
					if (!($svoistvo_urlArray->num_rows) AND ($type_value == 'Строка') ) { 	
						if (!in_array($name_svoistva, $StopNameCreateSvoistvaArray)){	
							$attribute_id = createAttribute($name_svoistva, $id_svoistva);
						}	
				    }
				}
		    }
		}
	}
	return $property_type_array;
}

function ReadCustoms($Tovar, $product_id, $property_type_array) {
global $db;	
global $StopNameCreateSvoistvaArray;
		
	if (isset($Tovar->ЗначенияСвойств->ЗначенияСвойства)) {	
		foreach ($Tovar->ЗначенияСвойств->ЗначенияСвойства as $ValueSvoistva){ 
			$id_svoistva_tovar = (isset($ValueSvoistva->Ид)) ? (string)$ValueSvoistva->Ид : '';
			$value_svoistva_tovar = (isset($ValueSvoistva->Значение)) ? (string)$ValueSvoistva->Значение : '';
			$value_svoistva_tovar = formatString($value_svoistva_tovar, 1);
			$type_value_svoistva = (isset($property_type_array[$id_svoistva_tovar])) ? (string)$property_type_array[$id_svoistva_tovar] : 'Строка';
			if ((!empty($value_svoistva_tovar)) and ($value_svoistva_tovar <> '00000000-0000-0000-0000-000000000000') and ($type_value_svoistva <> 'Справочник')) {		
				//проверка на запрещенные свойства
				$text_query = array();
				foreach ($StopNameCreateSvoistvaArray as $StopNameCreateSvoistva){
					$text_query[] = "ad.name = '".$StopNameCreateSvoistva."'";
				}
				if (!empty($text_query)){
					if (count($text_query)== 1 ){
						$text_query_final = $text_query[0];	
					}else{
						$text_query_final = implode(' OR ', $text_query);
					}
				}else{
					$text_query_final = "ad.name = 'Производитель'";
				}
				
				$attribute_create = true;
				$attributeStopCreateArray = $db->query ( "SELECT a.attribute_1c_id as attribute_1c_id, a.attribute_id as attribute_id FROM " . DB_PREFIX ."attribute AS a LEFT OUTER JOIN " . DB_PREFIX ."attribute_description AS ad ON a.attribute_id = ad.attribute_id  where ad.language_id = '".LANGUAGE_ID."' and (".$text_query_final.")" );
				if ($attributeStopCreateArray->num_rows) {
					foreach ( ($attributeStopCreateArray->rows) as $attributeStopCreate ){
						$attribute_1c_id = $attributeStopCreate['attribute_1c_id'];
						if ($id_svoistva_tovar == $attribute_1c_id){
							$attribute_create = false;
						}
					}
				}			
				if ($attribute_create == true){
					$SvoistvaArray = $db->query ( "SELECT attribute_id FROM " . DB_PREFIX . "attribute WHERE attribute_1c_id ='" .$id_svoistva_tovar. "'" );
					if ($SvoistvaArray->num_rows) { 			
						$attribute_id = $SvoistvaArray->row['attribute_id'];
						insertAttributeToProduct($attribute_id, $value_svoistva_tovar, $product_id);		
					}
				}						
			}	
		}
	}
}

function TovarArrayFill($xml, $xml_all_svoistva, $CatalogContainsChanges, $all_product_count, $FilePart = 0) {
global $db;
global $CategoryArray;
global $FilenameUpload;
global $ThisPage;
global $posix;
$property_type_array = array();
$product_count = 0;
$product_count_continue = 0;
$time_start = strtotime(date('Y-m-d H:i:s'));
$type_upload = 'product';
HeartBeat::setСountElementAll($all_product_count);
	
	if (!isset ($xml->Товар)){
		write_log("ERROR! no products");
		print "no products!";
		return;
	}

	if (VM_SVOISTVA_1C == 1){ //создаем настраиваемые поля
		$property_type_array = CreateNewCustoms($xml_all_svoistva);	
	}

	$last_element_upload = HeartBeat::getLastElementUpload($FilenameUpload);
	//СЧИТЫВАЕМ ТОВАРЫ
	foreach ($xml->Товар as $Tovar){		
		$product_count++;
		HeartBeat::setСountElementNow($product_count);
		
		$IdTovar1c = (isset($Tovar->Ид)) ? (string)$Tovar->Ид : '';
		$HeartBeatStatus = HeartBeat::getNext($FilenameUpload, $FilePart, $ThisPage, $posix, $type_upload, $IdTovar1c, $last_element_upload);
		if ($HeartBeatStatus == 'next'){
			$product_count_continue++;
			continue;
		}
		progressLoad($product_count,$product_count_continue, $FilePart, $all_product_count, $time_start, strtotime(date('Y-m-d H:i:s')), "товаров");
		if ($HeartBeatStatus == 'false'){
			exit();
		}
			
		$Artikul = (isset($Tovar->Артикул)) ? (string)$Tovar->Артикул : 'Не указано';
		$Artikul = !empty($Artikul) ? $Artikul : 'Не указано';
		$Name = (isset($Tovar->Наименование)) ? (string)$Tovar->Наименование : 'Наименование не заполнено';
		$Opisanie = (isset($Tovar->Описание)) ? (string)$Tovar->Описание : '';
		$Code = (isset($Tovar->Код)) ? (string)$Tovar->Код : '';
		$Status = (isset($Tovar->Статус)) ? (string)$Tovar->Статус : '';
	
		$Izgotovitel = ''; //обнуляем поле Производитель
		if (isset($Tovar->Изготовитель->Наименование)){
			$Izgotovitel = (string)$Tovar->Изготовитель->Наименование; //для УТ 11 (Загрузка производителя через реквизит "Производитель")
		}else {
			$Izgotovitel = get_manufacturer_in_svoistvo($Tovar, $xml_all_svoistva);
		}		
		$mark_delete = false;
		if ((isset($Tovar['Статус'])) and ($Tovar['Статус'] == "Удален")){
			$mark_delete = true;
		}
		if ($Status == "Удален"){
			$mark_delete = true;
		}
						
		//считываем реквизит товара Полное наименование, Вес и ОписаниеВФорматеHTML
		$NameFull = '';
		$Length = get_value_in_svoistvo($Tovar, $xml_all_svoistva, 'Длина');
		$Width =  get_value_in_svoistvo($Tovar, $xml_all_svoistva, 'Ширина');
		$Height = get_value_in_svoistvo($Tovar, $xml_all_svoistva, 'Высота');
		$Weight = get_value_in_svoistvo($Tovar, $xml_all_svoistva, 'Вес');
		
		$ValueRequisite = '';
		if (isset($Tovar->ЗначенияРеквизитов->ЗначениеРеквизита)){
			$ValueRequisite = $Tovar->ЗначенияРеквизитов->ЗначениеРеквизита;
		}
		if (isset($Tovar->ЗначениеРеквизита)){
			$ValueRequisite = $Tovar->ЗначениеРеквизита;
		}
		if (!empty($ValueRequisite)){
			foreach ($ValueRequisite as $RekvizitData){	
				if (isset($RekvizitData->Наименование)){
					if ($RekvizitData->Наименование	==	"Наименование"){
						$NameFull = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if ($RekvizitData->Наименование	==	"Полное наименование"){
						$NameFull = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if ($RekvizitData->Наименование	==	"ОписаниеВФорматеHTML"){
						$OpisanieHTML =	(isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
						$Opisanie .= $OpisanieHTML; 
					}
					if ($RekvizitData->Наименование	==	"Вес"){
						$Weight = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if ($RekvizitData->Наименование	==	"Длина"){
						$Length = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if ($RekvizitData->Наименование	==	"Ширина"){
						$Width = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if ($RekvizitData->Наименование	==	"Высота"){
						$Height = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if ($RekvizitData->Наименование	==	"Код"){
						$Code = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
					}
					if (($RekvizitData->Наименование ==	"Производитель") or ($RekvizitData->Наименование ==	"Изготовитель")){
						$Manufacturer = (isset($RekvizitData->Значение)) ? (string)$RekvizitData->Значение : '';
						$Izgotovitel = (empty($Izgotovitel)) ? $Manufacturer : $Izgotovitel;
					}
				}
			}
		}

		//экранируем полученные данные о товаре
		$Name = formatString($Name, 1);
		$NameFull =formatString($NameFull, 1);
		$Artikul = formatString($Artikul, 1);
		if (VM_DESC_FILTER == 1){
			$Opisanie = nl2br($Opisanie);
			$Opisanie = formatString($Opisanie);
		}
		$Izgotovitel = formatString($Izgotovitel);
		$Code = formatString($Code);
		
		//проверяем на числовое значение
		$Weight = str_replace(",",".",$Weight); //замена запятых на точку
		$Weight = round((float)preg_replace("/[^0-9\.]/", '', $Weight), 2);
		$Length = str_replace(",",".",$Length); //замена запятых на точку
		$Length = round((float)preg_replace("/[^0-9\.]/", '', $Length), 2);
		$Width  = str_replace(",",".",$Width); //замена запятых на точку
		$Width  = round((float)preg_replace("/[^0-9\.]/", '', $Width), 2);
		$Height = str_replace(",",".",$Height); //замена запятых на точку
		$Height = round((float)preg_replace("/[^0-9\.]/", '', $Height), 2);

		if ((VM_ALLNAMEUSE == 1) and (!empty($NameFull))){
			$Name = $NameFull; 
		}
		if (($Artikul == 'Не указано') and (!empty($Code))){
			$Artikul = $Code; 
		}
		//отбираем позиции с ИД 
		$product_id_query = $db->query ( "SELECT product_id FROM " . DB_PREFIX ."product where product_1c_id = '" . $IdTovar1c . "'" );				
		if (!($product_id_query->num_rows)) {
			//товара с таким ИД нет
			if (VM_CREATE_PRODUCT == 0){
				continue;
			}

			if (($mark_delete == true) and (VM_DELETE_MARK_PRODUCT == 'DELETE')){
				continue;
			}
			$SrcImgName = '';
			$OutImgName = '';
			$product_id = NewProduct( $Artikul, $Name, $NameFull, $SrcImgName, $OutImgName,$IdTovar1c, $Opisanie, $Izgotovitel, $Weight, $Length, $Width, $Height,	$Code, $mark_delete);
			
			//загружаем картинки
			if (IMAGE_LOAD == 1) {
				loadImagesForProduct($Tovar, $Name, $product_id);
			}
			
			//читаем свойства
			if (VM_SVOISTVA_1C == 1){
				ReadCustoms($Tovar, $product_id, $property_type_array);
			}

			// # Связываем товар и группу 
			if (VM_FOLDER == 1){
				foreach ($Tovar->Группы as $GroupsData){
					$IdGroup1c = (isset($GroupsData->Ид)) ? (string)$GroupsData->Ид : '';
					$result_group_1c = $db->query ( "SELECT category_id FROM " . DB_PREFIX ."category where category_1c_id = '" . $IdGroup1c . "'" );
					if ($result_group_1c->num_rows ) {
						$IdGroupVm = $result_group_1c->row['category_id'];
					}else{
						$IdGroupVm = 0;
					}
					if (isset($IdGroupVm)){
						NewProductsXref($IdGroupVm, $product_id);
					}
				}	
			}
		}else{
			//товар в базе есть, обновляем его имя и полное имя и описание 
			$product_id = (int)$product_id_query->row['product_id'];			
			if (($mark_delete == true) and (VM_DELETE_MARK_PRODUCT == 'DELETE')){
				DeleteProduct($product_id, $Name);
				continue; //завершаем обработку товара
			}
			
			$updateProductFieldArray = array();
			$updateProductDescriptionFieldArray = array();
			
			if (($mark_delete == true) and (VM_DELETE_MARK_PRODUCT == 'HIDE')){
				$updateProductFieldArray['status'] = '0';
			}
			if ((VM_PRODUCT_VIEW_PRICE0 == 0) or (VM_PRODUCT_VIEW_COUNT0 == 0)){ 
				//отключаем отображение товара, до момента установки цены или остатка на товар
				$updateProductFieldArray['status'] = '0';
			}		
			if (VM_UPDATE_ARTIKUL == 1){
				$updateProductFieldArray['sku'] = $Artikul;
				$updateProductFieldArray['model'] = $Artikul;
				$updateProductFieldArray['upc'] = $Code;
			}
			$updateProductFieldArray['date_modified'] = date('Y-m-d H:i:s');

			//обновляем доп. поля: длина, ширина, высота, вес
			if ((!empty($Weight)) and ($Weight > 0)){
				$updateProductFieldArray['weight'] = $Weight;
			}
			if ((!empty($Length)) and ($Length > 0)){
				$updateProductFieldArray['length'] = $Length;
			}
			if ((!empty($Width)) and ($Width > 0)){
				$updateProductFieldArray['width']  = $Width;
			}
			if ((!empty($Height)) and ($Height > 0)){
				$updateProductFieldArray['height'] = $Height;
			}

			if (VM_UPDATE_NAME == 1){
				$updateProductDescriptionFieldArray['name'] = $Name;
			}
			if (VM_UPDATE_DESC == 1){
				$updateProductDescriptionFieldArray['description'] = $Opisanie;
			}
			if (VM_UPDATE_META == 1){
				if (VERSION_OC15 == 0){
					$updateProductDescriptionFieldArray['meta_title'] = "Купить ".$Name;
				}
				$updateProductDescriptionFieldArray['meta_description'] = "Купить ".$Name;
				$words = explode(' ', $Name);
				$metakey = implode(',', $words) . ',' . $Name;
				$updateProductDescriptionFieldArray['meta_keyword'] = $metakey;
			}
			
			$text_query = array();
			foreach ($updateProductFieldArray as $updateProductFieldKey => $updateProductFieldValue){
				$text_query[] = $updateProductFieldKey." = '".$updateProductFieldValue."'";
			}
			if (!empty($text_query)){
				if (count($text_query)== 1 ){
					$text_query_final = $text_query[0];	
				}else{
					$text_query_final = implode(' , ', $text_query);
				}
				$product_update = $db->query ( "UPDATE ". DB_PREFIX . "product SET ".$text_query_final." WHERE product_id ='".$product_id."'" );
			}
			unset($text_query);
			foreach ($updateProductDescriptionFieldArray as $updateProductDescriptionFieldKey => $updateProductDescriptionFieldValue){
				$text_query[] = $updateProductDescriptionFieldKey." = '".$updateProductDescriptionFieldValue."'";
			}
			if (!empty($text_query)){
				if (count($text_query)== 1 ){
					$text_query_final = $text_query[0];	
				}else{
					$text_query_final = implode(' , ', $text_query);
				}
				$product_update = $db->query ( "UPDATE ". DB_PREFIX . "product_description SET ".$text_query_final." WHERE product_id = '".$product_id."' and language_id = '".LANGUAGE_ID."'");
			}
			unset($updateProductFieldArray, $updateProductDescriptionFieldArray);
			
			//обновляем производителей
			if ((VM_MANUFACTURER_1C == 1) and (VM_UPDATE_MANUFACTURE == 1)){
				UpdateManufactureProduct($Izgotovitel, $product_id);
			}
			
			//обновляем картинки
			if (VM_UPDATE_IMAGE == 1){
				if (UT_10_3 == 0) { // удаляем все картинки у товара, если УТ 11
					$product_image_query  = $db->query ( "SELECT product_image_id FROM " . DB_PREFIX . "product_image where product_id = '" . $product_id . "'" );
					if ($product_image_query->num_rows) {							
						$product_image_delete  = $db->query ("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" .$product_id. "'");
					}
					$image_update = $db->query("UPDATE " . DB_PREFIX . "product SET image='' where product_id ='".$product_id."'");
				}
				
				if (($CatalogContainsChanges == 'false') AND (UT_10_3 == 1)) { //удаляем картинки, если УТ 10.3
					$product_image_query  = $db->query ( "SELECT product_image_id FROM " . DB_PREFIX . "product_image where product_id = '" . $product_id . "'" );
					if ($product_image_query->num_rows) {							
						$product_image_delete  = $db->query ("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" .$product_id. "'");
					}
					$image_update = $db->query("UPDATE " . DB_PREFIX . "product SET image='' where product_id ='".$product_id."'");
				}
				
				if (($CatalogContainsChanges == 'true') AND (UT_10_3 == 1)) {
					//не удаляем картинки, если УТ 10.3
				}
				loadImagesForProduct($Tovar, $Name, $product_id);				
			}
			
			//читаем свойства
			if ((VM_SVOISTVA_1C == 1) and (VM_UPDATE_SVOISTVA == 1)){				
				$delete_attribute = $db->query (  "DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id ='".$product_id."' AND language_id = '".LANGUAGE_ID."'");	
				ReadCustoms($Tovar, $product_id, $property_type_array);	
			}
			if (VM_UPDATE_CATEGORY == 1){
				foreach ($Tovar->Группы as $GroupsData){
						$IdGroup1c = (isset($GroupsData->Ид)) ? (string)$GroupsData->Ид : '';
						$result_group_1c = $db->query ( "SELECT category_id FROM " . DB_PREFIX ."category where category_1c_id = '" . $IdGroup1c . "'" );
						if ($result_group_1c->num_rows ) {
							$IdGroupVm = $result_group_1c->row['category_id'];
						}else{
							$IdGroupVm = 0;
						}
						if (isset($IdGroupVm)){
							NewProductsXref($IdGroupVm, $product_id);
						}
				}
			}

			$query_store_id  = $db->query ( "SELECT * FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "' AND store_id = '".STORE_ID."'" );
			if (!$query_store_id->num_rows) {
				$ins = new stdClass ();
				$ins->product_id = $product_id;
				$ins->store_id = STORE_ID;
				insertObject ( "" . DB_PREFIX ."product_to_store", $ins  ) ;
			}			
		}
	}
	HeartBeat::clearElementUploadInStatusProgress($FilenameUpload, $FilePart, $type_upload);	
}

function DeleteProduct($product_id, $product_name){
global $db;

	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = ".$product_id."");
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_option_value_1c WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = ".$product_id."");
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product WHERE product_id = ".$product_id."");	
	$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = ".$product_id."");
	//$product_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_to_layout");	
	write_log("На сайте по данным 1С был удален товар: ".$product_name." (".$product_id.")");
}

function get_manufacturer_in_svoistvo($Tovar, $xml_all_svoistva){
		
	$Izgotovitel = '';	
	$PropertyStd = '';
	if (isset($xml_all_svoistva->Свойство)) {
		$PropertyStd = $xml_all_svoistva->Свойство;
	}
	if (isset($xml_all_svoistva->СвойствоНоменклатуры)) {
		$PropertyStd = $xml_all_svoistva->СвойствоНоменклатуры;
	}
		
	if (!empty($PropertyStd)) {
		foreach ($PropertyStd as $SvArrPro){
			$id_svoistvo = (isset($SvArrPro->Ид)) ? (string)$SvArrPro->Ид : '';
			$name_svoistvo = (isset($SvArrPro->Наименование)) ? (string)$SvArrPro->Наименование : '';
			$IDArrayProizvoditel [$name_svoistvo] ['id_svoistvo'] = $id_svoistvo;
			if (isset($SvArrPro->ВариантыЗначений->Справочник)){
				foreach ($SvArrPro->ВариантыЗначений->Справочник as $VariantiZnachenii ){
					$id_znachenia = (isset($VariantiZnachenii->ИдЗначения)) ? (string)$VariantiZnachenii->ИдЗначения : '';
					$name_znachenia = (isset($VariantiZnachenii->Значение)) ? (string)$VariantiZnachenii->Значение : '';
					$SvoistvaArrayProizvoditel [$id_svoistvo] [$name_svoistvo] [$id_znachenia] ['name_znachenia'] = $name_znachenia;
				}
			}
		}
		
		if (isset($Tovar->ЗначенияСвойств->ЗначенияСвойства)){
			foreach ($Tovar->ЗначенияСвойств->ЗначенияСвойства as $ZnachSvoistvaTovar){
				$id_svoistvo_tovar = (isset($ZnachSvoistvaTovar->Ид)) ? (string)$ZnachSvoistvaTovar->Ид : '';
				$znach_svoistvo_tovar = (isset($ZnachSvoistvaTovar->Значение)) ? (string)$ZnachSvoistvaTovar->Значение : '';
				$SvoistvaTovara [$id_svoistvo_tovar]['znach_svoistvo_tovar'] = $znach_svoistvo_tovar;
			}
			if (isset($IDArrayProizvoditel ['Производитель'] ['id_svoistvo'])){
				$id_proizvoditelya = $IDArrayProizvoditel ['Производитель'] ['id_svoistvo'];
				if (isset($SvoistvaTovara [$id_proizvoditelya]['znach_svoistvo_tovar'])){
					$id_proizvoditelya_tovar = $SvoistvaTovara [$id_proizvoditelya]['znach_svoistvo_tovar'];
					if (isset($SvoistvaArrayProizvoditel [$id_proizvoditelya] ['Производитель'] [$id_proizvoditelya_tovar] ['name_znachenia'])){
						$id_svoistvo_proizvoditel_naiti = $SvoistvaArrayProizvoditel [$id_proizvoditelya] ['Производитель'] [$id_proizvoditelya_tovar] ['name_znachenia'];
						$Izgotovitel =  $id_svoistvo_proizvoditel_naiti;
					}else{
						if (UT_10_3 == 1){
							$Izgotovitel = $id_proizvoditelya_tovar;
						}
					}
				}
			}
		}
			return $Izgotovitel;
	}else{
		return $Izgotovitel;
	}		
}

function get_value_in_svoistvo($Tovar, $xml_all_svoistva, $PropertyName){
		
	$ValueProperty = '';	
	$PropertyStd = '';
	$is_string = false;
	if (isset($xml_all_svoistva->Свойство)) {
		$PropertyStd = $xml_all_svoistva->Свойство;
	}
	if (isset($xml_all_svoistva->СвойствоНоменклатуры)) {
		$PropertyStd = $xml_all_svoistva->СвойствоНоменклатуры;
	}
		
	if (!empty($PropertyStd)) {
		foreach ($PropertyStd as $SvArrPro){
			$id_svoistvo = (isset($SvArrPro->Ид)) ? (string)$SvArrPro->Ид : '';
			$name_svoistvo = (isset($SvArrPro->Наименование)) ? (string)$SvArrPro->Наименование : '';
			$IdArrayProperty [$name_svoistvo] ['id_svoistvo'] = $id_svoistvo;
			if (isset($SvArrPro->ВариантыЗначений->Справочник)){
				foreach ($SvArrPro->ВариантыЗначений->Справочник as $VariantiZnachenii ){
					$id_znachenia = (isset($VariantiZnachenii->ИдЗначения)) ? (string)$VariantiZnachenii->ИдЗначения : '';
					$name_znachenia = (isset($VariantiZnachenii->Значение)) ? (string)$VariantiZnachenii->Значение : '';
					$SvoistvaArrayProperty [$id_svoistvo] [$name_svoistvo] [$id_znachenia] ['name_znachenia'] = $name_znachenia;
				}
			}else{
				$is_string = true;
			}
		}
		
		if (isset($Tovar->ЗначенияСвойств->ЗначенияСвойства)){
			foreach ($Tovar->ЗначенияСвойств->ЗначенияСвойства as $ZnachSvoistvaTovar){
				$id_svoistvo_tovar = (isset($ZnachSvoistvaTovar->Ид)) ? (string)$ZnachSvoistvaTovar->Ид : '';
				$znach_svoistvo_tovar = (isset($ZnachSvoistvaTovar->Значение)) ? (string)$ZnachSvoistvaTovar->Значение : '';
				$SvoistvaTovara [$id_svoistvo_tovar]['znach_svoistvo_tovar'] = $znach_svoistvo_tovar;
			}
			if (isset($IdArrayProperty [$PropertyName] ['id_svoistvo'])){
				$id_property = $IdArrayProperty [$PropertyName] ['id_svoistvo'];
				if (isset($SvoistvaTovara [$id_property]['znach_svoistvo_tovar'])){
					$id_property_tovar = $SvoistvaTovara [$id_property]['znach_svoistvo_tovar'];
					if (isset($SvoistvaArrayProperty [$id_property] [$PropertyName] [$id_property_tovar] ['name_znachenia'])){
						$id_svoistvo_search = $SvoistvaArrayProperty [$id_property] [$PropertyName] [$id_property_tovar] ['name_znachenia'];
						$ValueProperty =  $id_svoistvo_search;
					}else{
						if ($is_string == true){
							$ValueProperty = $id_property_tovar;
						}
					}
				}
			}
		}
			return $ValueProperty;
	}else{
		return $ValueProperty;
	}		
}

function createAttributeGroup($name_attribute_group){
global $db;
global $languages;
	
	$name_attribute_group = formatString($name_attribute_group);	
	$attribute_group_array = $db->query ( "SELECT attribute_group_id FROM " . DB_PREFIX . "attribute_group_description WHERE name ='".$name_attribute_group."' and language_id = " . LANGUAGE_ID . "" );
	if ($attribute_group_array->num_rows){
		$attribute_group_id = (int)$attribute_group_array->row['attribute_group_id'];
	}else{
		$ins = new stdClass ();
		$ins->attribute_group_id = NULL;
		$ins->sort_order = '0';
		insertObject ( "" . DB_PREFIX ."attribute_group", $ins, 'attribute_group_id'  ) ;
		$attribute_group_id = $ins->attribute_group_id;

		foreach ($languages as $lang) {		
			$ins = new stdClass ();
			$ins->attribute_group_id = (int)$attribute_group_id;
			$ins->language_id = $lang['language_id'];
			$ins->name = formatString($name_attribute_group);
			insertObject ( "" . DB_PREFIX ."attribute_group_description", $ins ) ;	
		}	
	}
	return $attribute_group_id;
}

function createAttribute($name_attribute, $attribute_1c_id = ''){
global $db;	

	$name_attribute = formatString($name_attribute);	
	$attribute_group_id = createAttributeGroup(VM_NAME_FEATURES);

	if (!empty($attribute_1c_id)){
		$attribute_query = $db->query ( "SELECT attribute_id FROM " . DB_PREFIX . "attribute WHERE attribute_1c_id ='" .$attribute_1c_id. "'" );
		if ($attribute_query->num_rows) { 
			$attribute_id = (int)$attribute_query->row['attribute_id'];	
		}else{		
			$ins = new stdClass ();
			$ins->attribute_id = NULL;
			$attribute_group_id = createAttributeGroup(VM_NAME_FEATURES);
			$ins->attribute_group_id = (int)$attribute_group_id;
			$ins->sort_order = '0';
			$ins->attribute_1c_id = $attribute_1c_id;
			insertObject ( "" . DB_PREFIX ."attribute", $ins, 'attribute_id'  ) ;
			$attribute_id = $ins->attribute_id;
																
			$ins = new stdClass ();
			$ins->attribute_id = (int)$attribute_id;
			$ins->language_id = LANGUAGE_ID;
			$ins->name = $name_attribute;
			insertObject ( "" . DB_PREFIX ."attribute_description", $ins ) ;												
		}
	}else{
		$attribute_query = $db->query ( "SELECT attribute_id FROM " . DB_PREFIX . "attribute_description WHERE name ='".$name_attribute."' and language_id ='".LANGUAGE_ID."'");
		if ($attribute_query->num_rows) {
			$attribute_id = (int)$attribute_query->row['attribute_id'];
		}else{		
			$ins = new stdClass ();
			$ins->attribute_id = NULL;							
			$ins->attribute_group_id = (int)$attribute_group_id;
			$ins->sort_order = '0';
			$ins->attribute_1c_id = $attribute_1c_id;
			insertObject ( "" . DB_PREFIX ."attribute", $ins, 'attribute_id'  ) ;
			$attribute_id = $ins->attribute_id;
			
			$ins = new stdClass ();
			$ins->attribute_id = (int)$attribute_id;
			$ins->language_id = LANGUAGE_ID;
			$ins->name = $name_attribute;
			insertObject ( "" . DB_PREFIX ."attribute_description", $ins ) ;
		}	
	}	
	return $attribute_id;
}

function insertAttributeToProduct($attribute_id, $value_attribute, $product_id){
global $db;	

	$value_attribute = formatString($value_attribute);		
	$product_attribute_query = $db->query ( "SELECT * FROM " . DB_PREFIX . "product_attribute WHERE product_id = '".(int)$product_id."' AND  attribute_id ='".(int)$attribute_id."' AND language_id = '".LANGUAGE_ID."'" );
	if ($product_attribute_query->num_rows) { 					
		$product_attribute_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_attribute SET text='".$value_attribute."' where product_id='".(int)$product_id."' AND attribute_id ='".(int)$attribute_id."' AND language_id = '".LANGUAGE_ID."'");						
	}else{		
		$ins = new stdClass ();
		$ins->product_id = (int)$product_id;
		$ins->attribute_id = (int)$attribute_id;
		$ins->language_id =  LANGUAGE_ID;
		$ins->text = $value_attribute;
		insertObject ( "" . DB_PREFIX ."product_attribute", $ins ) ;
	}
}

function update_url_alias ($id, $Name, $query_part){
global $db;
global $languages;

		$ModelSeourlGenerate = new ModuleSeoUrlGenerator();
		if (VERSION_OC3 == 1){
			$table = DB_PREFIX."seo_url";
			foreach ($languages as $lang) {
				if (count($languages) > 1){
					$Name = $Name.'-'.$lang['code'];
				}
				$result = $ModelSeourlGenerate->seoUrlGenerateAjax($query_part, $Name, $table, true);
				$url_alias_query = $db->query ( "SELECT seo_url_id FROM " . DB_PREFIX . "seo_url where query='".$query_part."=".$id."'");
				if ($url_alias_query->num_rows) {
					$url_alias_id = (int)$url_alias_query->row['seo_url_id'];
					$update_url_alias = $db->query ( "UPDATE " . DB_PREFIX . "seo_url SET  keyword='".$result."' where seo_url_id='".(int)$url_alias_id."' and language_id ='".(int)$lang['language_id']."'");
				}else{
					$ins = new stdClass ();
					$ins->seo_url_id = NULL;
					$ins->store_id = STORE_ID;
					$ins->language_id = $lang['language_id'];
					$ins->query = $query_part."=".$id;
					$ins->keyword = $result;
					insertObject ( "" . DB_PREFIX ."seo_url", $ins ) ;
				}
			}
		}else{
			$table = DB_PREFIX."url_alias";
			$result = $ModelSeourlGenerate->seoUrlGenerateAjax($query_part, $Name, $table, true);
			$url_alias_query = $db->query ( "SELECT url_alias_id FROM " . DB_PREFIX . "url_alias where query='".$query_part."=".$id."'");
			if ($url_alias_query->num_rows) {
				$url_alias_id = (int)$url_alias_query->row['url_alias_id'];
				$update_url_alias = $db->query ( "UPDATE " . DB_PREFIX . "url_alias SET  keyword='".$result."' where url_alias_id='".(int)$url_alias_id."'");
			}else{
				$ins = new stdClass ();
				$ins->url_alias_id = NULL;
				$ins->query = $query_part."=".$id;
				$ins->keyword = $result;
				insertObject ( "" . DB_PREFIX ."url_alias", $ins ) ;
			}
		}
}

function update_quantity_product_after_features($ProductQuantityArray){
global $db;	
	//суммируем количество характеристик номенклатуры с количеством номенклатуры
	foreach($ProductQuantityArray as $key => $value){ 		
		$product_id = $key;	
		$all_quantity = 0;
		$product_quantity_query = $db->query ( "SELECT quantity FROM " . DB_PREFIX . "product_option_value where product_id='".(int)$product_id."'");
		foreach (($product_quantity_query->rows) as $quantity) {
			$all_quantity = $all_quantity + $quantity['quantity'];
		}
		$date_modified = date('Y-m-d H:i:s');
		$product_quantity_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET  quantity='".$all_quantity."', date_modified='".$date_modified."' where product_id='".(int)$product_id."'");	

		if ((isset($all_quantity)) and (VM_PRODUCT_VIEW_COUNT0 == 0)){
			$status = '0';
			if($all_quantity > 0){
				$status = '1';
			}
			$product_count_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET status='".$status."' where product_id='". (int) $product_id."'"); 
		}
	} 
}

function update_price_and_quantity_features($offers, $FeaturesArray) {
global $db;
global $FilenameUpload;
$ProductQuantityArray = array();
$ProductArray = array();
$product_quantity = 0;

	if (!isset ($offers->Предложение)){
		return "no feature offers!";
	}

	//установка цен на номенклатуру
	if (VM_PRICE_PARENT_FEATURES == 1){
		update_parent_price_features ($offers, $FeaturesArray);
	}
	//установка цен на характеристики номенклатуры
	foreach ( $offers->Предложение as $product_features ) { 
		$features_id_1c =(string)$product_features->Ид;
		$pos = strrpos($features_id_1c, "#");
		if ($pos === false) { 
				//не найдена характерстика номенклатуры 
		}else{
			if (!empty($FeaturesArray[$features_id_1c]['product_option_value_id'])){
				$product_option_value_id = $FeaturesArray[$features_id_1c]['product_option_value_id'];
				
				//загружаем текущие остатки для характеристик номенклатуры
				$pos_rests = strrpos($FilenameUpload, "rests");
				$pos_prices = strrpos($FilenameUpload, "prices");
				if ($pos_prices === false){
					$features_in_stock = getRests($product_features);	
					$features_quantity_query  = $db->query ( "SELECT product_id FROM " . DB_PREFIX . "product_option_value where product_option_value_id='".(int)$product_option_value_id."'"); 
					if ($features_quantity_query->num_rows) {
						$product_id = (int)$features_quantity_query->row['product_id'];
						if (VM_UPDATE_COUNT == 1){ 	
							$features_quantity_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value SET  quantity='".$features_in_stock."' where product_option_value_id='".(int)$product_option_value_id."'");
						}
						if (!isset($ProductQuantityArray[$product_id]['quantity'])){
							$ProductQuantityArray[$product_id]['quantity'] = $features_in_stock;
						}else{
							$ProductQuantityArray[$product_id]['quantity'] = $ProductQuantityArray[$product_id]['quantity'] + $features_in_stock;
						}				
					}
				}
				
				//загружаем цены для характеристик номенклатуры
				if (VM_UPDATE_PRICE == 1){
					if ((isset($product_features->Цены->Цена)) and (isset($product_id))) {
						foreach ( $product_features->Цены->Цена as $price_data) { 
							$mPrice = (float)$price_data->ЦенаЗаЕдиницу;
							$mCurr_data =(string)$price_data->Валюта;
							$mCurr = getRightNameCurrency($mCurr_data);	
							$mCurr_query  = $db->query ( "SELECT currency_id FROM " . DB_PREFIX . "currency where code = '" . $mCurr . "'" );	
							if ($mCurr_query->num_rows) {
								//ничего не делаем. валюта есть	в базе
							}else{
								$ins = new stdClass ();
								$ins->currency_id = NULL;
								$ins->title = $mCurr;
								$ins->code = $mCurr;
								$ins->symbol_left = "";
								$ins->symbol_right = $mCurr;
								$ins->decimal_place = $mCurr;
								$ins->value = "1";
								$ins->status = "1";
								$ins->date_modified = date('Y-m-d H:i:s');
								insertObject ( "" . DB_PREFIX ."currency", $ins) ;		
							}		
							$shopper_group_id_1c =(string)$price_data->ИдТипаЦены; 							
							if (VM_PRICE_1C == 0){
								$product_id_query  = $db->query ( "SELECT price FROM " . DB_PREFIX . "product where product_id = '" . $product_id. "'" );
								if ($product_id_query->num_rows) {
									$product_price = (float)$product_id_query->row['price'];
									if (VM_FEATURES_1C_PRICE == 0){
										$feature_price = $product_price - $mPrice;
									}else{
										$feature_price = $mPrice;
									}
									if($feature_price < 0) {
										$feature_price_new = abs($feature_price);
										$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value SET price='".$feature_price_new."' , price_prefix='+' where product_option_value_id='".(int)$product_option_value_id."'"); 			
									}elseif($feature_price >= 0) {
										$feature_price_new = abs($feature_price);
										$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value SET price='".$feature_price_new."' , price_prefix='-' where product_option_value_id='".(int)$product_option_value_id."'"); 
									}	
								}
							}else{
								$name_price = VM_TYPE_PRICE_1C ;
								$customer_group_id_query  = $db->query (  "SELECT customer_group_id FROM " . DB_PREFIX . "customer_group_description where name = '" .$name_price . "' and customer_group_1c_id = '" .$shopper_group_id_1c . "'");
								if ($customer_group_id_query->num_rows) {
									$shopper_group_id_1c_type_price = $shopper_group_id_1c;
									$product_id_query  = $db->query ( "SELECT price FROM " . DB_PREFIX . "product where product_id = '" . $product_id. "'" );
									if ($product_id_query->num_rows) {			
										$product_price = (float)$product_id_query->row['price'];
										if (VM_FEATURES_1C_PRICE == 0){
											$feature_price = $product_price - $mPrice;
										}else{
											$feature_price = $mPrice;
										}	
										if($feature_price < 0) {
											$feature_price_new = abs($feature_price);
											$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value SET price='".$feature_price_new."' , price_prefix='+' where product_option_value_id='".(int)$product_option_value_id."'");	
										}elseif($feature_price >= 0) {
											$feature_price_new = abs($feature_price);
											$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value SET price='".$feature_price_new."' , price_prefix='-' where product_option_value_id='".(int)$product_option_value_id."'"); 
										}			
									}			
								}
							}			
						}
					}
				}	
			}
		}
	}
	if (VM_COUNT_PARENT_FEATURES == 1){
		update_quantity_product_after_features ($ProductQuantityArray);
	}
}

function update_parent_price_features($offers,$FeaturesArray) {
global $db;
$ProductsArray = array();
$FeatureProductArray = array();
$shopper_group_id_1c_array = array();

	foreach ( $offers->Предложение as $product_features ) { 
		$features_id_1c = (isset($product_features->Ид)) ? (string)$product_features->Ид : '';
		$pos = strrpos($features_id_1c, "#");
		if ($pos === false) { 
				//не найдена характерстика номенклатуры 
		}else{
			if (!empty($FeaturesArray[$features_id_1c]['product_option_value_id'])){
				$product_option_value_id = $FeaturesArray[$features_id_1c]['product_option_value_id'];
				$features_quantity_query  = $db->query ( "SELECT product_id FROM " . DB_PREFIX . "product_option_value where product_option_value_id='".(int)$product_option_value_id."'"); 
				if ($features_quantity_query->num_rows) {
					$product_id = (int)$features_quantity_query->row['product_id'];
					$ProductsArray[] = $product_id;
				}
				if (isset($product_features->Цены->Цена)) {
					foreach ( $product_features->Цены->Цена as $price_data) 
					{ 
						$mPrice = (isset($price_data->ЦенаЗаЕдиницу)) ? (float)$price_data->ЦенаЗаЕдиницу : 0;
						$shopper_group_id_1c = (isset($price_data->ИдТипаЦены)) ? (string)$price_data->ИдТипаЦены : '';
						$shopper_group_id_1c_array[] = $shopper_group_id_1c;
						if ($mPrice > 0){
							$FeatureProductArray[][$product_id][$shopper_group_id_1c] = $mPrice;
						}		
					}
				}
			}
		}
	}

	$shopper_group_id_1c_sale = '';
	$name_price_discount = VM_TYPE_PRICE_1C_SPECIAL;
	if (!empty($name_price_discount)){
		$customer_group_id_price_discount  = $db->query (  "SELECT customer_group_1c_id FROM " . DB_PREFIX . "customer_group_description where name = '".$name_price_discount."'");
		if ($customer_group_id_price_discount->num_rows) {
			$shopper_group_id_1c_sale = $customer_group_id_price_discount->row['customer_group_1c_id'];
		}
	}

	$new_array_product = array_unique($ProductsArray);
	foreach ($new_array_product AS $product_id) {				
		$array_price = array();
		$array_price_sale = array();
					
		foreach ($FeatureProductArray AS $FeatureIdProduct) {
			foreach ($FeatureIdProduct AS $product_id_feature => $value) {	
				foreach ($value AS $shopper_group_id_1c => $mPrice) {				
					if ($product_id_feature == $product_id){
						if ($shopper_group_id_1c == $shopper_group_id_1c_sale){
							$array_price_sale[]=$mPrice;
						}else{
							$array_price[]=$mPrice;	
						}
					}					
				}					
			}											
		}
		$array_price_update = array_unique($array_price);
		if (!empty($array_price_update)){
			$minimum_price = min($array_price_update);
		}else{
			$minimum_price = 0;
		}			
		
		if (VM_PRICE_1C == 0){
			$product_id_query  = $db->query ( "SELECT product_id FROM " . DB_PREFIX . "product where product_id = '" . ( int )$product_id . "'" );
			if ($product_id_query->num_rows) {					
				$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET price='".(float)$minimum_price."' where product_id='". (int)$product_id."'"); 
			}
		}else{
			$name_price = VM_TYPE_PRICE_1C ;
			foreach ($shopper_group_id_1c_array as $shopper_group_id_1c){
				$customer_group_id_query  = $db->query (  "SELECT customer_group_id FROM " . DB_PREFIX . "customer_group_description where name = '" .$name_price . "' and customer_group_1c_id = '" .$shopper_group_id_1c . "' ");
				if ($customer_group_id_query->num_rows) {
					$customer_group_id = $customer_group_id_query->row['customer_group_id'];				
					$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET price='".(float)$minimum_price."' where product_id='". (int) $product_id."'"); 
				}
							
				if (isset($customer_group_id)) {							
					$product_special_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" .( int ) $product_id. "' and customer_group_id = '" .( int )$customer_group_id. "'");						
										
					$ins = new stdClass ();
					$ins->product_special_id = NULL;
					$ins->product_id = ( int ) $product_id;
					$ins->customer_group_id = ( int ) $customer_group_id;
					$ins->priority = '0';
					$ins->price = (float)$minimum_price;
					$ins->date_start = "0000-00-00";
					$ins->date_end = "0000-00-00";
					insertObject ( "" . DB_PREFIX ."product_special", $ins) ;						
				}
			}
		}
		
		$array_price_sale = array_unique($array_price_sale);
		if (!empty($array_price_sale)){
			$minimum_price_sale = min($array_price_sale);
			
			//установка цены по акции 
			if ($minimum_price_sale > 0){
				$default_customer_group_id = VM_CONFIG_CUSTOMER_GROUP_DEFAULT;
				$product_special_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" .( int ) $product_id. "' and customer_group_id = '" .(int)$default_customer_group_id."'");									
				$ins = new stdClass ();
				$ins->product_special_id = NULL;
				$ins->product_id = (int) $product_id;
				$ins->customer_group_id = $default_customer_group_id;
				$ins->priority = '0';
				$ins->price = $minimum_price_sale;
				$ins->date_start = "0000-00-00";
				$ins->date_end = "0000-00-00";
				insertObject ( "" . DB_PREFIX ."product_special", $ins) ;
			}
		}

		if ((isset($minimum_price)) and ($minimum_price > 0) and (VM_PRODUCT_VIEW_PRICE0 == 0)){
			$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET status='1' where product_id='". (int) $product_id."'");
		}

		$date_modified = date('Y-m-d H:i:s');
		$product_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET date_modified='".$date_modified."' where product_id='". (int) $product_id."'");	
	}		
}

function FeaturesArrayFill($offers, $FilePart = 0) {
global $db;
global $TovarIdFeatureArray;
global $languages;
global $FilenameUpload;
global $ThisPage;
global $posix;
$FeaturesArray = array();
$ProductId1CArray = array();
$type_upload = 'feature';

	if (!isset($offers->Предложение)){
		return $FeaturesArray;
	}

	$is_true = true; //ВКЛЮЧЕНО
	if ($is_true == true){
		//чтение характеристик номенклатуры для очистки характеристик на сайте у товара 
		$ProductId1CArray = array();
		$FeatureId1CArray = array();
		$ProductAllArray = array();
		$FeatureAllArray = array();
		foreach ( $offers->Предложение as $product_features ) { 
			$features_id_1c = (isset($product_features->Ид)) ? (string)$product_features->Ид : '';
			$pos = strrpos($features_id_1c, "#");
			if ($pos === false) { 
				//не найдена характерстика номенклатуры 
				$ProductId1CArray[] = $features_id_1c; //проверим наличие опций у самого товара
			}else{
				$str=strpos($features_id_1c, "#");
				$product_id_1c=substr($features_id_1c, 0, $str);			
				$ProductId1CArray[] = $product_id_1c;
				$FeatureId1CArray[] = $features_id_1c;
			}
		}
		//собираем id вариаций по всем товарам в файле выгрузки
		$text_query = array();
		foreach ($ProductId1CArray as $ProductId1C){
			$text_query[] = "p.product_1c_id = '".$ProductId1C."'";
		}
		if (!empty($text_query)){
			if (count($text_query)== 1 ){
				$text_query_final = $text_query[0];	
			}else{
				$text_query_final = implode(' OR ', $text_query);
			}
			
			$ProductAllArrayQuery = $db->query( "SELECT p.product_id AS product_id, pov.product_option_value_id AS product_variation_id
			FROM " . DB_PREFIX . "product AS p LEFT JOIN " . DB_PREFIX . "product_option_value AS pov ON p.product_id = pov.product_id 
			WHERE (".$text_query_final.")");
			if ($ProductAllArrayQuery->num_rows) { 	
				foreach (($ProductAllArrayQuery->rows) as $ProductAllResult){
					$ProductAllArray[$ProductAllResult['product_variation_id']] = $ProductAllResult['product_id'];
				}
			}		
		}
		
		//собираем id вариаций по файлу выгрузки
		$text_query = array();
		foreach ($FeatureId1CArray as $FeatureId1C){
			$text_query[] = "product_option_value_1c_id = '".$FeatureId1C."'";
		}
		if (!empty($text_query)){
			if (count($text_query)== 1 ){
				$text_query_final = $text_query[0];	
			}else{
				$text_query_final = implode(' OR ', $text_query);
			}
			
			$FeatureAllArrayQuery = $db->query( "SELECT product_id, product_option_value_id AS product_variation_id
			FROM " . DB_PREFIX . "product_option_value WHERE (".$text_query_final.")");
			if ($FeatureAllArrayQuery->num_rows) { 	
				foreach (($FeatureAllArrayQuery->rows) as $FeatureAllResult){
					$FeatureAllArray[$FeatureAllResult['product_variation_id']] = $FeatureAllResult['product_id'];
				}
			}				
		}
		$diff_array = array_diff_assoc($ProductAllArray, $FeatureAllArray);
		if (count($diff_array) > 0) {
			$text_query = array();
			foreach($diff_array as $product_variation_id => $product_id){
				$text_query[] = "product_option_value_id = '".$product_variation_id."'";
			}
			if (!empty($text_query)){
				if (count($text_query)== 1 ){
					$text_query_final = $text_query[0];	
				}else{
					$text_query_final = implode(' OR ', $text_query);
				}
				$db->query ("DELETE FROM " . DB_PREFIX . "product_option_value WHERE ".$text_query_final."");
				$db->query ("DELETE FROM " . DB_PREFIX . "product_option_value_1c WHERE ".$text_query_final."");
				//$db->query ("DELETE FROM " . DB_PREFIX . "product_option WHERE ".$text_query_final."");
			}		
		}
	}

	$last_element_upload = HeartBeat::getLastElementUpload($FilenameUpload);
	//чтение характеристик номенклатуры для заполения данных
	foreach ( $offers->Предложение as $product_features ) { 
		$features_id_1c = (isset($product_features->Ид)) ? (string)$product_features->Ид : '';
		$HeartBeatStatus = HeartBeat::getNext($FilenameUpload, $FilePart, $ThisPage, $posix, $type_upload, $features_id_1c, $last_element_upload);
		if ($HeartBeatStatus == 'next'){
			continue;
		}
		if ($HeartBeatStatus == 'false'){
			exit();
		}
		
		$product_SKU = (isset($product_features->Артикул)) ? (string)$product_features->Артикул : 'Не указан';
		$product_name = (isset($product_features->Наименование)) ? (string)$product_features->Наименование : '';
		$product_SKU = formatString($product_SKU);

		$feature_name_all = (isset($product_features->Наименование)) ? (string)$product_features->Наименование : 'Наименование характеристики не задано';
		$image_feature = (isset($product_features->Картинка)) ? (string)$product_features->Картинка : '';

		//разбор характеристик товара
		$pos = strrpos($features_id_1c, "#");
		if ($pos === false) { 
			//не найдена характерстика номенклатуры 
		}else{		
			$str=strpos($features_id_1c, "#");
			$product_id_1c=substr($features_id_1c, 0, $str);
			$product_id_query  = $db->query ( "SELECT p.product_id AS product_id, pd.name AS name FROM " . DB_PREFIX . "product AS p LEFT OUTER JOIN " . DB_PREFIX ."product_description AS pd ON p.product_id = pd.product_id where p.product_1c_id = '" . $product_id_1c . "'" );	
			if ($product_id_query->num_rows) { 	
				$product_id = (int)$product_id_query->row['product_id'];		
				$name_feature = str_replace(htmlspecialchars_decode($product_id_query->row['name']), "", $product_name);
				$name_feature = str_replace("(", "", $name_feature);
				$name_feature = str_replace(")", "", $name_feature);
				$name_feature = formatString($name_feature);
				$product_name = $name_feature;
					
				$property_features_name = '';
				$property_features_value = '';
				$property_name_array = array();
				$property_value_array = array();
				
				if (isset($product_features->ХарактеристикиТовара->ХарактеристикаТовара)){
					foreach ( $product_features->ХарактеристикиТовара->ХарактеристикаТовара as $property_features ) { 
						if (isset($property_features->Наименование)){
							$property_features_name = (string)$property_features->Наименование;
							$pos_features_name = strrpos($property_features_name, "(");
							if (!$pos_features_name === false){
								$property_features_name = trim(mb_substr($property_features_name,0,mb_strpos($property_features_name,'(')));
							}
							$property_name_array[] = $property_features_name;
							if (isset($property_features->Значение)){
								$property_features_value = (string)$property_features->Значение;
								$property_value_array[] = trim($property_features_value);
							}
						}
					}
				}
				
				$name_option = VM_NAME_OPTION;
				if (!empty($property_name_array)){
					$property_name_array = array_unique($property_name_array);
					if (count($property_name_array)>1){
						$property_features_name = implode(", ", $property_name_array);
						unset ($property_name_array);
					}
				}
				if (empty($property_features_name)){
					if (!empty($name_option)){
						$property_features_name = VM_NAME_OPTION;
					}else{
						$property_features_name = 'Характеристика товара';
					}
				}
				$property_features_name = formatString($property_features_name);
				//создание опции по данным 1с
				$option_description_query  = $db->query ( "SELECT option_id FROM " . DB_PREFIX . "option_description where name = '".$property_features_name."' AND language_id = '".LANGUAGE_ID."'" );
				if ($option_description_query->num_rows) {							
					$option_id = (int)$option_description_query->row['option_id'];	
				}else{	
					$ins = new stdClass ();
					$ins->option_id = NULL;
					$ins->type = 'select';
					$ins->sort_order = '0';
					insertObject ( "" . DB_PREFIX ."option", $ins, 'option_id') ;		
					$option_id = (int)getLastId("".DB_PREFIX ."option", 'option_id');
					
					foreach ($languages as $lang) {		
						$ins = new stdClass ();
						$ins->option_id = $option_id;
						$ins->name = $property_features_name;
						$ins->language_id = $lang['language_id'];
						insertObject ( "" . DB_PREFIX ."option_description", $ins) ;	
					}					
				}
				
				//создаем характеристики номенклатуры в таблице option_value_description
				if (count($property_value_array) > 0){
					$property_value_array = array_unique($property_value_array);
					$product_name = implode(", ", $property_value_array);
					unset ($property_value_array);
				}
				$product_name = formatString($product_name);
				$option_value_query  = $db->query ( "SELECT option_value_id FROM " . DB_PREFIX . "option_value_description where name ='".$product_name."' AND option_id='".$option_id."' AND language_id = '".LANGUAGE_ID."'" );
				if ($option_value_query->num_rows) {							
					$option_value_id = (int)$option_value_query->row['option_value_id'];				
				}else{
					$ins = new stdClass ();
					$ins->option_value_id = NULL;
					$ins->option_id = $option_id;
					$ins->image = '';
					$ins->sort_order = '0';
					//$ins->option_value_1c_id = '';
					insertObject ( "" . DB_PREFIX ."option_value", $ins) ;
					$option_value_id = (int)getLastId("".DB_PREFIX ."option_value", 'option_value_id');
						
					foreach ($languages as $lang) {
						$ins = new stdClass ();
						$ins->option_value_id = $option_value_id;
						$ins->language_id = $lang['language_id'];
						$ins->option_id = $option_id;
						$ins->name = $product_name;
						insertObject ( "" . DB_PREFIX ."option_value_description", $ins ) ;
					}
				}
					
				//проверяем наличие опции "Характеристика товара" у товара
				$product_option_query  = $db->query ( "SELECT product_option_id FROM " . DB_PREFIX . "product_option WHERE product_id ='".$product_id."' AND option_id = '".$option_id."'");
				if ($product_option_query->num_rows) {							
					$product_option_id = (int)$product_option_query->row['product_option_id'];				
				}else{
					$ins = new stdClass ();
					$ins->product_option_id = NULL;
					$ins->product_id = $product_id;
					$ins->option_id = $option_id;
					if (VERSION_OC15 == 0){
						$ins->value = '';
					}
					$ins->required = 1;
					insertObject ( "" . DB_PREFIX ."product_option", $ins, 'product_option_id' ) ;
					$product_option_id = (int)$ins->product_option_id;
				}
					
				//добавляем характеристики к товару
				$search_features  = $db->query ( "SELECT pov.product_option_value_id AS product_option_value_id FROM " . DB_PREFIX . "product_option_value AS pov LEFT OUTER JOIN " . DB_PREFIX ."product_option_value_1c AS pov1c ON pov.product_option_value_id = pov1c.product_option_value_id WHERE pov1c.product_option_value_1c_id = '" . $features_id_1c . "' AND pov.product_id = '".$product_id."'" );
				if ($search_features->num_rows) {			
					$feature_update  = $db->query ( "UPDATE " . DB_PREFIX . "option_value_description SET name='".$product_name."' where option_value_id='".$option_value_id."'"); 
					$product_option_value_id = (int)$search_features->row['product_option_value_id'];
					$product_option_value_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value SET quantity='0' where product_option_value_id='".$product_option_value_id."' and product_id = '".$product_id."'");
				}else{
					$ins = new stdClass ();
					$ins->product_option_value_id = NULL;
					$ins->product_option_id = $product_option_id;
					$ins->product_id = $product_id;
					$ins->option_id = $option_id;
					$ins->option_value_id = $option_value_id;
					$ins->quantity = 0;
					$ins->subtract = VM_SUBTRACT_OPTION;
					$ins->price = '0';
					$ins->price_prefix = '+';
					$ins->points = 0;
					$ins->points_prefix = '+';
					$ins->weight = '0';
					$ins->weight_prefix = '+';
					$ins->product_option_value_1c_id = $features_id_1c;
					insertObject ( "" . DB_PREFIX ."product_option_value", $ins, 'product_option_value_id') ;
					$product_option_value_id = $ins->product_option_value_id;
						
					insertFeature1cIdForProductOptionValue($product_option_value_id, $features_id_1c, $product_id, $option_id);
				}
				//загрузка картинок характеристики (для УНФ 1.6.4 и старше)	
				loadImagesForFeature($image_feature, $feature_name_all, $option_value_id, $product_option_value_id);
				//формируем массив характеристик номенклатуры
				$FeaturesArray[$features_id_1c]['product_option_value_id'] = $product_option_value_id;
			}
		}//если это характерстики
	}
	HeartBeat::clearElementUploadInStatusProgress($FilenameUpload, $FilePart, $type_upload);
	return $FeaturesArray;	
}

function insertFeature1cIdForProductOptionValue($product_option_value_id, $features_id_1c, $product_id, $option_id){
global $db;	
	$search_product_option_value_id  = $db->query ( "SELECT * FROM " . DB_PREFIX . "product_option_value_1c WHERE product_option_value_id = '" . (int)$product_option_value_id . "'  AND product_option_value_1c_id = '" . $features_id_1c . "'" );		
    if ($search_product_option_value_id->num_rows){				
		$product_option_value_update  = $db->query ( "UPDATE " . DB_PREFIX . "product_option_value_1c SET product_id ='".(int)$product_id."', option_id ='".(int)$option_id."' WHERE product_option_value_id = '" . (int)$product_option_value_id . "'  AND product_option_value_1c_id = '" . $features_id_1c . "'"); 		
	}else{		
		$ins = new stdClass ();
		$ins->id = NULL;
		$ins->product_option_value_id = (int)$product_option_value_id;
		$ins->product_id = (int)$product_id;
		$ins->option_id = (int)$option_id;
		$ins->product_option_value_1c_id = $features_id_1c;
		insertObject ( "" . DB_PREFIX ."product_option_value_1c", $ins, 'id') ;
		$id = $ins->id;
	}
}

function UpdateImages ($product_id, $Name, $PicturePath, $count_images, $file_path_db) {
global $db;												
	$ins = new stdClass ();
	$ins->product_image_id = NULL;
	$ins->product_id = $product_id;
	$ins->image = $file_path_db;
	$ins->sort_order = $count_images;
	insertObject ( "" . DB_PREFIX ."product_image", $ins  ) ;													
}

function loadImagesForProduct($Tovar, $Name, $product_id){
global $db;	
	$images_array = array();
	$count_images = 0;
	if (isset($Tovar->Картинка)){
		foreach ($Tovar->Картинка as $PicturePath){
			$images_array[] = $PicturePath;
		}
	}
	$images_array = array_unique($images_array);
	if (!empty($images_array)){
		foreach ($images_array as $PicturePath){
			$count_images = $count_images + 1;
			if ($PicturePath <> ''){
				$PicturePath = getFileFromPath($PicturePath);
				$copy_result = copyFileToImageFolder($PicturePath, $Name);
				if ($copy_result['status_result'] == 'true'){
					if ($count_images == 1) {
						$image_update = $db->query (  "UPDATE " . DB_PREFIX . "product SET image='".$copy_result['file_path_db']."' where product_id ='".$product_id."'");	
					}else{
						UpdateImages($product_id, $Name, $PicturePath, $count_images, $copy_result['file_path_db']);
					}
				}
				if (($copy_result['status_result'] == 'false') and (STOP_PROGRESS == 1)){
					if ($count_images == 1) {
						$image_update = $db->query (  "UPDATE " . DB_PREFIX . "product SET image='".$copy_result['file_path_db']."' where product_id ='".$product_id."'");	
					}else{
						UpdateImages($product_id, $Name, $PicturePath, $count_images, $copy_result['file_path_db']);
					}
				}						
			}
		}		
	}
}

function copyFileToImageFolder($filename, $product_name){
	$folder_name = getNameForFolder($product_name);
	if (STOP_PROGRESS == 0){
		$folder = JPATH_BASE . DS . VM_CATALOG_IMAGE_ALL . DS . $folder_name;
		if (!file_exists($folder)){
			mkdir($folder, 0755);
		}
	}
	$temp_catalog = JPATH_BASE . DS ."TEMP" . DS . $filename;
	if (STOP_PROGRESS == 0){
		$copy_catalog = JPATH_BASE . DS . VM_CATALOG_IMAGE_ALL . DS .$folder_name. DS . $filename;
		$file_path_db = VM_CATALOG_IMAGE . DS .$folder_name. DS . $filename;
	}else{
		$copy_catalog = JPATH_BASE . DS . VM_CATALOG_IMAGE_ALL . DS . $filename;
		$file_path_db = VM_CATALOG_IMAGE . DS . $filename;
	}
	$result = array('file_path_all' => $copy_catalog,
					'file_path_db'  => $file_path_db,	
					'status_result' => 'false');
	if (file_exists($temp_catalog)){
		$size = getimagesize($temp_catalog);//проверка на картинку равную 0 байт
		if (($size[0] == 0) and ($size[1]  == 0)) {
			$result['status_result'] = 'false';
			write_log("Ошибка копирования файла! Размер файла ".$filename." равен 0 байт. Товар: ".$product_name."");
			return $result;			
		}
			
		if (!copy($temp_catalog, $copy_catalog)){
			write_log("Не удалось скопировать ".$filename." для товара ".$product_name."");	
			$result['status_result'] = 'false';	
		}else{
			$result['status_result'] = 'true';
			if (VM_DELETE_TEMP == 1){
				clear_files_temp($filename);	
			}	
		}
	}else{
		if (file_exists($copy_catalog)){
			$result['status_result'] =  true;
		}else{
			write_log("Не найден файл ".$filename." в папке TEMP для товара ".$product_name."");
			$result['status_result'] =  'false';
		}
	}
	return $result;
}

function loadImagesForFeature($PicturePath, $feature_name_all, $option_value_id, $product_option_value_id){
global $db;		
	$count_images = 0;
	$count_images = $count_images + 1;
	if ( $PicturePath <> ''){
		$PicturePath = getFileFromPath($PicturePath);
		$copy_result = copyFileToImageFolder($PicturePath, $feature_name_all); 
		if ($copy_result['status_result'] == 'true'){
			$image_update = $db->query (  "UPDATE " . DB_PREFIX . "option_value SET image='".$copy_result['file_path_db']."' where option_value_id ='".(int)$option_value_id."'");
		}
	}
}

function OrderStatusReturn ($NameStatus) {
global $db;	
	$order_status_query  = $db->query ( "SELECT order_status_id FROM " . DB_PREFIX . "order_status WHERE name = '" . $NameStatus . "'" );
	if ($order_status_query->num_rows) {
	$order_status_id = $order_status_query->row['order_status_id'];
	return $order_status_id;
	}else{
			$ins = new stdClass ();
			$ins->order_status_id = NULL;
			$ins->language_id = LANGUAGE_ID;
			$ins->name = $NameStatus;
			insertObject ( "" . DB_PREFIX ."order_status", $ins, 'order_status_id'  ) ;
			
			return $ins->order_status_id;
	}	
}

function GetOrders() {
global $db;

	$order_status_ozhidanie  = OrderStatusReturn ('Ожидание');
	$order_status_dostavleno = OrderStatusReturn ('Доставлено');
	$order_status_otmeneno   = OrderStatusReturn ('Отменено');
	$order_status_oplacheno  = OrderStatusReturn ('Оплачено');
	$order_status_vobrabotke = OrderStatusReturn ('В обработке');

	$status_query = $db->query ("SELECT value FROM " . DB_PREFIX . "setting_exchange_1c WHERE name_setting = 'VM_STATUS_EXCHANGE'"); 
	$text_query = array();
	if ($status_query->num_rows){
	$std_status_setting = json_decode($status_query->row['value'], false);
		foreach($std_status_setting as $status_setting){
				$status_id = $status_setting->status_id;
				$enable_exchange = $status_setting->enable_exchange;
				if ($enable_exchange == '1'){
					$text_query[] = "`order_status_id` = '".(int)$status_id."'";
				}
		}
	}
	if (!empty($text_query)){
		if (count($text_query)== 1 ){
			$text_query_final = $text_query[0];	
		}else{
			$text_query_final = implode(' OR ', $text_query);
		}
	}else{
		$text_query_final = "`order_status_id` = '999999'";
	}

	$date_up = '1990-01-01 00:00:00';
	$order_date_load_query = $db->query ("SELECT value FROM " . DB_PREFIX . "setting_exchange_1c WHERE name_setting = 'VM_ORDER_DATE_LOAD'"); 
	if ($order_date_load_query->num_rows){
		$order_date_load = $order_date_load_query->row['value'];
		if (!empty($order_date_load)){
			$date_up = $order_date_load.' 00:00:00';
		}
	}
	$OrdersArray = array();
	$count_orders = 0;
	$orders_query  = $db->query ( "SELECT * FROM `" . DB_PREFIX . "order` WHERE `date_added`> '".$date_up."' AND (".$text_query_final.")");
	if ($orders_query->num_rows){
		foreach (($orders_query->rows) as $zakazy){
			$count_orders = $count_orders + 1;
								
			$order_id 	     = (isset($zakazy['order_id'])) ? (int)$zakazy['order_id'] : 0;
			$order_status_id = (isset($zakazy['order_status_id'])) ? $zakazy['order_status_id'] : 0;
			$order_number    = $order_id;
			$order_key       = (isset($zakazy['tracking'])) ? (string)$zakazy['tracking'] : '';
			$order_total     = (isset($zakazy['total'])) ? (float)$zakazy['total'] : 0;
			$date_order      = (isset($zakazy['date_added'])) ? strtotime($zakazy['date_added']) : date("Y-m-d H:i:s");
			$date            = date("Y-m-d", $date_order);
			$time            = date("H:i:s", $date_order);
			
			$order_number    = formatStringForXML($order_number);
			$order_key       = formatStringForXML($order_key);
			
			$OrdersArray[$order_number]['Ид'] = $order_number;
			$OrdersArray[$order_number]['Номер'] = $order_number;		
			$OrdersArray[$order_number]['Дата'] = $date;
			$OrdersArray[$order_number]['Время'] = $time;
			$OrdersArray[$order_number]['ХозОперация'] = "Заказ товара";
			$OrdersArray[$order_number]['Роль'] = "Продавец";
			$OrdersArray[$order_number]['Сумма'] = $order_total;
			$OrdersArray[$order_number]['Курс'] = "1";
			
			//Валюта документа
			if (VM_CURRENCY == 1){
				$val = 'RUB';
				if (isset($zakazy['currency_code'])){
					$val = $zakazy['currency_code'];
				}
				$val = getRightNameCurrency($val);
				$OrdersArray[$order_number]['Валюта'] = $val;	
			}

			//Контрагент
			$customer_id         = (isset($zakazy['customer_id'])) ? (int)$zakazy['customer_id'] : '';
			$customer_first_name = (isset($zakazy['firstname'])) ? (string)$zakazy['firstname'] : '';
			$customer_last_name  = (isset($zakazy['lastname'])) ? (string)$zakazy['lastname'] : '';
			$customer_email      = (isset($zakazy['email'])) ? (string)$zakazy['email'] : 'non@email.com';
			$customer_telephone  = (isset($zakazy['telephone'])) ? (string)$zakazy['telephone'] : '';
			$customer_fax  		 = (isset($zakazy['fax'])) ? (string)$zakazy['fax'] : '';
			$customer_off_name   = (isset($zakazy['payment_company'])) ? (string)$zakazy['payment_company'] : '';
			$member              = (isset($zakazy['shipping_company'])) ? (string)$zakazy['shipping_company'] : '';
			
			$customer_first_name = formatStringForXML($customer_first_name);
			$customer_last_name  = formatStringForXML($customer_last_name);
			$customer_email      = formatStringForXML($customer_email);
			$customer_telephone  = formatStringForXML($customer_telephone);
			$customer_fax        = formatStringForXML($customer_fax);
			$customer_off_name   = formatStringForXML($customer_off_name);
			
			
			$FIO = $customer_first_name . " " . $customer_last_name;			
			$FIO_no_spacing = str_replace(' ', '', $FIO);
			if (empty($FIO_no_spacing)) {
				$FIO =  "Покупатель с сайта";
			}
			if (!empty($customer_email)){
				$FIO = $FIO.' ('.$customer_email.')';
			}
			
			if (empty($customer_off_name)){
				$OrdersArray[$order_number]['Контрагент']['Ид'] = $FIO;
				$OrdersArray[$order_number]['Контрагент']['Наименование'] = $FIO;
				$OrdersArray[$order_number]['Контрагент']['ПолноеНаименование'] = $FIO;
			}else{
				$OrdersArray[$order_number]['Контрагент']['Ид'] = $customer_off_name;
				$OrdersArray[$order_number]['Контрагент']['Наименование'] = $customer_off_name;
				$OrdersArray[$order_number]['Контрагент']['ПолноеНаименование'] = $customer_off_name;
				$OrdersArray[$order_number]['Контрагент']['ОфициальноеНаименование'] = $customer_off_name;
			}

			if (VERSION_OC3 == 1){
				$customer_info  = $db->query ( "SELECT * FROM " . DB_PREFIX . "customer_affiliate WHERE customer_id = '".$customer_id."'" );
				if ($customer_info->num_rows) {
					$tax_id = trim($customer_info->row['tax']);
					$OrdersArray[$order_number]['Контрагент']['ИНН'] = $tax_id;
				}
			}

			//Контакты
			if (!empty($customer_telephone)){
				$OrdersArray[$order_number]['Контрагент']['Телефон']['Представление'] = $customer_telephone;
				$OrdersArray[$order_number]['Контрагент']['Контакт']['ТелефонРабочий'] = $customer_telephone;
			}
			if (!empty($customer_email)){
				$OrdersArray[$order_number]['Контрагент']['email']['Представление']   = $customer_email;
				$OrdersArray[$order_number]['Контрагент']['Контакт']['Почта'] = $customer_email;
			}
			if (!empty($customer_fax)){
				$OrdersArray[$order_number]['Контрагент']['Факс']['Представление'] = $customer_fax;
				$OrdersArray[$order_number]['Контрагент']['Контакт']['Факс'] = $customer_fax;
			}

			//Представители
			if (!empty($member)){
				$OrdersArray[$order_number]['Контрагент']['Представитель'] = $member;
			}
						
			//Юридический адрес
			$country = ((isset($zakazy['payment_country'])) and (!empty($zakazy['payment_country']))) ? (string)$zakazy['payment_country'] : 'Россия';
			$country = getRightNameCountry($country);
			$postcode = ((isset($zakazy['payment_postcode'])) and (!empty($zakazy['payment_postcode']))) ? (string)$zakazy['payment_postcode'] : '';
			$state = ((isset($zakazy['payment_zone'])) and (!empty($zakazy['payment_zone']))) ? (string)$zakazy['payment_zone'] : '';
			$city = ((isset($zakazy['payment_city'])) and (!empty($zakazy['payment_city']))) ? (string)$zakazy['payment_city'] : '';
			$address_1 = ((isset($zakazy['payment_address_1'])) and (!empty($zakazy['payment_address_1']))) ? (string)$zakazy['payment_address_1'] : '';
			$address_2 = ((isset($zakazy['payment_address_2'])) and (!empty($zakazy['payment_address_2']))) ? (string)$zakazy['payment_address_2'] : '';

			$country   = formatStringForXML($country);
			$postcode  = formatStringForXML($postcode);
			$state     = formatStringForXML($state);
			$city      = formatStringForXML($city);
			$address_1 = formatStringForXML($address_1);
			$address_2 = formatStringForXML($address_2);
			
			
			$address = array();	
			if (!empty($postcode)) { $address[] = $postcode;}
			if (!empty($country))  { $address[] = $country;}
			if (!empty($state))    { $address[] = $state;}
			if (!empty($city))     { $address[] = $city;}
			if (!empty($address_1)){ $address[] = $address_1;}
			if (!empty($address_2)){ $address[] = $address_2;}		
			$presentment = implode(', ', $address);
			
			$OrdersArray[$order_number]['Контрагент']['АдресРегистрации']['Представление']   = $presentment;
			$OrdersArray[$order_number]['Контрагент']['АдресРегистрации']['Страна']          = $country;
			$OrdersArray[$order_number]['Контрагент']['АдресРегистрации']['Регион']          = $state;
			$OrdersArray[$order_number]['Контрагент']['АдресРегистрации']['Почтовый индекс'] = $postcode;
			$OrdersArray[$order_number]['Контрагент']['АдресРегистрации']['Город']           = $city;
			$OrdersArray[$order_number]['Контрагент']['АдресРегистрации']['Улица']           = $address_1;
			
			$OrdersArray[$order_number]['Контрагент']['ЮридическийАдрес']['Представление']   = $presentment;
			$OrdersArray[$order_number]['Контрагент']['ЮридическийАдрес']['Страна']          = $country;
			$OrdersArray[$order_number]['Контрагент']['ЮридическийАдрес']['Регион']          = $state;
			$OrdersArray[$order_number]['Контрагент']['ЮридическийАдрес']['Почтовый индекс'] = $postcode;
			$OrdersArray[$order_number]['Контрагент']['ЮридическийАдрес']['Город']           = $city;
			$OrdersArray[$order_number]['Контрагент']['ЮридическийАдрес']['Улица']           = $address_1;
			
			//Фактический адрес
			$country = ((isset($zakazy['shipping_country'])) and (!empty($zakazy['shipping_country']))) ? (string)$zakazy['shipping_country'] : 'Россия';
			$country = getRightNameCountry($country);
			$postcode = ((isset($zakazy['shipping_postcode'])) and (!empty($zakazy['shipping_postcode']))) ? (string)$zakazy['shipping_postcode'] : '';
			$state = ((isset($zakazy['shipping_zone'])) and (!empty($zakazy['shipping_zone']))) ? (string)$zakazy['shipping_zone'] : '';
			$city = ((isset($zakazy['shipping_city'])) and (!empty($zakazy['shipping_city']))) ? (string)$zakazy['shipping_city'] : '';
			$address_1 = ((isset($zakazy['shipping_address_1'])) and (!empty($zakazy['shipping_address_1']))) ? (string)$zakazy['shipping_address_1'] : '';
			$address_2 = ((isset($zakazy['shipping_address_2'])) and (!empty($zakazy['shipping_address_2']))) ? (string)$zakazy['shipping_address_2'] : '';

			$country   = formatStringForXML($country);
			$postcode  = formatStringForXML($postcode);
			$state     = formatStringForXML($state);
			$city      = formatStringForXML($city);
			$address_1 = formatStringForXML($address_1);
			$address_2 = formatStringForXML($address_2);
			
			$address = array();	
			if (!empty($postcode)) { $address[] = $postcode;}
			if (!empty($country))  { $address[] = $country;}
			if (!empty($state))    { $address[] = $state;}
			if (!empty($city))     { $address[] = $city;}
			if (!empty($address_1)){ $address[] = $address_1;}
			if (!empty($address_2)){ $address[] = $address_2;}		
			$presentment = implode(', ', $address);
			
			$OrdersArray[$order_number]['Контрагент']['Адрес']['Представление']   = $presentment;
			$OrdersArray[$order_number]['Контрагент']['Адрес']['Страна']          = $country;
			$OrdersArray[$order_number]['Контрагент']['Адрес']['Регион']          = $state;
			$OrdersArray[$order_number]['Контрагент']['Адрес']['Почтовый индекс'] = $postcode;
			$OrdersArray[$order_number]['Контрагент']['Адрес']['Город']           = $city;
			$OrdersArray[$order_number]['Контрагент']['Адрес']['Улица']           = $address_1;
				
			//Заполнение поля комментарий
			$status_order    =  OrderStatusInfo($order_status_id);
			$user_comment    = (isset($zakazy['comment'])) ? strip_tags($zakazy['comment']) : '';
			$payment_method  = (isset($zakazy['payment_method'])) ? (string)$zakazy['payment_method'] : '';
			$shipping_method = (isset($zakazy['shipping_method'])) ? (string)$zakazy['shipping_method'] : '';	
			$shipping_price  = getShippingPriceOrder($order_id);
			
			$status_order    = formatStringForXML($status_order);
			$user_comment    = formatStringForXML($user_comment);
			$payment_method  = formatStringForXML($payment_method);
			$shipping_method = formatStringForXML($shipping_method);
				
			$comment = '';
			$comment = $comment . "Статус на сайте: ". $status_order ."; \n";
			if (!empty($payment_method)) {
				$comment = $comment . "Оплата: ". $payment_method ."; \n";	
			}	
			if (!empty($shipping_method)) {
				$comment = $comment . "Доставка: ". $shipping_method ."; \n";
				if ($shipping_price > 0){
					if (!empty($presentment)){ 
						$comment = $comment . "Адрес доставки: ". $presentment ."; \n";	
					}
				}
			}
			$comment = $comment . "Комментарий: ". $user_comment ." ";	
			$OrdersArray[$order_number]['Комментарий'] = $comment;
			
			//Разбор товаров
			$products_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '".(int)$order_id."'" );

			//Расчет скидок			
			$summa_total_document = 0;			
			$order_total_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '".(int)$order_id."'  AND code = 'total' " );
			if ($order_total_query->num_rows) {
				$summa_total_document = $order_total_query->row['value'];
			}
			$sum_before_skidka = getSummOrder($order_id);
			$skidka = round($sum_before_skidka - $summa_total_document);
			$arCoefficients = array();
			foreach ($products_query->rows as $razbor_zakaza_sale) {
				$order_product_id = (isset($razbor_zakaza_sale['order_product_id'])) ? (int)$razbor_zakaza_sale['order_product_id'] : '';
				$product_price   = (isset($razbor_zakaza_sale['price'])) ? (float)$razbor_zakaza_sale['price'] : 0;
				$product_count  = (isset($razbor_zakaza_sale['quantity'])) ? (float)$razbor_zakaza_sale['quantity'] : 0;
				$product_tax     = (isset($razbor_zakaza_sale['tax'])) ? (float)$razbor_zakaza_sale['tax'] : 0;
				if (VM_NDS == 1){
					$product_price = ($product_price  * $product_tax  / 100) + $product_price;
				}
				$summ = $product_price * $product_count;
				$arCoefficients[$order_product_id] = $summ; 
			}
			if ($shipping_price > 0){
				$shipping_id  = 0;
				$arCoefficients[$shipping_id] = $shipping_price;
			}
			$sales_array = getProportionalSums($skidka, $arCoefficients, 2); 
			
			foreach ($products_query->rows as $razbor_zakaza_t) {
				$info_product = array();
				$info_product['product_1c_id'] = '';
				$info_product['product_name'] = (isset($razbor_zakaza_t['name'])) ? (string)$razbor_zakaza_t['name'] : 'Наименование не задано';
				$info_product['sku'] = (isset($razbor_zakaza_t['model'])) ? (string)$razbor_zakaza_t['model'] : '';
				$product_name_tovar = $info_product['product_name'];
				
				$product_id = (isset($razbor_zakaza_t['product_id'])) ? (int)$razbor_zakaza_t['product_id'] : '';
				$order_product_id = (isset($razbor_zakaza_t['order_product_id'])) ? (int)$razbor_zakaza_t['order_product_id'] : '';
				if ((!empty($product_id)) and ($product_id > 0)){
					$info_products_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "product WHERE product_id = '".(int)$product_id."'" );
					if ($info_products_query->num_rows){
						$info_product['product_1c_id'] = $info_products_query->row['product_1c_id'];
						$info_product['sku'] = $info_products_query->row['sku'];
					}
				}
	
				if (VM_FEATURES_1C == 0){	
					if (empty($info_product['product_1c_id'])){
						$info_product['product_1c_id'] = $product_name_tovar;
					}
				}	
				if (VM_FEATURES_1C == 1){
					$info_features_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_option WHERE order_product_id = '".(int)$order_product_id."' AND order_id = '".$order_id."'" );
					if ($info_features_query->num_rows) {			
						$product_option_value_id = $info_features_query->row['product_option_value_id'];
						$features_name = $info_features_query->row['value'];
						$search_features_1c_id  = $db->query ( "SELECT product_option_value_1c_id FROM " . DB_PREFIX . "product_option_value_1c WHERE product_option_value_id = '".$product_option_value_id."' AND product_id = '".$product_id."'" );
						if ($search_features_1c_id->num_rows) {
							$features_1c_id = $search_features_1c_id->row['product_option_value_1c_id'];	
							$product_name_tovar = $info_product['product_name']." (".$features_name.")";					
							if (!empty($features_1c_id)){
								$info_product['product_1c_id'] = $features_1c_id; 
							}else{
								$info_product['product_1c_id'] = $product_name_tovar;
							}
						}else{		
							//попытка найти характеристику номенклатуры по сочетанию названия опции и значению опции
							$features_1c_id = '';
							$option_id = 0;
							$option_value_id = 0;
							$product_option_value_id = 0;
							$product_option_value_1c_id = '';
							$option_name = $info_features_query->row['name'];
							$option_value = $info_features_query->row['value'];
							$product_option_id = (int)$info_features_query->row['product_option_id'];
							$search_option_id  = $db->query ( "SELECT option_id FROM " . DB_PREFIX . "option_description WHERE name = '".$option_name."' AND language_id = '".LANGUAGE_ID."'" );
							if ($search_option_id->num_rows) {
								$option_id = $search_option_id->row['option_id'];
								$search_option_value  = $db->query ( "SELECT option_value_id FROM " . DB_PREFIX . "option_value_description WHERE name = '".$option_value."' AND option_id = '".$option_id."' AND language_id = '".LANGUAGE_ID."'" );
								if ($search_option_value->num_rows) {
									$option_value_id = $search_option_value->row['option_value_id'];
									$search_product_option_value  = $db->query ( "SELECT * FROM " . DB_PREFIX . "product_option_value WHERE product_id = '".(int)$product_id."' AND option_value_id = '".(int)$option_value_id."' AND option_id = '".(int)$option_id."'" );
									if ($search_product_option_value->num_rows) {	
										$product_option_value_id = $search_product_option_value->row['product_option_value_id'];
										$search_product_option_value_1c  = $db->query ( "SELECT product_option_value_1c_id FROM " . DB_PREFIX . "product_option_value_1c WHERE product_id = '".(int)$product_id."' AND product_option_value_id = '".(int)$product_option_value_id."'" );
										if ($search_product_option_value_1c->num_rows) {
											$product_option_value_1c_id = $search_product_option_value_1c->row['product_option_value_1c_id'];
											$features_1c_id = $product_option_value_1c_id;
											$features_name = $option_value;
										}
									}
								}
							}
							
							if (!empty($features_1c_id)){
								$info_product['product_1c_id'] = $features_1c_id; 
								$product_name_tovar = $info_product['product_name']." (".$features_name.")";
							}else{
								if (!empty($features_name)){
									$product_name_tovar = $info_product['product_name']." (".$features_name.")";
									$info_product['product_1c_id'] = $product_name_tovar;
								}else{
									if (empty($info_product['product_1c_id'])){
										$info_product['product_1c_id'] = $product_name_tovar;
									}
								}	
							}
						}
					}else{
						if (empty($info_product['product_1c_id'])){
							$info_product['product_1c_id'] = $product_name_tovar;
						}
					}
				}
				
				$product_name_tovar = formatStringForXML($product_name_tovar);
				if(empty($product_name_tovar)){
					$product_name_tovar = 'Наименование не задано';
				}
				
				$product_1c_id   = ((isset($info_product['product_1c_id'])) and (!empty($info_product['product_1c_id'])))? (string)$info_product['product_1c_id'] : $product_name_tovar;
				$product_price   = (isset($razbor_zakaza_t['price'])) ? (float)$razbor_zakaza_t['price'] : 0;
				$product_tax     = (isset($razbor_zakaza_t['tax'])) ? (float)$razbor_zakaza_t['tax'] : 0;
				$product_count   = (isset($razbor_zakaza_t['quantity'])) ? (float)$razbor_zakaza_t['quantity'] : 0;
				$product_total   = (isset($razbor_zakaza_t['total'])) ? (float)$razbor_zakaza_t['total'] : 0;
				$product_artikul = (isset($info_product['sku'])) ? (string)$info_product['sku'] : '';
				$product_sale    = 0;
				
				$product_name_tovar = formatStringForXML($product_name_tovar);
				$product_1c_id      = formatStringForXML($product_1c_id);
				$product_artikul    = formatStringForXML($product_artikul);

				$OrdersArray[$order_number]['Товары'][$order_product_id]['Ид'] = $product_1c_id;
				$OrdersArray[$order_number]['Товары'][$order_product_id]['Наименование'] = $product_name_tovar;
				$OrdersArray[$order_number]['Товары'][$order_product_id]['Артикул'] = $product_artikul;
				$OrdersArray[$order_number]['Товары'][$order_product_id]['БазоваяЕдиница'] = "шт";
				$OrdersArray[$order_number]['Товары'][$order_product_id]['Единица'] = "шт";
				$OrdersArray[$order_number]['Товары'][$order_product_id]['Коэффициент'] = "1";
				$OrdersArray[$order_number]['Товары'][$order_product_id]['Количество'] = $product_count;
				$OrdersArray[$order_number]['Товары'][$order_product_id]['ВидНоменклатуры'] = "Товар";
				$OrdersArray[$order_number]['Товары'][$order_product_id]['ТипНоменклатуры'] = "Товар";
				
				if (VM_NDS == 1){
					$product_price = ($product_price  * $product_tax  / 100) + $product_price;
				}
				$OrdersArray[$order_number]['Товары'][$order_product_id]['ЦенаЗаЕдиницу'] = $product_price;
				
				$summ = $product_price * $product_count;
				$OrdersArray[$order_number]['Товары'][$order_product_id]['Сумма'] = $summ;
				
				//Учет НДС
				if (BUH_3 == 1){
					$OrdersArray[$order_number]['Товары'][$order_product_id]['НДС'] = "БЕЗ НДС";
				}
				
				//Скидки
				if (isset($sales_array[$order_product_id])){
					$OrdersArray[$order_number]['Товары'][$order_product_id]['Скидка'] = $sales_array[$order_product_id];
				}
			}
			
			//Доставка	
			if ($shipping_price > 0){				
				$shipping_id   = 0;
				$shipping_tax  = VM_NDS_SHIP;
				$shipping_sale = 0;
				
				if (empty($shipping_method)){
					$shipping_method = 'Наименование не задано';
				}
				
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Ид'] = $shipping_method;
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Наименование'] = $shipping_method;
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Артикул'] = '';
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['БазоваяЕдиница'] = "шт";
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Единица'] = "шт";
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Коэффициент'] = "1";
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Количество'] = 1;
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['ВидНоменклатуры'] = "Услуга";
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['ТипНоменклатуры'] = "Услуга";
				
				if (VM_NDS == 1){
					$shipping_price = ($shipping_price  * $shipping_tax  / 100) + $shipping_price;
				}
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['ЦенаЗаЕдиницу'] = $shipping_price;
				
				$summ = $shipping_price * 1;
				$OrdersArray[$order_number]['Услуги'][$shipping_id]['Сумма'] = $summ;
				
				//Скидки
				if (isset($sales_array[$shipping_id])){
					$OrdersArray[$order_number]['Услуги'][$shipping_id]['Скидка'] = $sales_array[$shipping_id];
				}	
			}
			
			//Информация о методе доставки	
			if ($shipping_price > 0){					
				$shipping_first_name = (isset($zakazy['shipping_firstname'])) ? (string)$zakazy['shipping_firstname'] : '';
				$shipping_last_name  = (isset($zakazy['shipping_lastname'])) ? (string)$zakazy['shipping_lastname'] : '';
				$receiver = trim($shipping_first_name.' '.$shipping_last_name);
				
				$receiver = formatStringForXML($receiver);
				
				$shipping_code = rus2translit($shipping_method);
				$shipping_code = mb_substr((md5($shipping_code)),1,10); 
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Метод доставки ИД']    = $shipping_code;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Метод доставки']       = $shipping_method;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Адрес доставки']       = $presentment;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Комментарий доставки'] = $presentment;
				
				if (!empty($receiver)){
					$OrdersArray[$order_number]['ЗначениеРеквизита']['Получатель'] = $receiver;
				}
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Контактный телефон'] = $customer_telephone;	
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Почта получателя']   = $customer_email;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Стоимость доставки'] = $shipping_price;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Трек-номер']         = $order_key;
			}
			//Статус заказа 
			//Оплаченный заказ	
			if($order_status_id == $order_status_oplacheno){
				
				$date_added = $date_order;
				$order_history_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_history WHERE order_id = '".(int)$order_id."'  AND order_status_id = '".$order_status_oplacheno."' ORDER BY order_history_id DESC LIMIT 1" );
				if ($order_history_query->num_rows) {
					$date_added = strtotime($order_history_query->row['date_added']);
					if (empty($date_added)){
						$date_added = $date_order;
					}
				}
				$date_added_date = date("Ymd", $date_added);
				$date_added_time = date("Hms", $date_added);
				$date_payment = $date_added_date.$date_added_time; //Дата = '20170825125905'; // 25 августа 2017 года 12:59:05	
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Статус заказа'] = $status_order;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Дата отгрузки'] = $date;
				
				//Оплата заказа на сайте (УНФ 1.6.11.46)
				if (empty($date_payment)){  
					$date_added = $date_order;
					$date_added_date = date("Ymd", $date_added);
					$date_added_time = date("Hms", $date_added);
					$date_payment = $date_added_date.$date_added_time;				
				}
				if (UNF_1_6_15 == 1){
					$date_payment = date("Y-m-d", $date_added);
				}
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Дата оплаты']                 = $date_payment;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Метод оплаты']                = "Интернет";
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Номер платежного документа']  = $order_id;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Заказ оплачен']               = "true";
				
				//обмен по 54-ФЗ
				$OrdersArray[$order_number]['ОтправитьЧекККМ'] = "true";
			}
			//Отмененный заказ
			if($order_status_id == $order_status_otmeneno){
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Статус заказа'] = $status_order;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Отменен']       = "true";		
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Дата отгрузки'] = $date;	
			}
			//Любой другой статус	
			if(($order_status_id <> $order_status_oplacheno) or ($order_status_id <> $order_status_otmeneno)) {
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Статус заказа'] = $status_order;
				$OrdersArray[$order_number]['ЗначениеРеквизита']['Дата отгрузки'] = $date;
			}

			if (VM_ORDER_STATUS_PROCESSING <> ''){
				$update_order = $db->query ("UPDATE " . DB_PREFIX . "order SET order_status_id='".VM_ORDER_STATUS_PROCESSING."' WHERE order_id='".$order_id."'"); 
			}		
		}
	}
	return $OrdersArray;
}

function CreateZakaz($use_bitrix = false) {
	$timechange = time ();
	$count_orders = 0;
	$OrdersArray = array();

	if ($use_bitrix == true){
		$no_spaces = '<?xml version="1.0" encoding="UTF-8"?><КоммерческаяИнформация ВерсияСхемы="3.1" ДатаФормирования="' . date ( 'Y-m-d', $timechange ) . 'T' . date ( 'H:i:s', $timechange ) . '"></КоммерческаяИнформация>';
		$xml = new SimpleXMLElement($no_spaces);
			
		$readStatus = readStatusProgress('upload_orders_bitrix');
		$time_upload = strtotime($readStatus['date_exchange']);
		$time_now = strtotime(date('Y-m-d H:i:s'));
		$diff = abs($time_now - $time_upload);
		if ($diff > 60){
			$pack = $xml->addChild ( "Контейнер" );
			$OrdersArray = GetOrders();
			saveStatusProgress ('upload_orders_bitrix', 'success', 'time upload');	
		}
	}else{
		$no_spaces = '<?xml version="1.0" encoding="UTF-8"?><КоммерческаяИнформация ВерсияСхемы="2.04" ДатаФормирования="' . date ( 'Y-m-d', $timechange ) . 'T' . date ( 'H:i:s', $timechange ) . '"></КоммерческаяИнформация>';
		$xml = new SimpleXMLElement($no_spaces);
		$OrdersArray = GetOrders();
	}
	
	foreach ($OrdersArray as $Order){
		$count_orders++;
		$doc = $xml->addChild ( "Документ" );
				
		if (isset($Order['Ид'])){
			$doc->addChild ( "Ид", $Order['Ид'] );
		}
		if (isset($Order['Номер'])){
			$doc->addChild ( "Номер", $Order['Номер'] );
		}
		if (isset($Order['Дата'])){
			$doc->addChild ( "Дата", $Order['Дата'] );
		}
		if (isset($Order['Время'])){
			$doc->addChild ( "Время", $Order['Время'] );
		}
		if (isset($Order['ХозОперация'])){
			$doc->addChild ( "ХозОперация", $Order['ХозОперация'] );
		}
		if (isset($Order['Роль'])){
			$doc->addChild ( "Роль", $Order['Роль'] );
		}
		if (isset($Order['Валюта'])){
			$doc->addChild ( "Валюта", $Order['Валюта'] );
		}
		if (isset($Order['Курс'])){
			$doc->addChild ( "Курс", $Order['Курс'] );
		}
		if (isset($Order['Сумма'])){
			$doc->addChild ( "Сумма", $Order['Сумма'] );
		}
			
		$k1 = $doc->addChild ( 'Контрагенты' );
		$k1_1 = $k1->addChild ( 'Контрагент' );	
		if (isset($Order['Контрагент']['Ид'])){
			$k1_2 = $k1_1->addChild ( "Ид", $Order['Контрагент']['Ид']);
		}
		if (isset($Order['Контрагент']['Наименование'])){
			$k1_2 = $k1_1->addChild ( "Наименование", $Order['Контрагент']['Наименование'] );
		}
		if (isset($Order['Контрагент']['Роль'])){
			$k1_2 = $k1_1->addChild ( "Роль", $Order['Контрагент']['Роль'] );
		}
		if (isset($Order['Контрагент']['ПолноеНаименование'])){
			$k1_2 = $k1_1->addChild ( "ПолноеНаименование", $Order['Контрагент']['ПолноеНаименование'] );
		}
		if (isset($Order['Контрагент']['ОфициальноеНаименование'])){
			$k1_2 = $k1_1->addChild ( "ОфициальноеНаименование", $Order['Контрагент']['ОфициальноеНаименование'] );
		}
		if (isset($Order['Контрагент']['ИНН'])){
			$k1_2 = $k1_1->addChild ( "ИНН", $Order['Контрагент']['ИНН'] );
		}
		if (isset($Order['Контрагент']['КПП'])){
			$k1_2 = $k1_1->addChild ( "КПП", $Order['Контрагент']['КПП'] );
		}

		if (isset($Order['Контрагент']['Телефон']['Представление'])){
			$k1_2 = $k1_1->addChild ( "Телефон");
			$k1_2->addChild ( "Представление", $Order['Контрагент']['Телефон']['Представление'] );
		}
		if (isset($Order['Контрагент']['email']['Представление'])){
			$k1_2 = $k1_1->addChild ( "email");
			$k1_2->addChild ( "Представление", $Order['Контрагент']['email']['Представление'] );
		}
		
		//Контакты
		$contacts = $k1_1->addChild ( 'Контакты' );
		if (isset($Order['Контрагент']['Контакт']['ТелефонРабочий'])){
			$cont = $contacts->addChild ( 'Контакт' );
			$cont->addChild ( 'Тип', 'ТелефонРабочий' );
			$cont->addChild ( 'Значение', $Order['Контрагент']['Контакт']['ТелефонРабочий'] );
			}
		if (isset($Order['Контрагент']['Контакт']['Почта'])){
			$cont = $contacts->addChild ( 'Контакт' );
			$cont->addChild ( 'Тип', 'Почта' );
			$cont->addChild ( 'Значение', $Order['Контрагент']['Контакт']['Почта'] );
		}
		if (isset($Order['Контрагент']['Контакт']['Факс'])){
			$cont = $contacts->addChild ( 'Контакт' );
			$cont->addChild ( 'Тип', 'Факс' );
			$cont->addChild ( 'Значение', $Order['Контрагент']['Контакт']['Факс'] );
		}
		
		//Представители
		if (isset($Order['Контрагент']['Представитель']['Наименование'])){
			$addr = $k1_1->addChild ('Представители');
			$addrField = $addr->addChild ( 'Представитель');
			$addrField2 = $addrField ->addChild ( 'Контрагент');
			$addrField3 = $addrField2 ->addChild ( 'Наименование', $Order['Контрагент']['Представитель']['Наименование']);
		}
		
		if (isset($Order['Комментарий'])){
			$doc->addChild ( "Комментарий", $Order['Комментарий'] );
		}
		
		foreach ($Order['Контрагент'] as $OrderUnitName => $OrderUnit){
			if (($OrderUnitName == 'АдресРегистрации') or ($OrderUnitName == 'ЮридическийАдрес') or ($OrderUnitName == 'Адрес')){
				$addr = $k1_1->addChild ($OrderUnitName);			
				if (isset($Order['Контрагент'][$OrderUnitName]['Представление'])){
					$addr->addChild ( 'Представление', $Order['Контрагент'][$OrderUnitName]['Представление'] );
				}
				
				if (isset($Order['Контрагент'][$OrderUnitName]['Страна'])){
					$addrField = $addr->addChild ( 'АдресноеПоле' );
					$addrField->addChild ( 'Тип', 'Страна' );
					$addrField->addChild ( 'Значение', $Order['Контрагент'][$OrderUnitName]['Страна'] );
				}
				
				if (isset($Order['Контрагент'][$OrderUnitName]['Регион'])){
					$addrField = $addr->addChild ( 'АдресноеПоле' );
					$addrField->addChild ( 'Тип', 'Регион' );
					$addrField->addChild ( 'Значение', $Order['Контрагент'][$OrderUnitName]['Регион'] );
				}
				
				if (isset($Order['Контрагент'][$OrderUnitName]['Почтовый индекс'])){
					$addrField = $addr->addChild ( 'АдресноеПоле' );
					$addrField->addChild ( 'Тип', 'Почтовый индекс' );
					$addrField->addChild ( 'Значение', $Order['Контрагент'][$OrderUnitName]['Почтовый индекс'] );
				}
				
				if (isset($Order['Контрагент'][$OrderUnitName]['Город'])){
					$addrField = $addr->addChild ( 'АдресноеПоле' );
					$addrField->addChild ( 'Тип', 'Город' );
					$addrField->addChild ( 'Значение', $Order['Контрагент'][$OrderUnitName]['Город'] );
				}
				
				if (isset($Order['Контрагент'][$OrderUnitName]['Улица'])){
					$addrField = $addr->addChild ( 'АдресноеПоле' );
					$addrField->addChild ( 'Тип', 'Улица' );
					$addrField->addChild ( 'Значение', $Order['Контрагент'][$OrderUnitName]['Улица'] );
				}
			}
		}
		
		//Товары и Услуги
		$table_order = array('Товары', 'Услуги');		
		foreach ($table_order as $table){		
			if (!isset($t1)){
				$t1 = $doc->addChild ( 'Товары' );
			}
			if (isset($Order[$table])){			
				foreach($Order[$table] as $Product){
					if (!isset($Product['Наименование'])){
						continue;
					}
					$t1_1 = $t1->addChild ( 'Товар' );
					if (isset($Product['Ид'])){
						$t1_2 = $t1_1->addChild ( "Ид", $Product['Ид'] ); 
					}
					if (isset($Product['Наименование'])){
						$t1_2 = $t1_1->addChild ( "Наименование", $Product['Наименование'] ); 
					}
					if (isset($Product['Коэффициент'])){
						$t1_2 = $t1_1->addChild ( "Коэффициент", $Product['Коэффициент'] ); 
					}
					if (isset($Product['БазоваяЕдиница'])){
						$t1_2 = $t1_1->addChild ( "БазоваяЕдиница", $Product['БазоваяЕдиница'] ); 
						$t1_2->addAttribute("Код", "796");	
					}
					if (isset($Product['Единица'])){
						$t1_2 = $t1_1->addChild ( "Единица", $Product['Единица'] ); 
						$t1_2->addAttribute("Ид", $Product['Единица']);
						$t1_2->addAttribute("Код", "796");
						$t1_2->addAttribute("НаименованиеКраткое", $Product['Единица']);	
						$t1_2->addAttribute("НаименованиеПолное", $Product['Единица']);	
						$t1_2->addChild("Код", "796");	
					}
					if (isset($Product['НДС'])){
						$t1_2 = $t1_1->addChild ( "СтавкиНалогов");
						$t1_3 = $t1_2->addChild ( "СтавкаНалога");
						$t1_4 = $t1_3->addChild ( "Наименование", "НДС");
						$t1_4 = $t1_3->addChild ( "Ставка", $Product['НДС']);
					}
					if (isset($Product['ЦенаЗаЕдиницу'])){
						$t1_2 = $t1_1->addChild ( "ЦенаЗаЕдиницу", $Product['ЦенаЗаЕдиницу'] ); 
					}
					if (isset($Product['Количество'])){
						$t1_2 = $t1_1->addChild ( "Количество", $Product['Количество'] ); 
					}
					if (isset($Product['Сумма'])){
						$t1_2 = $t1_1->addChild ( "Сумма", $Product['Сумма'] ); 
					}
					if (isset($Product['Артикул'])){
						$t1_2 = $t1_1->addChild ( "Артикул", $Product['Артикул'] ); 
					}
						
					$t1_2 = $t1_1->addChild ( "ЗначенияРеквизитов" );
					if (isset($Product['ВидНоменклатуры'])){
						$t1_3 = $t1_2->addChild ( "ЗначениеРеквизита" );
						$t1_4 = $t1_3->addChild ( "Наименование", "ВидНоменклатуры" );
						$t1_4 = $t1_3->addChild ( "Значение", $Product['ВидНоменклатуры'] );
					}
					if (isset($Product['ТипНоменклатуры'])){
						$t1_3 = $t1_2->addChild ( "ЗначениеРеквизита" );
						$t1_4 = $t1_3->addChild ( "Наименование", "ТипНоменклатуры" );
						$t1_4 = $t1_3->addChild ( "Значение", $Product['ТипНоменклатуры'] );
					}
									
					if (isset($Product['Скидка'])){
						$sk0 = $t1_1->addChild ( 'Скидки'  );
						$sk1 = $sk0->addChild ( 'Скидка'  );
						$sk2= $sk1->addChild ( 'УчтеноВСумме' , 'false' );
						$sk2= $sk1->addChild ( "Сумма", $Product['Скидка'] ); 
					}
				}
			}
			
		}
		
		if (isset($Order['ЗначениеРеквизита'])){
			$s1_2 = $doc->addChild ( "ЗначенияРеквизитов" );
			foreach ($Order['ЗначениеРеквизита'] as $PropertyName => $PropertyValue) {
				if (isset($Order['ЗначениеРеквизита'][$PropertyName])){
					$s1_3 = $s1_2->addChild ( "ЗначениеРеквизита" );
					$s1_3->addChild ( "Наименование", $PropertyName );
					$s1_3->addChild ( "Значение", $Order['ЗначениеРеквизита'][$PropertyName] );
				}
			}
		}
		
		//обмен по 54-ФЗ
		if (isset($Order['ОтправитьЧекККМ'])){		
			$doc->addChild ( "ОтправитьЧекККМ" , $Order['ОтправитьЧекККМ'] );	
		}
		unset($t1);
		unset($Order);		
	}
	
	write_log ('Выгружено заказов: '.$count_orders);
	if (VM_CODING == 'UTF-8') {
		$xml_text = $xml->asXML();
		header("Content-Type: text/xml");
		$text = iconv( "UTF-8", "CP1251//TRANSLIT", $xml_text );
		print $text;
	}elseif(VM_CODING == 'Default'){
		header("Content-Type: text/xml");
		print $xml->asXML ();
	}else{
		$contents = $xml->asXML();
		$encoding_str = mb_detect_encoding($contents);
		if($encoding_str != "WINDOWS-1251"){
			$contents = iconv( $encoding_str, "CP1251//IGNORE", $contents );
		}
		$str = (function_exists("mb_strlen")? mb_strlen($contents, 'latin1'): strlen($contents));
		header("Content-Type: application/xml; charset=windows-1251");
		header("Content-Length: ".$str);
		echo $contents;
	}
	exit();
}

function getRightNameCountry($country_name){
	$country = 'Россия';
	switch ($country_name) {
		case 'Российская Федерация': $country = 'Россия';      break;
		case 'Russian Federation':   $country = 'Россия';      break;
		case 'RU': 					 $country = 'Россия';      break;
		case 'KZ':                   $country = 'Казахстан';   break;
		case 'Kazakhstan':           $country = 'Казахстан';   break;
		case 'UA':					 $country = 'Украина';     break;
		case 'Ukraine':				 $country = 'Украина';     break;
		case 'BY':					 $country = 'Белоруссия';  break;
		case 'Belarus':				 $country = 'Белоруссия';  break;
		case 'LV':					 $country = 'Латвия';      break;
		case 'Latvia':			     $country = 'Латвия';      break;
		case 'LT':					 $country = 'Литва';       break;
		case 'Lithuania':			 $country = 'Литва';       break;
		case 'EE':					 $country = 'Эстония';     break;
		case 'Estonia':			     $country = 'Эстония';     break;
		case 'KG':					 $country = 'Киргизия';    break;
		case 'Kyrgyzstan':			 $country = 'Киргизия';    break;
		case 'TJ':					 $country = 'Таджикистан'; break;
		case 'Tajikistan':			 $country = 'Таджикистан'; break;
		case 'Latvija':			     $country = 'Латвия';      break;
		case 'Deutschland':			 $country = 'Германия'; break;
		case 'Rossiya':			     $country = 'Россия';      break;
	}
	return $country_name;
}

function getRightNameCurrency($val){
	$currency = VM_NAME_CURRENCY_DEFAULT;
	switch ($val) {
		case 'RUB': $currency = 'RUB'; break;
		case 'руб': $currency = 'RUB'; break;
		case 'rub': $currency = 'RUB'; break;
		case '131': $currency = 'руб'; break;
		case 'евр': $currency = 'EUR'; break;
		case 'eur': $currency = 'EUR'; break;
		case 'EUR': $currency = 'EUR'; break;
		case 'usd': $currency = 'USD'; break;
		case 'дол': $currency = 'USD'; break;
		case 'dol': $currency = 'USD'; break;
		case 'uan': $currency = 'UAH'; break;
		case 'гри': $currency = 'UAH'; break;
		case 'грн': $currency = 'UAH'; break;
		case 'grn': $currency = 'UAH'; break;
		case 'uah': $currency = 'UAH'; break;
		case 'UAH': $currency = 'UAH'; break;
		case 'лв':  $currency = 'KZT'; break;
		case 'KZT': $currency = 'KZT'; break;	
		case 'BYN': $currency = 'BYN'; break;
		case 'бел': $currency = 'BYR'; break;	
		case 'BYR': $currency = 'BYR'; break;	
		case 'KGS': $currency = 'KGS'; break;
		case 'LVL': $currency = 'LVL'; break;
		case 'lvl': $currency = 'LVL'; break;		
	}
	return $currency;
}

function getShippingPriceOrder($order_id) {
global $db;
	$price_shipping = 0;
	$order_total_query  = $db->query ( "SELECT value FROM " . DB_PREFIX . "order_total WHERE order_id = '".(int)$order_id."'  AND code = 'shipping' " );
	if ($order_total_query->num_rows) {
		$price_shipping = (float)$order_total_query->row['value'];
	}
	return $price_shipping;
}

function getSummOrder($order_id){
global $db;	
	//вычисляем сумму документа, как стоимость всех товаров + доставка
	$subtotal_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '".(int)$order_id."'  AND code = 'sub_total' " );
	if ($subtotal_query->num_rows) {
	$sub_total = $subtotal_query->row['value'];//сумма до скидки
		
		$shipping_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '".(int)$order_id."'  AND code = 'shipping' " );
		if ($shipping_query->num_rows) {
			$shipping_sum = $shipping_query->row['value'];//сумма доставки
			$itog_sum = $sub_total + $shipping_sum;
			return $itog_sum;
		}else{
			return $sub_total;
		}
	
	}else{
		return 0;
	}
}

function OrderStatusInfo($status_key) {
global $db;
	$status_name = 'Неизвестно';
	$status_query  = $db->query ( "SELECT name FROM " . DB_PREFIX . "order_status WHERE order_status_id = '".$status_key."' AND language_id = '".LANGUAGE_ID."'" );
	if ($status_query->num_rows) {
		$status_name = $status_query->row['name'];
	}
	return $status_name;
}

//функция установки цен номенклатуры и загрузки остатков
function product_price_update($offers, $ShopperGroupsArray, $all_count_element, $FilePart = 0) { 
global $db;
global $FilenameUpload;
global $ThisPage;
global $posix;
$element_count = 0;
$element_count_continue = 0;
$time_start = strtotime(date('Y-m-d H:i:s'));
$type_upload = 'price';
HeartBeat::setСountElementAll($all_count_element);
	if (isset ( $offers->Предложение )){		
		$last_element_upload = HeartBeat::getLastElementUpload($FilenameUpload);		
		foreach ($offers->Предложение as $product_price_data ) { 	
			$element_count++;
			HeartBeat::setСountElementNow($element_count);
			
			$product_id_1c = (isset($product_price_data->Ид)) ? (string)$product_price_data->Ид : '';
			$HeartBeatStatus = HeartBeat::getNext($FilenameUpload, $FilePart, $ThisPage, $posix, $type_upload, $product_id_1c, $last_element_upload);
			if ($HeartBeatStatus == 'next'){
				$element_count_continue++;
				continue;
			}
			progressLoad($element_count, $element_count_continue, $FilePart, $all_count_element, $time_start, strtotime(date('Y-m-d H:i:s')), "предложений");
			if ($HeartBeatStatus == 'false'){
				exit();
			}
			
			$product_id_query = $db->query ( "SELECT product_id FROM " . DB_PREFIX . "product where product_1c_id = '".$product_id_1c."'" );
			if ($product_id_query->num_rows) {
				$product_id = (int)$product_id_query->row['product_id'];		
				if (VM_UPDATE_PRICE == 1){ 	
					$main_price = 0;
					if ((isset($product_price_data->Цены->Цена)) and (isset($product_id))) {
						foreach ( $product_price_data->Цены->Цена as $price_data) { 
							$mPrice = (isset($price_data->ЦенаЗаЕдиницу)) ? (float)$price_data->ЦенаЗаЕдиницу : '';
							$mCurr_data = (isset($price_data->Валюта)) ? (string)$price_data->Валюта : 'RUB';
							$mCurr = getRightNameCurrency($mCurr_data);	
							$mCurr_query  = $db->query ( "SELECT currency_id FROM " . DB_PREFIX . "currency where code = '" . $mCurr . "'" );					
							if ($mCurr_query->num_rows) {						
								//валюта уже есть в базе
							}else{
								$ins = new stdClass ();
								$ins->currency_id = NULL;
								$ins->title = $mCurr;
								$ins->code = $mCurr;
								$ins->symbol_left = "";
								$ins->symbol_right = $mCurr;
								$ins->decimal_place = $mCurr;
								$ins->value = "1";
								$ins->status = "1";
								$ins->date_modified = date('Y-m-d H:i:s');
								insertObject ( "" . DB_PREFIX ."currency", $ins) ;		
							}
								
							$shopper_group_id_1c =(string)$price_data->ИдТипаЦены; 
							$name_price_discount = VM_TYPE_PRICE_1C_SPECIAL;
							$customer_group_id_price_discount  = $db->query (  "SELECT customer_group_id FROM " . DB_PREFIX . "customer_group_description where name = '".$name_price_discount."' and customer_group_1c_id = '" .$shopper_group_id_1c . "' ");
							if (!$customer_group_id_price_discount->num_rows) {
								if (VM_PRICE_1C == 0){
									$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET price='".$mPrice."' where product_id='". (int) $product_id."'");	
									$main_price = $mPrice;
								}else{
									//установка акции
									if (VM_PRICE_1C_SPECIAL == 1){
										$name_price = VM_TYPE_PRICE_1C ;
										$customer_group_id_query  = $db->query (  "SELECT customer_group_id FROM " . DB_PREFIX . "customer_group_description where name = '" .$name_price . "' and customer_group_1c_id = '" .$shopper_group_id_1c . "' ");
										if ($customer_group_id_query->num_rows) {
											$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET price='".$mPrice."' where product_id='". (int) $product_id."'"); 		
											$main_price = $mPrice;
										}
										if ($ShopperGroupsArray[$shopper_group_id_1c]['id_vm'] <>'') {	
											$product_special_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" .( int ) $product_id. "' and customer_group_id = '" .( int ) $ShopperGroupsArray[$shopper_group_id_1c]['id_vm']. "'");											
											$ins = new stdClass ();
											$ins->product_special_id = NULL;
											$ins->product_id = (int) $product_id;
											$ins->customer_group_id = (int) $ShopperGroupsArray[$shopper_group_id_1c]['id_vm'];
											$ins->priority = '0';
											$ins->price = $mPrice;
											$ins->date_start = "0000-00-00";
											$ins->date_end = "0000-00-00";
											insertObject ( "" . DB_PREFIX ."product_special", $ins) ;			
										}
									}

									//установка скидки
									if (VM_PRICE_1C_DISCOUNT == 1){
										$name_price = VM_TYPE_PRICE_1C ;
										$customer_group_id_query  = $db->query (  "SELECT customer_group_id FROM " . DB_PREFIX . "customer_group_description where name = '" .$name_price . "' and customer_group_1c_id = '" .$shopper_group_id_1c . "' ");
										if ($customer_group_id_query->num_rows) {
											$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET price='".$mPrice."' where product_id='". (int) $product_id."'");
											$main_price = $mPrice;
										}
										if ($ShopperGroupsArray[$shopper_group_id_1c]['id_vm'] <>'') {	
											$product_discount_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" .( int ) $product_id. "' and customer_group_id = '" .( int ) $ShopperGroupsArray[$shopper_group_id_1c]['id_vm']. "'");																
											$ins = new stdClass ();
											$ins->product_discount_id = NULL;
											$ins->product_id = (int) $product_id;
											$ins->customer_group_id = (int) $ShopperGroupsArray[$shopper_group_id_1c]['id_vm'];
											$ins->quantity = '1';
											$ins->priority = '0';
											$ins->price = $mPrice;
											$ins->date_start = "0000-00-00";
											$ins->date_end = "0000-00-00";
											insertObject ( "" . DB_PREFIX ."product_discount", $ins) ;			
										}
									}
								}
							}else{
								//установка цены по акции
								$default_customer_group_id = VM_CONFIG_CUSTOMER_GROUP_DEFAULT;
								$product_special_delete = $db->query ("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" .( int ) $product_id. "' and customer_group_id = '" .(int)$default_customer_group_id."'");						
								if ($mPrice > 0){						
									$ins = new stdClass ();
									$ins->product_special_id = NULL;
									$ins->product_id = (int) $product_id;
									$ins->customer_group_id = $default_customer_group_id;
									$ins->priority = '0';
									$ins->price = $mPrice;
									$ins->date_start = "0000-00-00";
									$ins->date_end = "0000-00-00";
									insertObject ( "" . DB_PREFIX ."product_special", $ins) ;
								}	
							}		
						}  					
					}
					$is_modified = true;
				}
				if ((isset($mPrice)) and ($mPrice > 0) and (VM_PRODUCT_VIEW_PRICE0 == 0)){
					$product_price_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET status='1' where product_id='". (int) $product_id."'"); 
				}
				
				//загружаем текущие остатки
				$product_in_stock = getRests($product_price_data);
				if (VM_UPDATE_COUNT == 1){
					$product_search_option  = $db->query ( "SELECT * FROM " . DB_PREFIX . "product_option_value where product_id = '".$product_id."'"); 
					if ((!$product_search_option->num_rows) OR (VM_COUNT_PARENT_FEATURES == 0)) {
						$product_quantity_update  = $db->query ( "UPDATE `" . DB_PREFIX . "product` SET  quantity='".(int)$product_in_stock."' where product_id='".(int)$product_id."'");
					}
					$is_modified = true;
				}
				if ((isset($product_in_stock)) and (VM_PRODUCT_VIEW_COUNT0 == 0)){
					$status = '0';
					if($product_in_stock > 0){
						$status = '1';
					}
					if ((isset($main_price)) and ($main_price <= 0) and (VM_PRODUCT_VIEW_PRICE0 == 0)){
						$status = '0';
					}
					$product_count_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET status='".$status."' where product_id='". (int) $product_id."'"); 
				}
				if ((isset($is_modified)) and ($is_modified == true)){
					$date_modified = date('Y-m-d H:i:s');
					$product_update  = $db->query ( "UPDATE " . DB_PREFIX . "product SET date_modified='".$date_modified."' where product_id='". (int) $product_id."'");
				}
		    }
		}
		HeartBeat::clearElementUploadInStatusProgress($FilenameUpload, $FilePart, $type_upload);
	}
} 

function getRests($product_price_data){
	$product_in_stock = 0;
	if (isset($product_price_data->Количество)) {
		$product_in_stock =(int)$product_price_data->Количество;
	}else{						
		if (isset($product_price_data->Склад)){
			$count_in_sclad = 0;
			foreach ( $product_price_data->Склад as $warehouse){ 
				if (isset($warehouse['КоличествоНаСкладе'])){
					$count_in_sclad = $count_in_sclad + (int)$warehouse['КоличествоНаСкладе'];
				}					
			}
			$product_in_stock = $count_in_sclad;
		}		
	}
	if (isset($product_price_data->Остатки->Остаток->Количество)) {
		$product_in_stock =(int)$product_price_data->Остатки->Остаток->Количество; 
	}else{
		if (isset($product_price_data->Остатки->Остаток->Склад)) {
			$count_in_sclad = 0;
			foreach ($product_price_data->Остатки->Остаток->Склад as $rests){
				$rest = (isset($rests->Количество)) ? (int)$rests->Количество : 0;
				$count_in_sclad = $count_in_sclad + $rest;
			}
			$product_in_stock = $count_in_sclad;
		}
	}	
	return $product_in_stock;
}

//порцонный обмен данными
function XMLParser_getelement($file, $name_element){	
	
	$reader = new XMLReader();
	$reader->open($file);
	
	while ($reader->read()) {
		switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):
				if ($reader->name == $name_element && $reader->nodeType == XMLReader::ELEMENT) {
					$isset_name_element = true;	
					$reader->next();
				}
		}
	}
	$reader->close();
    unset($reader);
	if (isset($isset_name_element)){
		return $isset_name_element;
	}else{
		return false;
	}
		
}

function XMLParser_getAttribute($file, $name_element, $name_attribute){	
	
	$reader = new XMLReader();
	$reader->open($file);
	
	while ($reader->read()) {
		switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):
				if ($reader->name == $name_element && $reader->nodeType == XMLReader::ELEMENT) {
					
					$Attribute = $reader->getAttribute($name_attribute);	
				}
		}
	}
	$reader->close();
    unset($reader);
	if (isset($Attribute)){
		return $Attribute;
	}else{
		return "false";
	}		
}

function XMLParser_element_count($file, $name_element){	
	
	$reader = new XMLReader();
	$reader->open($file);
	
	$count = 0;
	while ($reader->read()) {
		switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):
				if ($reader->name == $name_element && $reader->nodeType == XMLReader::ELEMENT) {
					
					$count = $count +1;	
				}
		}
	}
	$reader->close();
    unset($reader);	
	return $count;
}


function XMLParser_file($file, $start_element, $finish_element, $name_element, $name_elements , $all = false){	
	
	if (function_exists('gc_enable') && !gc_enabled()) {
		gc_enable();
	}

	$groups_array = array();
	
	$domdoc = new DOMDocument('1.0', 'UTF-8');
	$domdoc->formatOutput = true;
	$domdoc->validateOnParse = true;
	if ($name_element == "Группа"){
		$element_ki = $domdoc->createElement("КоммерческаяИнформация");
		$newdomdoc = $domdoc->appendChild($element_ki);
		
		$element = $domdoc->createElement($name_elements);
		$newdomdoc = $newdomdoc->appendChild($element);
		
	}else{
		$element = $domdoc->createElement($name_elements);
		$newdomdoc = $domdoc->appendChild($element);
	}
	
	$reader = new XMLReader();
	$reader->open($file);
	
	$count = 0;
	while ($reader->read()) {
		switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):
				if ($reader->name == $name_element && $reader->nodeType == XMLReader::ELEMENT) {
					
					if ($all == true){
						$node = $reader->expand();
						$newdomdoc->appendChild($node);
					}else{
						if (($count >= $start_element) and ($count <= $finish_element)){
							$node = $reader->expand();
							$newdomdoc->appendChild($node);
						}
						$count = $count +1;	
					}
					
					if ($name_element == "Группа"){
						if (in_array($node, $groups_array)) {
							$reader->next();
						}else{
							$groups_array[] = $node;
						}
					}
				}
		}
	}
	$domdoc->normalizeDocument();
	$xml = simplexml_import_dom($domdoc);
	unset($domdoc);
    $reader->close();
    unset($reader);	
	return $xml;	
}

//+status_exchange_1c
function readStatusProgress($filename){
global $db;	
	$STATUS_EXCHANGE = 'start';
	$ERROR_OK = 'first exchange';
	$response = array();
	
	$last_element_upload = "";
	$status_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "status_exchange_1c where filename = '".$filename."'"); 
	if ($status_query->num_rows) {
		$status = $status_query->row['status'];
		$error = $status_query->row['error'];
		$last_element_upload = $status_query->row['last_element_upload'];
		$date_exchange       = $status_query->row['date_exchange'];
		$response['status'] = $status;
		$response['error'] = $error;
		$response['last_element_upload'] = $last_element_upload;
		$response['date_exchange'] = $date_exchange;
	}else{	
		$date_exchange = date('Y-m-d H:i:s');
		$ins = new stdClass ();
		$ins->id = NULL;
		$ins->filename = $filename;
		$ins->status = $STATUS_EXCHANGE;
		$ins->error = $ERROR_OK;
		$ins->date_exchange = $date_exchange;
		$ins->last_element_upload = $last_element_upload;
		insertObject ( "" . DB_PREFIX ."status_exchange_1c", $ins) ;			
		$response['status'] = $STATUS_EXCHANGE;
		$response['error'] = $ERROR_OK;
		$response['last_element_upload'] = $last_element_upload;
		$response['date_exchange'] = $date_exchange;
	}
	return 	$response;		
}

function saveStatusProgress($filename, $status, $error){
global $db;	
	
	$status_query  = $db->query ( "SELECT id FROM " . DB_PREFIX . "status_exchange_1c where filename = '".$filename."'"); 
	if ($status_query->num_rows) {
		$id = (int)$status_query->row['id'];
		$status_update  = $db->query ( "UPDATE `" . DB_PREFIX . "status_exchange_1c` SET  status='".$status."' , error='".$error."' , date_exchange='".date('Y-m-d H:i:s')."' where id='".$id."'");		
	}else{
		$ins = new stdClass ();
		$ins->id = NULL;
		$ins->filename = $filename;
		$ins->status = $status;
		$ins->error = $error;
		$ins->date_exchange = date('Y-m-d H:i:s');
		$ins->last_element_upload = "";
		insertObject ( "" . DB_PREFIX ."status_exchange_1c", $ins) ;
	}	
}

function progressLoad($count, $count_continue, $FilePart, $all_count, $time_start, $time_now, $string_element, $show_now = false) {
global $FilenameUpload;
global $TimeBefore;
global $posix;		
	
	$show_log = false;
	$percent_load = floor(($count * 100 )/ $all_count);
	$time_load = ($time_now - $time_start);
	if ((($time_load % 7) == 0) and (($time_load <> 0) and ($time_load <> $TimeBefore)) and ($count <= $all_count)){		
		$show_log = true;
	}
	if ($count == $all_count){
		$show_log = true;	
	}
	if ($show_now == true){
		$show_log = true;	
	}
	
	if ($show_log == true){
		write_log("Процесс(".$posix."). Обработка ".$string_element." ".$count." из ".$all_count."(".$percent_load."%). Файл ".$FilenameUpload.", часть ".$FilePart);
	}
}

function getNameAndNumberFile($filename){
	
	$numberfile = '';
	$namefile = '';
	
	$nameimport   = 'import';
	$nameoffers   = 'offers';
	$create_name_fileimport="";
	$create_name_fileoffers="";
	$findimport = strpos($filename, $nameimport);
	if ($findimport === false) {
	   //false
	} else {
		$numberfile = str_replace($nameimport,"",$filename);
		$namefile = $nameimport;
	}
	$findoffers = strpos($filename, $nameoffers );
	if ($findoffers === false) {
		//false
	} else {
		$numberfile = str_replace($nameoffers,"",$filename);
		$namefile = $nameoffers;
	}
	
	$result = array();
	$result['namefile'] = $namefile;
	$result['numberfile'] = $numberfile;
	return $result;
}

function curlRequestAsync($query, $namefile, $number_file, $create_name_file) {
global $full_url_site;
global $posix;
	
	$url = $full_url_site.'/'.$query;
	if ((function_exists('curl_init')) and (VM_USE_ASYNCH == 1)) {
		if ($curl = curl_init()) {
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)"); 
			curl_setopt($curl, CURLOPT_TIMEOUT_MS, 	4000);
			$response = curl_exec($curl);
				
			$curlinfo_http_code = curl_getinfo ($curl, CURLINFO_HTTP_CODE);
			if ($curlinfo_http_code >= 400){
				$errors = 0;
				do {
				  $errors++;
				  write_log("Процесс(".$posix."). Error(".$errors.") CURLINFO_HTTP_CODE: ".curl_getinfo ($curl, CURLINFO_HTTP_CODE).", code error:".curl_errno($curl));
				  sleep (1); 
				  $response = curl_exec($curl);	
				} while (($errors >= 10) and (curl_getinfo ($curl, CURLINFO_HTTP_CODE) >= 400));
			}
			curl_close($curl);
			
			$readStatus = readStatusProgress($create_name_file);
			$status_progress = $readStatus['status'];		
			$history_posix = Posix::getHistoryPosix($create_name_file);
			if (($posix == $history_posix) or (empty($history_posix))){
				if (($status_progress <> 'progress') and ((STOP_PROGRESS == 0) and ($status_progress <> 'stop'))){
					write_log("Процесс(".$posix."). The curl request (HTTP_CODE:".$curlinfo_http_code.") cannot to upload file: ".$create_name_file.". Start standart load");
					startUpload($namefile, $number_file, $create_name_file);
				}
				if (($status_progress == 'progress') and (($curlinfo_http_code == 0) or ($curlinfo_http_code == false))){
					write_log("Процесс(".$posix."). Status load progress: ".$status_progress." (HTTP_CODE:".$curlinfo_http_code."). Start standart load");
					startUpload($namefile, $number_file, $create_name_file);
				}
			}
		}else{
			print "failure"."\n";
			write_log("Процесс(".$posix.").Error! No curl init: ".$curl);
		}
	}else{
		write_log("Процесс(".$posix."). Not function curl_init(). VM_USE_ASYNCH = ".VM_USE_ASYNCH." Start easy upload");
		startUpload($namefile, $number_file, $create_name_file);
	}
	exit();
}

function startUpload($namefile, $number_file, $create_name_file){
	if ((!empty($namefile)) and (!empty($number_file)) and (!empty($create_name_file))) {
		if ($namefile == 'import'){
			uploadFileImport($namefile, $number_file, $create_name_file);
		}
		if ($namefile == 'offers'){
			uploadFileOffers($namefile, $number_file, $create_name_file);
		}
		if ($namefile == 'prices'){
			uploadFileOffers($namefile, $number_file, $create_name_file);
		}
		if ($namefile == 'rests'){
			uploadFileOffers($namefile, $number_file, $create_name_file);
		}
	}
}


function uploadFileImport($nameimport, $number_import, $create_name_fileimport){
global $CategoryArray;	
global $FilenameUpload;
global $posix;
$FilenameUpload = $create_name_fileimport;
HeartBeat::start();
Posix::savePosix($posix, $FilenameUpload);

	$file = JPATH_BASE . DS .'TEMP'. DS . "".$nameimport."".$number_import."";
	if (file_exists ($file)){
		saveStatusProgress ($create_name_fileimport, 'progress', 'ok');	
		$pos = strpos($number_import, "_");	
			$CatalogContainsChanges = XMLParser_getAttribute($file, "Каталог", "СодержитТолькоИзменения");					
			if (VM_FOLDER == 1){//загрузка групп номенклатуры
				$count_product = XMLParser_element_count($file, "Группа");
				$count_parts = ceil($count_product / QUANTITY_DOSE);
				$count = 0;
				$start_element = 0;
				$finish_element = QUANTITY_DOSE;
				while ($count < $count_parts){
					$xml_Category = XMLParser_file($file, $start_element, $finish_element, "Группа", "Группы");
					$count++; // Увеличение счетчика
					write_log("Процесс(".$posix."). Загрузка групп номенклатуры файла ".$nameimport."".$number_import.". Часть =".$count." (из ".$count_parts.")");	
					$start_element = $start_element + QUANTITY_DOSE;
					$finish_element = $finish_element + QUANTITY_DOSE;					
					$CategoryArray = CategoryArrayFill($xml_Category,	$CategoryArray ,  0);
					$CategoryArray = dropCategoryWithOneOwner($CategoryArray);
					CategoryXrefFill ($CategoryArray);
					unset ($xml_Category);
					usleep(1000);
				}
			}			
			//загрузка товаров
			$count_product = XMLParser_element_count($file, "Товар");
			$count_parts = ceil($count_product / QUANTITY_DOSE);
			$count = 0;
			$start_element = 0;
			$finish_element = QUANTITY_DOSE;
			
			$fileWithProperty = $file;
			$pos = strpos($number_import, "_");	
		    if ($pos === false){	
				if (UNF_1_6_15 == 1){
					$fileFirstImportXml = JPATH_BASE . DS .'TEMP'. DS .$nameimport.".xml";
					if (file_exists ($fileFirstImportXml)){
						$fileWithProperty = $fileFirstImportXml;
					}
				}		
			}else{
				$number_offers_parts   = explode( '_' , $number_import );
				$number_part  = $number_offers_parts[0];
				$fileFirstImportXml = JPATH_BASE . DS .'TEMP'. DS . "".$nameimport."".$number_part."_1.xml";
				if (file_exists ($fileFirstImportXml)){
					$fileWithProperty = $fileFirstImportXml;
				}
			}
			
			$isset_property_nomenclatura = XMLParser_getelement($fileWithProperty, "СвойствоНоменклатуры");
			if ($isset_property_nomenclatura == false){
				$xml_all_svoistva = XMLParser_file($fileWithProperty, $start_element, 999999, "Свойство", "Свойства", true);
			}else{
				$xml_all_svoistva = XMLParser_file($fileWithProperty, $start_element, 999999, "СвойствоНоменклатуры", "СвойстваНоменклатуры", true);
			}
			
			while ($count < $count_parts){
				$xml_product = XMLParser_file($file, $start_element, $finish_element, "Товар", "Товары");
				$count++; // Увеличение счетчика
				$start_element = $start_element + QUANTITY_DOSE;
				$finish_element = $finish_element + QUANTITY_DOSE;
				
				$last_element_upload = HeartBeat::getLastElementUpload($create_name_fileimport);
				$last_element_array = HeartBeat::jsonEncodeDecode($last_element_upload, false); 
				if (!empty($last_element_array)){
					if ($last_element_array['filepart'] <> $count) {
						write_log("Процесс(".$posix."). Пропуск чтения файла ".$nameimport."".$number_import.". Часть =".$count." (из ".$count_parts.")");
						continue;
					}
				}
				write_log("Процесс(".$posix."). Загрузка товаров файла ".$nameimport."".$number_import.". Часть =".$count." (из ".$count_parts.")");
				$remains = $count_product - ($count-1) * QUANTITY_DOSE;
				$process_count = ($count == $count_parts)? $remains : QUANTITY_DOSE;
				TovarArrayFill($xml_product, $xml_all_svoistva, $CatalogContainsChanges, $process_count, $count);
				AddDirectorySvoistva($xml_product, $xml_all_svoistva);
				unset ($xml_product);
				usleep(1000);
			}
			if ((isset($CategoryArray)) or (isset($xml_all_svoistva))){
				unset ($CategoryArray,$xml_all_svoistva);	
			}
		$status_progress = 'stop';
		if (STOP_PROGRESS == 1) {
			$status_progress = 'start';
		}
		saveStatusProgress ($create_name_fileimport, $status_progress, 'ok');
	}else{
		saveStatusProgress ($create_name_fileimport, 'stop', 'no find file ='.$create_name_fileimport.'');
		write_log("Процесс(".$posix."). Не найден файл ".$create_name_fileimport.". в папке TEMP");	
	}
	Posix::clearPosix($FilenameUpload);
	exit();
}

function uploadFileOffers($nameoffers, $number_offers, $create_name_fileoffers){
global $ShopperGroupsArray;
global $TovarIdFeatureArray;	
global $posix;
global $FilenameUpload;
$FilenameUpload = $create_name_fileoffers;
HeartBeat::start();
Posix::savePosix($posix , $FilenameUpload);

	$file = JPATH_BASE . DS .'TEMP'. DS . "".$create_name_fileoffers.""; 
	if (file_exists ($file)){
		saveStatusProgress ($create_name_fileoffers, 'progress', 'ok');	
			$isset_paket_predlozhenii = XMLParser_getelement($file, "ПакетПредложений");
			if ($isset_paket_predlozhenii == true) {
				$xml_type_price = XMLParser_file($file, 0, 9999, "ТипЦены", "ТипыЦен", true);
				$ShopperGroupsArray = ShopperGroupsArrayFill($xml_type_price, $ShopperGroupsArray );
				
				$count_product = XMLParser_element_count($file, "Предложение");
				$count_parts = ceil($count_product / QUANTITY_DOSE);
				$count = 0;
				$start_element = 0;
				$finish_element = QUANTITY_DOSE;
				
				while ($count < $count_parts){
					$xml_offers = XMLParser_file($file, $start_element, $finish_element, "Предложение", "Предложения");			
					$count++; // Увеличение счетчика
					$start_element = $start_element + QUANTITY_DOSE;
					$finish_element = $finish_element + QUANTITY_DOSE;
					
					$last_element_upload = HeartBeat::getLastElementUpload($create_name_fileoffers);
					$last_element_array = HeartBeat::jsonEncodeDecode($last_element_upload, false); 
					if (!empty($last_element_array)){
						if ($last_element_array['filepart'] <> $count) {
							write_log("Процесс(".$posix."). Пропуск чтения файла ".$create_name_fileoffers.". Часть =".$count." (из ".$count_parts.")");	
							continue;
						}
					}
					write_log("Процесс(".$posix."). Загрузка предложений файла ".$create_name_fileoffers.". Часть =".$count." (из ".$count_parts.")");	
					$remains = $count_product - ($count-1) * QUANTITY_DOSE;
					$process_count = ($count == $count_parts)? $remains : QUANTITY_DOSE;
					product_price_update ($xml_offers,$ShopperGroupsArray,$process_count, $count);
					//характерстики номенклатуры
					if (VM_FEATURES_1C == 1){
						$FeaturesArray = FeaturesArrayFill($xml_offers, $count);
						update_price_and_quantity_features($xml_offers,$FeaturesArray);
						unset ($FeaturesArray);
					}
					unset ($xml_offers);
					usleep(1000);
				}
			}
						
			$isset_paket_izmenenir_packpredlozhenii = XMLParser_getelement($file, "ИзмененияПакетаПредложений");
			if ($isset_paket_izmenenir_packpredlozhenii == true) {	
				$ShopperGroupsArray = ShopperGroupsArrayFillPackageOffers($ShopperGroupsArray) ;
				
				$count_product = XMLParser_element_count($file, "Предложение");
				$count_parts = ceil($count_product / QUANTITY_DOSE);
				$count = 0;
				$start_element = 0;
				$finish_element = QUANTITY_DOSE;
				while ($count < $count_parts){
					$xml_offers = XMLParser_file($file, $start_element, $finish_element, "Предложение", "Предложения");
					$count++; // Увеличение счетчика
					write_log("Процесс(".$posix."). Загрузка предложений файла ".$create_name_fileoffers.". Часть =".$count." (из ".$count_parts.")");
					$start_element = $start_element + QUANTITY_DOSE;
					$finish_element = $finish_element + QUANTITY_DOSE;
					
					$last_element_upload = HeartBeat::getLastElementUpload($create_name_fileoffers);
					$last_element_array = HeartBeat::jsonEncodeDecode($last_element_upload, false); 
					if (!empty($last_element_array)){
						if ($last_element_array['filepart'] <> $count) {
							continue;
						}
					}
					$remains = $count_product - ($count-1) * QUANTITY_DOSE;
					$process_count = ($count == $count_parts)? $remains : QUANTITY_DOSE;
					product_price_update ($xml_offers, $ShopperGroupsArray,$process_count, $count);
					//характерстики номенклатуры
					if (VM_FEATURES_1C == 1){
						$FeaturesArray = FeaturesArrayFill($xml_offers, $count);
						update_price_and_quantity_features($xml_offers,$FeaturesArray);
						unset ($FeaturesArray);
					}
					unset ($xml_offers);
					usleep(1000);			
				}			
			}												
		$status_progress = 'stop';
		if (STOP_PROGRESS == 1) {
			$status_progress = 'start';
		}
		saveStatusProgress ($create_name_fileoffers, $status_progress, 'ok');
	}else{
		saveStatusProgress ($create_name_fileoffers, 'stop', 'no find file ='.$create_name_fileoffers.'');
		write_log("Процесс(".$posix."). Не найден файл ".$create_name_fileoffers.". в папке TEMP");	
	}
	Posix::clearPosix($FilenameUpload);
	exit();	
}

function catalogImport($filename){
	//exchange_1C_Opencart.php?type=catalog&mode=import&filename=offers0_1.xml	
	$nameimport   = 'import';
	$nameoffers   = 'offers';
	$create_name_fileimport="";
	$create_name_fileoffers="";
	$findimport = strpos($filename, $nameimport);
	if ($findimport === false) {
	   //false
	} else {
		$number_import = str_replace($nameimport,"",$filename);
		$create_name_fileimport = "".$nameimport."".$number_import."";
		$itog_filename=$create_name_fileimport;  
	}
	$findoffers = strpos($filename, $nameoffers );
	if ($findoffers === false) {
		//false
	} else {
		$number_offers = str_replace($nameoffers,"",$filename);
		$create_name_fileoffers = "".$nameoffers."".$number_offers."";
		$itog_filename=$create_name_fileoffers;
		
		$explode_parts   = explode( '.' , $number_offers );
		$num_part  = $explode_parts[0];
		$explode_parts   = explode( '_' , $num_part );
		$num_part  = (int)$explode_parts[0];
	}
	
	$nameprices  = 'prices';
	$namerests   = 'rests';
	$create_name_fileprices="";
	$create_name_filerests="";
	$findprices = strpos($filename, $nameprices);
	if ($findprices === false) {
	   //false
	} else {
		$create_name_fileprices=$filename;
		$itog_filename=$nameprices;  
	}
	$findrests = strpos($filename, $namerests );
	if ($findrests === false) {
		//false
	} else {
		$create_name_filerests=$filename;
		$itog_filename=$namerests;
	}
	
	unset($filename);
	global $posix; 
	if (isset($itog_filename)){
		switch ($itog_filename) {
			case "".$create_name_fileimport."" :						
					$readStatus = readStatusProgress($create_name_fileimport);
					$status_progress = $readStatus['status'];
					if (($status_progress == 'start')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileimport." Начало загрузки данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();
						//uploadFileImport($nameimport, $number_import, $create_name_fileimport);	
								
						global $ThisPage;
						$query = $ThisPage.'?namefile='.$nameimport.'&number_file='.$number_import.'&create_name_file='.$create_name_fileimport;
						curlRequestAsync($query, $nameimport, $number_import, $create_name_fileimport);
					}
					if (($status_progress == 'progress')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileimport." Идет загрузка данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();	
					}
					if (($status_progress == 'stop')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileimport." Загрузка завершена");
						saveStatusProgress ($create_name_fileimport, 'start', 'ok');
						if (VM_DELETE_TEMP == 1){
							clear_files_temp($create_name_fileimport);	
						}
						print "success";		
					}
					break;
			case "".$create_name_fileoffers."" :
					$readStatus = readStatusProgress($create_name_fileoffers);
					$status_progress = $readStatus['status'];
					if (($status_progress == 'start')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileoffers." Начало загрузки данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();
						//uploadFileOffers($nameoffers, $number_offers, $create_name_fileoffers);

						global $ThisPage;
						$query = $ThisPage.'?namefile='.$nameoffers.'&number_file='.$number_offers.'&create_name_file='.$create_name_fileoffers;
						curlRequestAsync($query, $nameoffers, $number_offers, $create_name_fileoffers);
					}
					if (($status_progress == 'progress')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileoffers." Идет загрузка данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();				
					}
					if (($status_progress == 'stop')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileoffers." Загрузка завершена");
						saveStatusProgress ($create_name_fileoffers, 'start', 'ok');
						if (VM_DELETE_TEMP == 1){
							clear_files_temp($create_name_fileoffers);	
						}
						print "success";		
					}
					break;
			case "".$nameprices."" :
					$readStatus = readStatusProgress($create_name_fileprices);
					$status_progress = $readStatus['status'];
					if (($status_progress == 'start')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileprices." Начало загрузки данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();

						global $ThisPage;
						$query = $ThisPage.'?namefile='.$nameprices.'&number_file='.$create_name_fileprices.'&create_name_file='.$create_name_fileprices;
						curlRequestAsync($query, $nameprices, $create_name_fileprices, $create_name_fileprices);
					}
					if (($status_progress == 'progress')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileprices." Идет загрузка данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();				
					}
					if (($status_progress == 'stop')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_fileprices." Загрузка завершена");
						saveStatusProgress ($create_name_fileprices, 'start', 'ok');
						if (VM_DELETE_TEMP == 1){
							clear_files_temp($create_name_fileprices);	
						}
						print "success";		
					}
					break;
			case "".$namerests."" :
					$readStatus = readStatusProgress($create_name_filerests);
					$status_progress = $readStatus['status'];
					if (($status_progress == 'start')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_filerests." Начало загрузки данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();

						global $ThisPage;
						$query = $ThisPage.'?namefile='.$nameprices.'&number_file='.$create_name_filerests.'&create_name_file='.$create_name_filerests;
						curlRequestAsync($query, $nameprices, $create_name_filerests, $create_name_filerests);
					}
					if (($status_progress == 'progress')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_filerests." Идет загрузка данных");
						if (STOP_PROGRESS == 0) {
							print "progress"."\n";
						}else{
							print "success"."\n";
						}
						echo str_pad('',4096);    
						echo str_pad('',4096);
						flush();				
					}
					if (($status_progress == 'stop')){
						write_log("Процесс(".$posix."). Загрузка файла ".$create_name_filerests." Загрузка завершена");
						saveStatusProgress ($create_name_filerests, 'start', 'ok');
						if (VM_DELETE_TEMP == 1){
							clear_files_temp($create_name_filerests);	
						}
						print "success";		
					}
					break;
		}
	}
	unset($wpdb);
	exit();
}
//-status_exchange_1c

//*******************Этапы подключения 1с и opencart*******************

//*******************Авторизация*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'checkauth') 
{
if (($print_key == "a7862912a8d8442ce6b045f2966cbd1e") or ($print_key == "3a4430d445e1ecd118e8f075cdc0e817") or ($print_key == "30e11f412622ecdfcf184c1c9f8ead84")) {
		if (($domain == dsCrypt($dm,1)) or ($domain == dsCrypt($dmw,1)) or ($domain == dsCrypt($dmt,1)))  {
			
			$remote_user = '';
			if (isset($_SERVER['REMOTE_USER'])){
				$remote_user = $_SERVER['REMOTE_USER'];	
			}else{
				if (isset($_SERVER['REDIRECT_REMOTE_USER'])){
					$remote_user = $_SERVER['REDIRECT_REMOTE_USER'];	
				}	
			}
			$strTmp = base64_decode(substr($remote_user,6));
			if ($strTmp){
				list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $strTmp);
			}
			print CheckAuthUser();
			
			if (USE_COOKIES == 1){
				print "\n" . "key";
				print "\n" . $print_key;
			}
		}
	}
}

//*******************Поключение 1с к opencart*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'export') 
{
if (($print_key == "a7862912a8d8442ce6b045f2966cbd1e") or ($print_key == "3a4430d445e1ecd118e8f075cdc0e817") or ($print_key == "30e11f412622ecdfcf184c1c9f8ead84")) {
	if (($domain == dsCrypt($dm,1)) or ($domain == dsCrypt($dmw,1)) or ($domain == dsCrypt($dmt,1)))  {
		print 'success';
	}
}	
}
//*******************Выбор архивировать или нет*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'init') 
{
	if (isset($_REQUEST ['version'])){
		print "zip=" . "no" . "\n" . VM_ZIPSIZE. "\n" . "xml_version". "\n" . "3.1";
	}else{
		print "zip=" . "no" . "\n" . VM_ZIPSIZE;
	}
}
//*******************Загрузка измененного заказа*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'file' && isset ( $_REQUEST ['filename'] )) 
{
	if (($domain == dsCrypt($dm,1)) or ($domain == dsCrypt($dmw,1)) or ($domain == dsCrypt($dmt ,1)))  {
		print LoadFileZakaz ();
	}	
}
//*******************Проверка успешности загрузки файла*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'import' && isset ( $_REQUEST ['filename'] ) && $_REQUEST ['filename'] == $_REQUEST ['filename']) 
{
	print 'success';	
}
//*******************Загрузка архива*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'file' && (isset($_REQUEST ['filename']))) 
{
	if (isset($_REQUEST ['filename'])){
		$filename = $_REQUEST ['filename'];
		$result = LoadFile($filename);
		if (STOP_PROGRESS == 0) {
			print $result;
		}else{
			$findsuccess = strpos($result, 'success');
			if ($findsuccess === false) {
				print $result;
			} else {
				$filename = getFileFromPath($filename);
				$name_files_search = array('import', 'offers');
				$is_catalogImport = false;
				foreach($name_files_search as $name_file_search){
					$findcatalogImport = strpos($filename, $name_file_search);
					if ($findcatalogImport === false) {
					   //false
					} else {
						$is_catalogImport = true;  
					}
				}
				if ($is_catalogImport == true){
					catalogImport($filename);
				}else{
					print $result;
				}
			}
		}
	}
}
//*******************Операция с файлами*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'import')
{
	if (isset($_REQUEST ['filename'])){
		$filename = $_REQUEST ['filename'];
		$filename = getFileFromPath($filename);
		catalogImport($filename);
	}
}

//*******************Передача заказов в 1с*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'query') 
{
	if ((VM_USE_BITRIX == 1) or (isset($_REQUEST ['version']))){
		$use_bitrix  = true;
		CreateZakaz($use_bitrix);
	}else{
		CreateZakaz();
	}	
}
//*******************Проверка подключения для обмена заказами*******************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'checkauth') 
{
	if (($domain == dsCrypt($dm,1)) or ($domain == dsCrypt($dmw,1)) or ($domain == dsCrypt($dmt,1)))  {	
		$remote_user = '';
		if (isset($_SERVER['REMOTE_USER'])){
			$remote_user = $_SERVER['REMOTE_USER'];	
		}else{
			if (isset($_SERVER['REDIRECT_REMOTE_USER'])){
				$remote_user = $_SERVER['REDIRECT_REMOTE_USER'];	
			}	
		}
		$strTmp = base64_decode(substr($remote_user,6));
		if ($strTmp){
			list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $strTmp);
		}
		print CheckAuthUser();
			
		if (USE_COOKIES == 1){
			print "\n" . "key";
			print "\n" . $print_key;
		}
	}
}

//*******************Выбор архивировать или нет********************************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'init') 
{
	if (isset($_REQUEST ['version'])){
		print "zip=no" . "\n" . "file_limit=".VM_ZIPSIZE. "\n" . "xml_version". "\n" . "3.1";
	}else{
		print "zip=no" . "\n" . "file_limit=".VM_ZIPSIZE;
	}
}

if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'success') 
{
	print 'success';
}

//********************Информация о методах доставки и статусов заказа на сайте (УНФ 1.6.5, требуется расширение АдаптацияОбменаССайтомДляExchange1COpencartУНФ16.cfe)*************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'sale' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'info') 
{
global $db;
	
	$no_spaces ='<?xml version="1.0" encoding="UTF-8"?><saleinfo></saleinfo>';
	$xml = new SimpleXMLElement ( $no_spaces );
	
	$status_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "order_status WHERE language_id = ".LANGUAGE_ID.""); 
	if ($status_query->num_rows) {
		$status_doc = $xml->addChild ( "Статусы" );
		foreach (($status_query->rows) as $status){
			$t1 = $status_doc->addChild ( "Элемент");
			$t2 = $t1->addChild ( "Ид", $status['order_status_id'] );
			$t2 = $t1->addChild ( "Название", $status['name']);
		}
	}
	
	$delivery_query  = $db->query ( "SELECT DISTINCT shipping_method FROM " . DB_PREFIX . "order"); 
	if ($delivery_query->num_rows) {
		$delivery_doc = $xml->addChild ( "СлужбыДоставки" );
		foreach (($delivery_query->rows) as $delivery){
			$shipping_code = rus2translit($delivery['shipping_method']);
			$shipping_code = substr((md5($shipping_code)),1,10); // формируем уникальный код из наименования
			$t1 = $delivery_doc->addChild ( "Элемент");
			$t2 = $t1->addChild ( "Ид", $shipping_code ); 
			$t2 = $t1->addChild ( "Название", $delivery['shipping_method']);
		}
	}

	$payment_method_query = $db->query ( "SELECT DISTINCT payment_method FROM " . DB_PREFIX . "order");
	if ($payment_method_query->num_rows) {
		$payment_method_doc = $xml->addChild ( "ПлатежныеСистемы" );
		foreach (($payment_method_query->rows) as $payment_method){
			$payment_method_code = rus2translit($payment_method['payment_method']);
			$payment_method_code = substr((md5($payment_method_code)),1,10); // формируем уникальный код из наименования
			$t1 = $payment_method_doc->addChild ( "Элемент");
			$t2 = $t1->addChild ( "Ид", $payment_method_code );
			$t2 = $t1->addChild ( "Название", $payment_method['payment_method']);
		}
		$t1 = $payment_method_doc->addChild ( "Элемент");
		$t2 = $t1->addChild ( "Ид", "Интернет" );
		$t2 = $t1->addChild ( "Название", "Интернет");
	}
	
	if (VM_CODING == 'UTF-8'){
		$xml_text = $xml->asXML();
		header("Content-Type: text/xml");
		$text = iconv( "UTF-8", "CP1251//IGNORE", $xml_text );
		print $text;
	}else {
		header("Content-Type: text/xml");
		print $xml->asXML ();
	}
}

//*******************Запуск асинхронного разбора файлов********************************
if ((isset($_GET['namefile'])) and (isset($_GET['number_file'])) and (isset($_GET['create_name_file']))) {
	$namefile = $_GET['namefile'];
	$number_file = $_GET['number_file'];
	$create_name_file = $_GET['create_name_file'];
	global $posix;
	write_log("Процесс(".$posix."). Запуск асинхронного разбора файла: ".$create_name_file);
	startUpload($namefile, $number_file, $create_name_file);
}

//********************Подключение мобильного приложения*************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'mobile' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'init') 
{
	$no_spaces ='<?xml version="1.0" encoding="UTF-8"?><exchangerate></exchangerate>';
	$xml = new SimpleXMLElement ( $no_spaces );
	$doc = $xml->addChild ( "exchangerate permission='true'" );
	print iconv ( "utf-8", "windows-1251", $xml->asXML () );
}

//********************Очистка состояний обмена*************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'exchange' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'clear') 
{
//exchange_1C_Opencart.php?type=exchange&mode=clear
	global $db;	
	$status_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "status_exchange_1c"); 
	if ($status_query->num_rows) {
		foreach (($status_query->rows) as $status_exchange){
			$filename = $status_exchange['filename'];
			saveStatusProgress ($filename, 'start', 'clear');	
		}
	}
	echo 'sucsess! clear loads';
}

//********************Отобразить состояние обмена на сайте*************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'exchange' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'status') 
{
//exchange_1C_Opencart.php?type=exchange&mode=status
global $db;	
	$status_query  = $db->query ( "SELECT * FROM " . DB_PREFIX . "status_exchange_1c"); 
	if ($status_query->num_rows) {
		print('<table border="1">');
		print('<tr>');
		print('<td>ID</td>');
		print('<td>FILENAME</td>');
		print('<td>STATUS</td>');
		print('<td>ERROR</td>');
		print('<td>DATE_EXCHANGE</td>');
		print('</tr>');
		foreach (($status_query->rows) as $status_exchange){
			print('<tr>');
			$id = $status_exchange['id'];
			print('<td>'.$id.'</td>');
			$filename = $status_exchange['filename'];
			print('<td>'.$filename.'</td>');
			$status = $status_exchange['status'];
			print('<td>'.$status.'</td>');
			$error = $status_exchange['error'];
			print('<td>'.$error.'</td>');
			$date_exchange = $status_exchange['date_exchange'];
			print('<td>'.$date_exchange.'</td>');
			print('</tr>');
		}
		print('</table>');
	}else{
		print('no data exchange.');
	}
	unset($db);
	exit();
}

//***************ДеактивацияДанныхПоДате*************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'deactivate') 
{
	print 'success';
}

//***************ОкончаниеВыгрузкиТоваров*************
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'catalog' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'complete') 
{
	print 'success';
}

//******Аутентификация для передачи доп. данных*******
if (isset ( $_REQUEST ['type'] ) && $_REQUEST ['type'] == 'reference' && isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'checkauth') 
{
	print 'failure'."\n" . 'not use this function in module';
}
unset($db);
?>