<?php
// Define path to application directory
if (!defined('ROOT_PATH'))
	define('ROOT_PATH', __DIR__);

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(ROOT_PATH . '/library'),
	get_include_path(),
)));
ini_set('display_errors', true);

date_default_timezone_set('Europe/Prague');

if (file_exists('config.php')) {
	require_once 'config.php';
}

defined('GOODDATA_WRITER_API_URL')
	|| define('GOODDATA_WRITER_API_URL', getenv('GOODDATA_WRITER_API_URL') ? getenv('GOODDATA_WRITER_API_URL') : 'https://gooddata-writer.keboola.com');

defined('STORAGE_API_TOKEN')
	|| define('STORAGE_API_TOKEN', getenv('STORAGE_API_TOKEN') ? getenv('STORAGE_API_TOKEN') : 'your_token');

require_once ROOT_PATH . '/vendor/autoload.php';