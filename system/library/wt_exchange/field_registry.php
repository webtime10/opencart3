<?php
/**
 * Реестр полей WT Exchange (коды как в CSV Price Pro: _ID_, _NAME_, ...).
 */
class WtExchangeFieldRegistry {
	public static function productFields() {
		return array(
			'_ID_'               => array('label' => 'ID', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => true),
			'_MAIN_CATEGORY_'    => array('label' => 'Main Category', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_CATEGORY_'         => array('label' => 'Categories', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_CATEGORY_ID_'      => array('label' => 'Category IDs', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_NAME_'             => array('label' => 'Name', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_MODEL_'            => array('label' => 'Model', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => true),
			'_SKU_'              => array('label' => 'SKU', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => true),
			'_EAN_'              => array('label' => 'EAN', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_JAN_'              => array('label' => 'JAN', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_ISBN_'             => array('label' => 'ISBN', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_MPN_'              => array('label' => 'MPN', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_UPC_'              => array('label' => 'UPC', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_MANUFACTURER_'     => array('label' => 'Manufacturer', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_LOCATION_'         => array('label' => 'Location', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_PRICE_'            => array('label' => 'Price', 'type' => 'float', 'export' => true, 'import' => true, 'keyable' => false),
			'_DISCOUNT_'         => array('label' => 'Discount', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_SPECIAL_'          => array('label' => 'Special offer', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_OPTIONS_'          => array('label' => 'Options', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_FILTERS_'          => array('label' => 'Filters', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_POINTS_'           => array('label' => 'Points (price)', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => false),
			'_REWARD_POINTS_'    => array('label' => 'Reward Points', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_QUANTITY_'         => array('label' => 'Quantity', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => false),
			'_STOCK_STATUS_'     => array('label' => 'Stock status', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_STOCK_STATUS_ID_'  => array('label' => 'Stock status ID', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => false),
			'_SHIPPING_'         => array('label' => 'Requires Shipping', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => false),
			'_LENGTH_'           => array('label' => 'Dimensions (length)', 'type' => 'float', 'export' => true, 'import' => true, 'keyable' => false),
			'_WIDTH_'            => array('label' => 'Dimensions (width)', 'type' => 'float', 'export' => true, 'import' => true, 'keyable' => false),
			'_HEIGHT_'           => array('label' => 'Dimensions (height)', 'type' => 'float', 'export' => true, 'import' => true, 'keyable' => false),
			'_WEIGHT_'           => array('label' => 'Weight', 'type' => 'float', 'export' => true, 'import' => true, 'keyable' => false),
			'_SEO_KEYWORD_'      => array('label' => 'SEO Keyword', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_META_H1_'          => array('label' => 'HTML Tag H1', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_META_TITLE_'       => array('label' => 'Meta Tag Title', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_META_KEYWORDS_'    => array('label' => 'Meta Tag Keywords', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_META_DESCRIPTION_' => array('label' => 'Meta Tag Description', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_DESCRIPTION_'      => array('label' => 'Description', 'type' => 'html', 'export' => true, 'import' => true, 'keyable' => false),
			'_ATTRIBUTES_'       => array('label' => 'Attributes', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_PRODUCT_TAG_'      => array('label' => 'Product Tags', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_IMAGE_'            => array('label' => 'Image', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_IMAGES_'           => array('label' => 'Additional images', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_PRODUCT_IMAGES_'   => array('label' => 'Product Images', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_SORT_ORDER_'       => array('label' => 'Sort order', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => false),
			'_STATUS_'           => array('label' => 'Status', 'type' => 'int', 'export' => true, 'import' => true, 'keyable' => false),
			'_STORE_ID_'         => array('label' => 'Store ID', 'type' => 'string', 'export' => true, 'import' => true, 'keyable' => false),
			'_URL_'              => array('label' => 'Url', 'type' => 'string', 'export' => true, 'import' => false, 'keyable' => false)
		);
	}

	public static function defaultProductExportFields() {
		return array('_ID_', '_NAME_', '_MODEL_', '_SKU_', '_PRICE_', '_QUANTITY_', '_STATUS_');
	}

	public static function importableProductFields() {
		$fields = array();
		foreach (self::productFields() as $code => $meta) {
			if (!empty($meta['import'])) {
				$fields[] = $code;
			}
		}
		return $fields;
	}

	public static function productKeyOptions() {
		return array(
			'_ID_'    => 'Product ID',
			'_MODEL_' => 'Model',
			'_SKU_'   => 'SKU'
		);
	}

	/**
	 * File encodings like CSV Price Pro (labels for UI, values for iconv/mb).
	 */
	public static function encodings() {
		return array(
			'ISO-8859-1'   => 'ISO-8859-1 (Western Europe)',
			'ISO-8859-5'   => 'ISO-8859-5 (Cyrillic, DOS)',
			'KOI8-R'       => 'KOI8-R (Cyrillic, Unix)',
			'UNICODE'      => 'UNICODE (MS Excel text format)',
			'UTF-8'        => 'UTF-8',
			'windows-1250' => 'windows-1250 (Central European languages)',
			'windows-1251' => 'windows-1251 (Cyrillic)',
			'windows-1252' => 'windows-1252 (Western languages)',
			'windows-1253' => 'windows-1253 (Greek)',
			'windows-1254' => 'windows-1254 (Turkish)',
			'windows-1255' => 'windows-1255 (Hebrew)',
			'windows-1256' => 'windows-1256 (Arabic)',
			'windows-1257' => 'windows-1257 (Baltic languages)',
			'windows-1258' => 'windows-1258 (Vietnamese)'
		);
	}

	public static function categoryFields() {
		return array(
			'_ID_'               => array('label' => 'ID', 'type' => 'int', 'import' => true, 'keyable' => true),
			'_PARENT_ID_'        => array('label' => 'Parent category ID', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_NAME_'             => array('label' => 'Name', 'type' => 'string', 'import' => true, 'keyable' => true),
			'_SEO_KEYWORD_'      => array('label' => 'SEO Keyword', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_H1_'          => array('label' => 'HTML Tag H1', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_TITLE_'       => array('label' => 'Meta Tag Title', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_KEYWORDS_'    => array('label' => 'Meta Tag Keywords', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_DESCRIPTION_' => array('label' => 'Meta Tag Description', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_DESCRIPTION_'      => array('label' => 'Description', 'type' => 'html', 'import' => true, 'keyable' => false),
			'_IMAGE_'            => array('label' => 'Image', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_TOP_'              => array('label' => 'Top menu', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_COLUMN_'           => array('label' => 'Columns', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_SORT_ORDER_'       => array('label' => 'Sort Order', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_STATUS_'           => array('label' => 'Status', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_STORE_ID_'         => array('label' => 'Stores ID', 'type' => 'string', 'import' => true, 'keyable' => false)
		);
	}

	public static function defaultCategoryExportFields() {
		return array('_ID_', '_PARENT_ID_', '_NAME_', '_STATUS_', '_SORT_ORDER_');
	}

	public static function categoryKeyOptions() {
		return array(
			'_ID_'   => 'Category ID',
			'_NAME_' => 'Name'
		);
	}

	public static function manufacturerFields() {
		return array(
			'_ID_'               => array('label' => 'ID', 'type' => 'int', 'import' => true, 'keyable' => true),
			'_NAME_'             => array('label' => 'Name', 'type' => 'string', 'import' => true, 'keyable' => true),
			'_SEO_KEYWORD_'      => array('label' => 'SEO Keyword', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_H1_'          => array('label' => 'HTML Tag H1', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_TITLE_'       => array('label' => 'Meta Tag Title', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_KEYWORDS_'    => array('label' => 'Meta Tag Keywords', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_META_DESCRIPTION_' => array('label' => 'Meta Tag Description', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_DESCRIPTION_'      => array('label' => 'Description', 'type' => 'html', 'import' => true, 'keyable' => false),
			'_IMAGE_'            => array('label' => 'Image', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_SORT_ORDER_'       => array('label' => 'Sort Order', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_STORE_ID_'         => array('label' => 'Store ID', 'type' => 'string', 'import' => true, 'keyable' => false)
		);
	}

	public static function defaultManufacturerExportFields() {
		return array('_ID_', '_NAME_', '_SORT_ORDER_', '_IMAGE_');
	}

	public static function manufacturerKeyOptions() {
		return array(
			'_ID_'   => 'Manufacturer ID',
			'_NAME_' => 'Name'
		);
	}

	public static function customerFields() {
		return array(
			'_ID_'         => array('label' => 'Customer Id', 'type' => 'int', 'import' => true, 'keyable' => true),
			'_FIRST_NAME_' => array('label' => 'First name', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_LAST_NAME_'  => array('label' => 'Last name', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_NAME_'       => array('label' => 'Name', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_EMAIL_'      => array('label' => 'Email', 'type' => 'string', 'import' => true, 'keyable' => true),
			'_TELEPHONE_'  => array('label' => 'Phone number', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_FAX_'        => array('label' => 'Fax', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_COMPANY_'    => array('label' => 'Company', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_COUNTRY_'    => array('label' => 'Country', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_CITY_'       => array('label' => 'City', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_POSTCODE_'   => array('label' => 'Zip-code', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_ADDRESS_1_'  => array('label' => 'Address 1', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_ADDRESS_2_'  => array('label' => 'Address 2', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_GROUP_'      => array('label' => 'Customer group', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_STATUS_'     => array('label' => 'Status', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_NEWSLETTER_' => array('label' => 'Newsletter', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_PASSWORD_'   => array('label' => 'Password', 'type' => 'string', 'import' => true, 'keyable' => false)
		);
	}

	public static function defaultCustomerExportFields() {
		return array('_ID_', '_FIRST_NAME_', '_LAST_NAME_', '_EMAIL_', '_TELEPHONE_', '_GROUP_', '_STATUS_');
	}

	public static function customerKeyOptions() {
		return array(
			'_ID_'    => 'Customer ID',
			'_EMAIL_' => 'Email'
		);
	}

	public static function orderFields() {
		return array(
			'_ID_'               => array('label' => 'Order ID', 'type' => 'int', 'import' => true, 'keyable' => true),
			'_INVOICE_NO_'       => array('label' => 'Invoice No', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_CUSTOMER_ID_'      => array('label' => 'Customer ID', 'type' => 'int', 'import' => false, 'keyable' => false),
			'_CUSTOMER_NAME_'    => array('label' => 'Customer Name', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_EMAIL_'            => array('label' => 'Email', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_TELEPHONE_'        => array('label' => 'Telephone', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_STATUS_'           => array('label' => 'Order Status', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_STATUS_ID_'        => array('label' => 'Order Status ID', 'type' => 'int', 'import' => true, 'keyable' => false),
			'_TOTAL_'            => array('label' => 'Total', 'type' => 'float', 'import' => false, 'keyable' => false),
			'_CURRENCY_'         => array('label' => 'Currency', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_DATE_ADDED_'       => array('label' => 'Date Added', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_PAYMENT_METHOD_'  => array('label' => 'Payment Method', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_SHIPPING_METHOD_'  => array('label' => 'Shipping Method', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_PRODUCTS_'         => array('label' => 'Products', 'type' => 'string', 'import' => false, 'keyable' => false),
			'_COMMENT_'          => array('label' => 'Comment', 'type' => 'string', 'import' => true, 'keyable' => false),
			'_STORE_ID_'         => array('label' => 'Store ID', 'type' => 'int', 'import' => false, 'keyable' => false)
		);
	}

	public static function defaultOrderExportFields() {
		return array('_ID_', '_CUSTOMER_NAME_', '_EMAIL_', '_STATUS_', '_TOTAL_', '_DATE_ADDED_', '_PRODUCTS_');
	}

	public static function orderKeyOptions() {
		return array(
			'_ID_' => 'Order ID'
		);
	}

	public static function entityFields($entity) {
		switch ($entity) {
			case 'category': return self::categoryFields();
			case 'manufacturer': return self::manufacturerFields();
			case 'customer': return self::customerFields();
			case 'order': return self::orderFields();
		}
		return array();
	}

	public static function defaultEntityExportFields($entity) {
		switch ($entity) {
			case 'category': return self::defaultCategoryExportFields();
			case 'manufacturer': return self::defaultManufacturerExportFields();
			case 'customer': return self::defaultCustomerExportFields();
			case 'order': return self::defaultOrderExportFields();
		}
		return array();
	}

	public static function entityKeyOptions($entity) {
		switch ($entity) {
			case 'category': return self::categoryKeyOptions();
			case 'manufacturer': return self::manufacturerKeyOptions();
			case 'customer': return self::customerKeyOptions();
			case 'order': return self::orderKeyOptions();
		}
		return array('_ID_' => 'ID');
	}
}
