
<?php
/**
 * project_settings.php
 *
 * Created by: Charles Wheelus  -  7/13/18
 * charles@wheelus.com
 */

define('PROJECT',"iot1");

define('OWNER',"chuck");
define('HOME','/home/chuck/');
define('DATADIR',HOME.PROJECT.'/pcaps/');
define('WORKDIR',HOME.PROJECT.'/bro-output/');
define('LOGDIR',HOME.PROJECT.'/logs/');
date_default_timezone_set('America/New_York');
define('DEBUG',FALSE);
define('CON',"host=11.33.33.4 port=5432 dbname=".PROJECT." user=".OWNER." password=password");
define('GOODIPS',HOME.PROJECT.'/labeldata/legitimate-ipaddresses.txt');
$admin_ips=array('11.22/16','11.33.44/24');
define('CONCENTRATORIP','11.33.33.2');
define('PORTALIP','11.33.33.3');


define('BROLIMIT',1000);
define('SHARKLIMIT',1000);
define('PUSHPLIMIT',1000);
define('PUSHSLIMIT',1000);
define('TPLIMIT',1);  // must stay "1" so it can execute concurrently
define('CFEATLIMIT',500);

error_reporting(E_ERROR | E_PARSE);

?>
