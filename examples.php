<?php

require_once 'class.EnvatoAPI.php';

if(!defined('ENVATO_API_PERSONAL_TOKEN'))define('ENVATO_API_PERSONAL_TOKEN','your-personal-token-here');

/**
 * List recent item comments
 */
$envato_api = EnvatoAPI::getInstance();
$envato_api->set_mode('personal');
$envato_api->set_personal_token(ENVATO_API_PERSONAL_TOKEN);
$result = $envato_api->api('v1/discovery/search/search/comment?item_id=2621629');
print_r($result);


/**
 * oAuth Login:
 */

