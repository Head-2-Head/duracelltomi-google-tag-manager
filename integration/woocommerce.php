<?php
$gtm4wp_woocommerce_completed_order_id = 0;
$gtp4wp_woocommerce_remarketing_sku_list = array();
$gtp4wp_woocommerce_remarketing_totalvalue = 0;

function gtm4wp_woocommerce_datalayer_filter_order( $dataLayer ) {
	global $gtm4wp_woocommerce_completed_order_id, $woocommerce;

	$order = new WC_Order( $gtm4wp_woocommerce_completed_order_id );

	$dataLayer["transactionId"]             = $order->get_order_number();
	$dataLayer["transactionDate"]           = date("c");
	$dataLayer["transactionType"]           = "sale";
	$dataLayer["transactionAffiliation"]    = get_bloginfo( 'name' );
	$dataLayer["transactionTotal"]          = $order->get_total();
	$dataLayer["transactionShipping"]       = $order->get_shipping();
	$dataLayer["transactionTax"]            = $order->get_total_tax();
	$dataLayer["transactionPaymentType"]    = $order->payment_method_title;
	$dataLayer["transactionCurrency"]       = get_woocommerce_currency();
	$dataLayer["transactionShippingMethod"] = $order->get_shipping_method();
	$dataLayer["transactionPromoCode"]      = implode( ", ", $order->get_used_coupons() );

	$_products = array();
	$_sumprice = 0;
	$_product_ids = array();

	if ( $order->get_items() ) {
		foreach ( $order->get_items() as $item ) {
			$_product = $order->get_product_from_item( $item );

        		if ( isset( $_product->variation_data ) ) {

				$_category = woocommerce_get_formatted_variation( $_product->variation_data, true );

			} else {
				$out = array();
				$categories = get_the_terms( $_product->id, 'product_cat' );
				if ( $categories ) {
					foreach ( $categories as $category ) {
						$out[] = $category->name;
					}
				}
				
				$_category = implode( " / ", $out );
			}

			$_prodprice = $order->get_item_total( $item );
			$_products[] = array(
			  "id"       => $_product->id,
			  "name"     => $item['name'],
			  "sku"      => $_product->get_sku() ? __( 'SKU:', GTM4WP_TEXTDOMAIN ) . ' ' . $_product->get_sku() : $_product->id,
			  "category" => $_category,
			  "price"    => $_prodprice,
			  "currency" => get_woocommerce_currency(),
			  "quantity" => $item['qty']
			);
			
			$_sumprice += $_prodprice;
			$_product_ids[] = "'" . $_product->id . "'";
		}
	}

	$dataLayer["transactionProducts"] = $_products;
	$dataLayer["event"] = "gtm4wp.orderCompleted";

	$dataLayer["ecomm_prodid"] = '[' . implode(", ", $_product_ids) . ']';
	$dataLayer["ecomm_pagetype"] = "purchase";
	$dataLayer["ecomm_totalvalue"] = $_sumprice;

	return $dataLayer;
}

function gtm4wp_woocommerce_thankyou( $order_id ) {
	global $gtm4wp_woocommerce_completed_order_id;

	if ( 1 == get_post_meta( $order_id, '_ga_tracked', true ) ) {
		return;
	}

	$gtm4wp_woocommerce_completed_order_id = $order_id;
	add_filter( GTM4WP_WPFILTER_COMPILE_DATALAYER, "gtm4wp_woocommerce_datalayer_filter_order" );

	update_post_meta( $order_id, '_ga_tracked', 1 );
}

function gtm4wp_woocommerce_datalayer_filter_items( $dataLayer ) {
	global $woocommerce, $gtp4wp_woocommerce_remarketing_sku_list, $gtp4wp_woocommerce_remarketing_totalvalue;

	if ( is_front_page() ) {
		$dataLayer["ecomm_prodid"] = "";
		$dataLayer["ecomm_pagetype"] = "home";
		$dataLayer["ecomm_totalvalue"] = 0;
	} else if ( is_product_category() || is_product_tag() ) {
		$sumprice = 0;
		$product_ids = array();
		foreach ( $woocommerce->query->filtered_product_ids as $oneproductid ) {
			$product = get_product( $oneproductid );
			$sumprice += $product->get_price();
			$product_ids[] = "'" . $oneproductid . "'";
		}

		$dataLayer["ecomm_prodid"] = '[' . implode( ", ", $product_ids ) . ']';
		$dataLayer["ecomm_pagetype"] = "category";
		$dataLayer["ecomm_totalvalue"] = $sumprice;
	} else if ( is_product() ) {
		$prodid = get_the_ID();
		$product = get_product( $prodid );
		$product_price = $product->get_price();

		$dataLayer["ecomm_prodid"] = $prodid;
		$dataLayer["ecomm_pagetype"] = "product";
		$dataLayer["ecomm_totalvalue"] = $product_price;
	} else if ( is_cart() ) {
		$products = $woocommerce->cart->get_cart();
		$product_ids = array();
		foreach( $products as $oneproduct ) {
			$product_ids[] = "'" . str_replace( "'", "\\'", $oneproduct['product_id'] ) . "'";
		}

		$dataLayer["ecomm_prodid"] = '[' . implode( ", ", $product_ids ) . ']';
		$dataLayer["ecomm_pagetype"] = "cart";
		$dataLayer["ecomm_totalvalue"] = $woocommerce->cart->cart_contents_total;
	} else if ( !is_order_received_page() ) {
		$dataLayer["ecomm_pagetype"] = "siteview";
	}

	return $dataLayer;
}

add_action( "woocommerce_thankyou", "gtm4wp_woocommerce_thankyou" );
add_filter( GTM4WP_WPFILTER_COMPILE_DATALAYER, "gtm4wp_woocommerce_datalayer_filter_items" );
