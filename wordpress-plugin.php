<?php


/**
 * Plugin Name: Example Envato WordPress Plugin
 * Description: Example WordPress Plugin showing integration with Envato API
 * Plugin URI: http://dtbaker.net
 * Version: 1.0.1
 * Author: dtbaker
 * Author URI: http://dtbaker.net
 * Text Domain: dtbaker-envato
 *
 * Register a personal token on build.envato.com and put your token in the below code: PUT_YOUR_PERSONAL_TOKEN_HERE
 */


class DtbakerEnvatoWordPress{
	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function init() {
		add_shortcode( 'envato_recent_sales', array( $this, 'envato_recent_sales' ) );
	}


	public function get_recent_sales(){

		// Cache recent sale data in transient to speed things up.
		$sale_data = get_transient('envato_sales_history');
		if(!$sale_data || !is_array($sale_data)){
			$sale_data = array();
		}
		// Cache item details in transient to speed things up.
		$item_cache = get_transient('envato_item_cache');
		if(!$item_cache || !is_array($item_cache)){
			$item_cache = array();
		}
		if(!$sale_data){
			// No recent sale data in our cache, hit the API for details.

			require_once 'class.EnvatoAPI.php';

			$envato_api = EnvatoAPI::getInstance();
			$envato_api->set_mode('personal');
			$envato_api->set_personal_token('PUT_YOUR_PERSONAL_TOKEN_HERE');
			// Get the last 10 days of statement data from the API:
			$results = $envato_api->api('v3/market/user/statement?from_date='.date('Y-m-d',strtotime('-10 days')));

			// Attempt to group the numerous line items into individual "sales".
			// We do this based off the "OrderID" and the "ItemID".
			// This will fail if someone bought multiple of the same item in a single transaction.
			// todo: some better grouping logic. Share this login with javascript in Dashboard Plus.
			foreach($results as $result){
				if(!$result['item_id'])continue; // This statement item was a manual adjustment, withdrawal, etc.. Ignore it.
				$id = $result['order_id'].'-'.$result['item_id']; // our unique group id.
				if(!isset($sale_data[$id]))$sale_data[$id] = array(
					'list_price' => 0,
					'time' => strtotime($result['date']),
					'item_id' => $result['item_id'],
					'country' => '',
					'type' => '',
				);
				if(strpos($result['type'],'Author Fee') !== false){
					$sale_data[$id]['list_price'] -= $result['price'];
				}else{
					$sale_data[$id]['list_price'] += $result['price'];
					$sale_data[$id]['type'] = $result['type'];
				}
				if(!empty($result['other_party_country']))$sale_data[$id]['country'] = $result['other_party_country'];
			}
			// Calculate list price out based on 70% cut. Inaccurate but close enough.
			foreach($sale_data as $id => $recent_item_sale){
				if($recent_item_sale['type'] != 'Sale'){
					unset($sale_data[$id]);
					// remove purchases, refunds, etc... from our listing.
				}else{
					$sale_data[$id]['list_price'] = round($recent_item_sale['list_price'] / .7);
					// Get the item details along with the sale:
					if(!isset($item_cache[$recent_item_sale['item_id']])) {
						$item_data = $envato_api->api( 'v3/market/catalog/item?id=' . $recent_item_sale['item_id'] );
						$item_cache[$recent_item_sale['item_id']] = array(
							'name' => $item_data['name'],
							'price' => $item_data['price_cents']/100,
							'url' => $item_data['url'].'?ref=dtbaker',
							'thumb' => isset($item_data['previews']['icon_preview']['icon_url']) ? $item_data['previews']['icon_preview']['icon_url'] : $item_data['previews']['icon_with_landscape_preview']['icon_url'],
						);
					}
					$sale_data[$id]['item'] = $item_cache[$recent_item_sale['item_id']];
				}
			}
			// Cache sale data for 10 minutes.
			set_transient('envato_sales_history',$sale_data, 600);
		}
		// cache item data for much longer:
		set_transient('envato_item_cache',$item_cache, 604800);

		return $sale_data;

	}

	public function envato_recent_sales($atts = array()){
		ob_start();
		?>
		<div id="envato_recent_sales">
			<h3>Recent Sales:</h3>
			<pre><?php print_r($this->get_recent_sales());?></pre>
		</div>
		<?php
		return ob_get_clean();
	}


}
DtbakerEnvatoWordPress::get_instance()->init();
