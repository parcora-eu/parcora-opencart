<?php
// Heading
$_['heading_title']          = 'Parcora';
$_['extension_heading_title'] = 'Parcora';

// Text
$_['text_extension']         = 'Extensions';
$_['text_success']           = 'Success: You have modified Parcora shipping!';
$_['text_edit']              = 'Edit Parcora Shipping';
$_['text_none']              = '--- None ---';
$_['text_manual']            = 'Manually, from the order page';
$_['text_all_zones']         = 'All Zones';
$_['text_carrier_default']   = 'Carrier default';
$_['text_mode_api']          = 'Parcora rate + markup';
$_['text_mode_fixed']        = 'Fixed price';
$_['text_connected']         = 'Connected';
$_['text_not_connected']     = 'Test mode only';
$_['text_no_services']       = 'Enter your API key and save to load the carriers available to your account.';
$_['text_webhook_on']        = 'Tracking updates are on (endpoint %s).';
$_['text_webhook_off']       = 'Tracking updates are off. Register the webhook so delivered orders update by themselves.';
$_['text_webhook_success']   = 'Success: Webhook registered!';
$_['text_shipment_success']  = 'Success: Shipment created!';
$_['text_shipment']          = 'Shipment';
$_['text_no_shipment']       = 'No shipment yet.';
$_['text_tracking']          = 'Tracking';
$_['text_pickup_point']      = 'Pickup point';

// Tab
$_['tab_general']            = 'General';
$_['tab_sender']             = 'Sender';
$_['tab_services']           = 'Services';
$_['tab_orders']             = 'Orders';

// Entry
$_['entry_api_key']          = 'API key';
$_['entry_base_url']         = 'API URL';
$_['entry_tax_class']        = 'Tax Class';
$_['entry_geo_zone']         = 'Geo Zone';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_name']             = 'Name';
$_['entry_phone']            = 'Phone';
$_['entry_email']            = 'E-Mail';
$_['entry_line1']            = 'Address';
$_['entry_city']             = 'City';
$_['entry_postcode']         = 'Postcode';
$_['entry_country']          = 'Country';
$_['entry_default_weight']   = 'Default weight (g)';
$_['entry_create_status']    = 'Create shipment when order status becomes';
$_['entry_delivered_status'] = 'Set order status when delivered';
$_['entry_label_format']     = 'Label format';
$_['entry_webhook']          = 'Tracking webhook';

// Column
$_['column_service']         = 'Service';
$_['column_enabled']         = 'Enabled';
$_['column_title']           = 'Title at checkout';
$_['column_mode']            = 'Price';
$_['column_markup_percent']  = 'Markup %';
$_['column_markup_amount']   = 'Markup amount';
$_['column_fixed']           = 'Fixed price';
$_['column_free_from']       = 'Free from';

// Help
$_['help_api_key']           = 'A dp_test_ key for testing, dp_live_ for real shipments. Get it in the Parcora dashboard.';
$_['help_base_url']          = 'Leave empty for https://api.parcora.eu.';
$_['help_default_weight']    = 'Used when the cart has no product weights.';
$_['help_title']             = 'Empty means carrier and service name.';
$_['help_free_from']         = 'Order subtotal from which this service is free. 0 turns it off.';

// Button
$_['button_create_shipment'] = 'Create shipment';
$_['button_label']           = 'Download label';
$_['button_webhook']         = 'Register webhook';

// Warning
$_['warning_telephone']      = 'Carriers need the buyer\'s phone number for parcel lockers and pickup points. Turn on "Telephone Display" and "Telephone Required" in System > Settings > Option.';

// Error
$_['error_permission']      = 'Warning: You do not have permission to modify Parcora shipping!';
$_['error_api_key']          = 'API key is required!';
$_['error_sender']           = 'Sender name, address, city, postcode and country are required!';
$_['error_carriers']         = 'Could not load carriers: %s';
$_['error_order']            = 'This order was not shipped with Parcora.';
$_['error_shipment_exists']  = 'This order already has a shipment.';
