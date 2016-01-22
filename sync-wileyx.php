<?php
$cron = $argv[1];
error_reporting(E_ALL);
ini_set('display_errors', true);

require('./engine/CMSMain.inc.php');
require_once('./engine/simple_html_dom.php');
CMSGlobal::setTEXTHeader();

if (!$cron && !CMSLogicAdmin::getInstance()->isLoggedUser()) {
	die('LOGIN REQUIRED');
}

CMSProcess::instance()->start_process(CMSLogicProvider::WILEYX, "parse");

CMSPluginSession::getInstance()->close();

$parser = new CMSClassGlassesParserWileyx();

if (!$parser->syncLock()) {
	die('Wileyx parser already running');
}

echo 'Syncing Wileyx', "\n";

try {
	$parser->sync();
} catch (Exception $e) {
	print_r($e); die;
}

$parser->syncUnlock();
$sssss = updateAvlTimeForItems();

CMSProcess::instance()->end_process();

echo "DONE\n";