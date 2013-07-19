<?php

// This is the default configuration file. You will at least need to
// change the field values denoted by "CHANGE".

// Title of the SMS provider as seen in Administer - System Settings - SMS Providers - Edit
define("MYCRAVINGS_SMS_PROVIDER_NAME", "Twilio - prod");

// SMS message template title as seen in Administer - Message Templates
define("MYCRAVINGS_SMS_MESSAGE_TEMPLATE", "MyCravings - SMS");

// The long URL to send in SMS messages
define("MYCRAVINGS_SMS_MESSAGE_LONG_URL", "http://mycravings.ca/survey2013");

// Suffix to append to the unique short URL key
define("MYCRAVINGS_SMS_MESSAGE_SHORT_URL_SUFFIX", "-mycravings");

// The short URL token stored in the SMS message template. This will be replaced
// with the shortened URL as registered with YOURLS.
define("MYCRAVINGS_URL_TOKEN_EXP", "/{mycravings_url}/");

// Email message template title as seen in Administer - Message Templates
define("MYCRAVINGS_EMAIL_MESSAGE_TEMPLATE", "MyCravings - Email");

// The From address to include in the MyCravings email
define("MYCRAVINGS_EMAIL_FROM_ADDRESS", "CHANGE");

// YOURLS server API endpoint
define("MYCRAVINGS_YOURLS_URL", "CHANGE");

// Unique YOURLS signature used for API authentication
define("MYCRAVINGS_YOURLS_SIGNATURE", "CHANGE");

//
// Entity Configuration
//
// o Name of the MyCravings CustomGroup that contains the fields used by this extension
//   This is the value that appears in the name field when issuing an API request.
// o Title is the externally-visible title of the entity
//

// OptionGroup - "MyCravings Magazine"
define("MYCRAVINGS_OPTION_GROUP_MAGAZINE_NAME", "MyCravings_Magazine");
define("MYCRAVINGS_OPTION_GROUP_MAGAZINE_TITLE", "MyCravings - Magazine");

// OptionValue - "MyCravings Magazine - No"
define("MYCRAVINGS_OPTION_MAGAZINE_NO_VALUE", "magazine-no");

// OptionGroup - "MyCravings Journey"
define("MYCRAVINGS_OPTION_GROUP_JOURNEY_NAME", "MyCravings_Journey");
define("MYCRAVINGS_OPTION_GROUP_JOURNEY_TITLE", "MyCravings - Journey");

// OptionValue - "MyCravings Journey - No"
define("MYCRAVINGS_OPTION_JOURNEY_NO_VALUE", "journey-nothing");

// OptionGroup - "MyCravings Gauge"
define("MYCRAVINGS_OPTION_GROUP_GAUGE_NAME", "MyCravings_Gauge");
define("MYCRAVINGS_OPTION_GROUP_GAUGE_TITLE", "MyCravings - Gauge");

// OptionValue - "MyCravings Gauge - Value prefix"
define("MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX", "gauge");

// CustomGroup - "MyCravings - Common"
define("MYCRAVINGS_CUSTOM_GROUP_COMMON_NAME", "MyCravings_Common");
define("MYCRAVINGS_CUSTOM_GROUP_COMMON_TITLE", "MyCravings - Common");

// CustomField - "MyCravings - Magazine"
define("MYCRAVINGS_CUSTOM_FIELD_MAGAZINE_NAME", "MyCravings_Magazine");
define("MYCRAVINGS_CUSTOM_FIELD_MAGAZINE_TITLE", "MyCravings - Magazine");

// CustomField - "MyCravings - Journey"
define("MYCRAVINGS_CUSTOM_FIELD_JOURNEY_NAME", "MyCravings_Journey");
define("MYCRAVINGS_CUSTOM_FIELD_JOURNEY_TITLE", "MyCravings - Journey");

// CustomField - "MyCravings - Gauge"
define("MYCRAVINGS_CUSTOM_FIELD_GAUGE_NAME", "MyCravings_Gauge");
define("MYCRAVINGS_CUSTOM_FIELD_GAUGE_TITLE", "MyCravings - Gauge");
