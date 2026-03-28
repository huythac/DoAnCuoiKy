<?php

declare(strict_types=1);

use App\MyApp;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Error\Debugger;
use Cake\I18n\DateTime;
use MongoDB\BSON\ObjectId;

ini_set("session.cookie_domain", ".syndium.vn");
define("SERVER_RUN_MODE", "DEV");
#define( "SERVER_RUN_MODE", "TEST" );
// define( "SERVER_RUN_MODE", "REAL"  );
#$url = env("REQUEST_SCHEME") . '://' .  env("SERVER_NAME");

switch (SERVER_RUN_MODE) {
    case "REAL": // real
        //URL base
        $url = 'https://syndium.vn';
        define("URL_BASE", $url);
        define("URL_STATIC", 'https://cdn.syndium.vn');
        define("URL_LUXURY", 'https://luxury.syndium.vn');
        define("FILE_STATIC", 'https://cdn.syndium.vn'); // . '/file.php?');
        define("CHAT_SERVER", "tcp://" . env("SERVER_NAME") . ":61613");
        define("NOTIFY_SERVER", 'http://222.255.72.18:8889');
        define("PUSH_SERVER", env("SERVER_NAME"));
        define("DISABLE_CACHE", false);

        //define twitter
        define('YOUR_CONSUMER_KEY', 'YOUR_TWITTER_CONSUMER_KEY_HERE');
        define('YOUR_CONSUMER_SECRET', 'YOUR_TWITTER_CONSUMER_SECRET_HERE');
        define("REGIST_TWITTER", URL_BASE . "/Users/registertwitter");

        // info contact gmail
        define('CONSUMER_KEY_G', 'YOUR_CONSUMER_KEY_G_HERE');
        define('CONSUMER_SECRET_G', 'YOUR_CONSUMER_SECRET_G_HERE');
        define('CALLBACK_G', 'https://www.syndium.vn/Users/gmailcontact');
        define('EMAILLS_COUNT', 1000);

        //openid google
        define('CALLBACK_OG', 'http://syndium.vn/Users/registergmail');
        define('CLIENT_ID_OG', 'YOUR_CLIENT_ID_OG_HERE');
        define('CLIENT_SECRET_OG', 'YOUR_CLIENT_SECRET_OG_HERE');
        define('SERVER_SECRET_OG', 'YOUR_SERVER_SECRET_OG_HERE');
        define('DEVELOPERKEY_OG', 'YOUR_DEVELOPERKEY_OG_HERE');
        define('CLIENT_CALLBACK_OG', 'http://syndium.vn/Users/registergoogle');

        //yahooo
        define("DOMAIN_YAHOO", "syndium.vn"); //127.0.0.1 su dung cho localhost
        define("CONSUMER_KEY", 'YOUR_CONSUMER_KEY_HERE');
        define("CONSUMER_SECRET", 'YOUR_CONSUMER_SECRET_HERE');
        define("APPID_Y", 'YOUR_APPID_Y_HERE');

        //facebook
        define("ID_FB", 'YOUR_ID_FB_HERE');
        define("SECRET_FB", 'YOUR_SECRET_FB_HERE');

        //define Instagram
        define('CLIENT_ID_INS', 'YOUR_CLIENT_ID_INS_HERE');
        define('CLIENT_SECRET_INS', 'YOUR_CLIENT_SECRET_INS_HERE');
        define('CLIENT_CALLBACK_INS', 'http://syndium.vn/users/registerinstagram');

        define('ALGOLIA_APPID', 'YOUR_ALGOLIA_APPID_HERE');
        define('ALGOLIA_SEARCH_KEY', 'YOUR_ALGOLIA_SEARCH_KEY_HERE');
        define('ALGOLIA_UPDATE_KEY', 'YOUR_ALGOLIA_UPDATE_KEY_HERE');

        define('ELASTIC_SEARCH_HOST', 'localhost:9200');

        define('ALLOW_SEND_OUT_EMAIL', true);

        define('Show_Advance_Feature', false);
        define('Show_ADV', false);

        define("CHANGE_PROGRESS_NTF_EMAIL", true);
        define("CHANGE_PROGRESS_NTF_SMS", true);
        define("CHANGE_PROGRESS_NTF_IPN", true);

        /* ONEPAY */
        define("ONEPAY_URL", 'https://onepay.vn/paygate/vpcpay.op');
        define("ONEPAY_URL_VISA", 'https://onepay.vn/paygate/vpcpay.op');
        define('ONEPAY_URL_QUERYRD', 'https://onepay.vn/onecomm-pay/Vpcdps.op');
        //        define('ONEPAY_URL_QUERYRD_VISA', 'https://onepay.vn/msp/api/v1/vpc/invoices/queries');
        define('ONEPAY_URL_QUERYRD_VISA', 'https://onepay.vn/vpcpay/Vpcdps.op');

        define("ONEPAY_SECURITY_CODE", 'YOUR_ONEPAY_SECURITY_CODE_HERE');
        define("ONEPAY_SECURITY_CODE_VISA", 'YOUR_ONEPAY_SECURITY_CODE_VISA_HERE');
        define("ONEPAY_ACCESS_CODE", 'YOUR_ONEPAY_ACCESS_CODE_HERE');
        define("ONEPAY_MERCHANT", 'OP_XINHTUOI');
        define("ONEPAY_ACCESS_CODE_VISA", 'YOUR_ONEPAY_ACCESS_CODE_VISA_HERE');
        define("ONEPAY_MERCHANT_VISA", 'OP_XINHTUOI');
        define("ONEPAY_USER", 'YOUR_ONEPAY_USER_HERE');
        define("ONEPAY_PASSWORD", 'YOUR_ONEPAY_PASSWORD_HERE');

        // DEFINE VNPAY
        define("VNP_TMNCODE", 'YOUR_VNP_TMNCODE_HERE'); // XINHTUOI
        define("VNP_HASHSECRET", 'YOUR_VNP_HASHSECRET_HERE'); //NQWNWJRFLPNGQGLUOTYUVGLJRCYCGKHM
        define("VNP_URL", "https://pay.vnpay.vn/vpcpay.html");
        define("VNP_RETURNURL", "https://syndium.vn/checkout/vnpay_return");

        // Momo

        define("MOMO_ENDPOINT", "https://payment.momo.vn");
        define('MOMO_PARTNER_CODE', 'YOUR_MOMO_PARTNER_CODE_HERE');
        define('MOMO_ACCESS_KEY', 'YOUR_MOMO_ACCESS_KEY_HERE');
        define('MOMO_SECRECT_KEY', 'YOUR_MOMO_SECRECT_KEY_HERE');
        define('MOMO_RETURN_URL', URL_BASE . '/checkout/momo_complete');
        define('MOMO_NOTIFY_URL', URL_BASE . '/checkout/momo_notify');


        // Zalo
        define("ZALOPAY_ENDPOINT", "https://openapi.zalopay.vn");
        define("ZALOPAY_MAC_KEY", 'YOUR_ZALOPAY_MAC_KEY_HERE');
        define("ZALOPAY_CALLBACK_KEY", 'YOUR_ZALOPAY_CALLBACK_KEY_HERE');
        define("ZALOPAY_PUBLIC_KEY", 'YOUR_ZALOPAY_PUBLIC_KEY_HERE');



        // Configure::write('debug', 0 );
        break;

    case "TEST": // TEST

        define("URL_BASE", "http://dev.syndium.vn");
        define("URL_STATIC", URL_BASE);
        define("FILE_STATIC", URL_STATIC . '/file.php?');
        define("CHAT_SERVER", "tls://192.168.1.125:61614");
        define("NOTIFY_SERVER", 'http://123.29.75.76:8889');
        define("PUSH_SERVER", 'static.syndium.vn');
        define("DISABLE_CACHE", true);


        //define twitter
        //        define('YOUR_CONSUMER_KEY', 'YOUR_TWITTER_CONSUMER_KEY_HERE');
        //        define('YOUR_CONSUMER_SECRET', 'YOUR_TWITTER_CONSUMER_SECRET_HERE');
        //        define("REGIST_TWITTER" , URL_BASE . "/Users/registertwitter");

        define('YOUR_CONSUMER_KEY', 'YOUR_TWITTER_CONSUMER_KEY_HERE');
        define('YOUR_CONSUMER_SECRET', 'YOUR_TWITTER_CONSUMER_SECRET_HERE');
        define("REGIST_TWITTER", URL_BASE . "/Users/registertwitter");

        // info contact gmail
        //        define('CONSUMER_KEY_G', 'YOUR_CONSUMER_KEY_G_HERE');
        //        define('CONSUMER_SECRET_G', 'YOUR_CONSUMER_SECRET_G_HERE');
        //        define('CALLBACK_G', 'http://test-heygo.foyu.vn/Users/gmailcontact');
        //        define('EMAILLS_COUNT', 500);


        define('CONSUMER_KEY_G', 'YOUR_CONSUMER_KEY_G_HERE');
        define('CONSUMER_SECRET_G', 'YOUR_CONSUMER_SECRET_G_HERE');
        define('CALLBACK_G', 'http://test.syndium.vn/u/gmailcontact');
        define('EMAILLS_COUNT', 500);



        //openid google
        //        define('CALLBACK_OG', /*'http://localhost/users/registergmail' );//*/'http://test-heygo.foyu.vn/Users/registergmail');
        //        define('CLIENT_ID_OG', 'YOUR_CLIENT_ID_OG_HERE' );//*/'48392486453-jhqnbns6trt7kthrc9sh6oe9kmb4eoov.apps.googleusercontent.com' );
        //        define('CLIENT_SECRET_OG', 'YOUR_CLIENT_SECRET_OG_HERE');//*/'7Qla74_SVggV7W0Kk1OukoCL' );
        //        define('DEVELOPERKEY_OG', 'YOUR_DEVELOPERKEY_OG_HERE' );
        //        define('CLIENT_CALLBACK_OG', 'http://test-heygo.foyu.vn/Users/registergoogle');//'http://localhost/users/registergoogle' );


        define('CALLBACK_OG', 'http://syndium.vn/Users/registergoogle');
        define('CLIENT_ID_OG', 'YOUR_CLIENT_ID_OG_HERE');
        define('CLIENT_SECRET_OG', 'YOUR_CLIENT_SECRET_OG_HERE');
        define('SERVER_SECRET_OG', 'YOUR_SERVER_SECRET_OG_HERE');
        define('DEVELOPERKEY_OG', 'YOUR_DEVELOPERKEY_OG_HERE');
        define('CLIENT_CALLBACK_OG', 'http://syndium.vn/Users/registergoogle');


        //yahoo
        define("DOMAIN_YAHOO", "test.syndium.vn"); //127.0.0.1 su dung cho localhost
        define('CONSUMER_KEY', 'YOUR_CONSUMER_KEY_HERE');
        define('CONSUMER_SECRET', 'YOUR_CONSUMER_SECRET_HERE');
        define('APPID_Y', 'YOUR_APPID_Y_HERE');

        //facebook
        define("ID_FB", 'YOUR_ID_FB_HERE');
        define("SECRET_FB", 'YOUR_SECRET_FB_HERE');


        //define Instagram
        define('CLIENT_ID_INS', 'YOUR_CLIENT_ID_INS_HERE');
        define('CLIENT_SECRET_INS', 'YOUR_CLIENT_SECRET_INS_HERE');
        define('CLIENT_CALLBACK_INS', 'http://test.syndium.vn/users/registerinstagram');

        define('ALGOLIA_APPID', 'YOUR_ALGOLIA_APPID_HERE');
        define('ALGOLIA_SEARCH_KEY', 'YOUR_ALGOLIA_SEARCH_KEY_HERE');
        define('ALGOLIA_UPDATE_KEY', 'YOUR_ALGOLIA_UPDATE_KEY_HERE');

        define('Show_ADV', false);

        define('ALLOW_SEND_OUT_EMAIL', true);

        define("CHANGE_PROGRESS_NTF_EMAIL", true);
        define("CHANGE_PROGRESS_NTF_SMS", true);
        define("CHANGE_PROGRESS_NTF_IPN", true);

        // Configure::write('debug', 0 );
        break;

    default: // debug
        // define("URL_BASE", "http://loc.syndium.vn" );
        // $url = 'http://172.27.37.44:80';
        // $url = 'http://172.27.37.35:80';
        // $url = 'https://localhost:444';

        // define("URL_BASE", env('URL_BASE',  'https://uat.syndium.vn') );
        define("URL_BASE", env('URL_BASE',  'http://loc.syndium.vn'));
        // define("URL_BASE", env('URL_BASE',  'http://172.27.37.34'));

        define("URL_STATIC", env('URL_STATIC',  URL_BASE));
        // define("URL_STATIC", 'https://cdn.syndium.vn' );
        // define("URL_LUXURY", 'https://luxury.syndium.vn');
        // define("URL_STATIC", 'https://cdn.syndium.vn' );
        define("FILE_STATIC", URL_STATIC);
        // define("FILE_STATIC", 'https://cdn.syndium.vn' );
        define("CHAT_SERVER", "tls://192.168.1.125:61614");
        define("NOTIFY_SERVER", 'http://test.syndium.vn:8889');
        define("PUSH_SERVER", 'static.syndium.vn');
        define("DISABLE_CACHE", true);

        //define twitter
        define('YOUR_CONSUMER_KEY', 'YOUR_TWITTER_CONSUMER_KEY_HERE');
        define('YOUR_CONSUMER_SECRET', 'YOUR_TWITTER_CONSUMER_SECRET_HERE');
        define("REGIST_TWITTER", URL_BASE . "/Users/registertwitter");


        // info contact gmail
        define('CONSUMER_KEY_G', 'YOUR_CONSUMER_KEY_G_HERE');
        define('CONSUMER_SECRET_G', 'YOUR_CONSUMER_SECRET_G_HERE');
        define('CALLBACK_G', 'http://loc.syndium.vn/users/gmailcontact');

        define('EMAILLS_COUNT', 500);

        //openid google
        define('CALLBACK_OG', 'http://loc.syndium.vn/users/registergoogle');
        define('CLIENT_ID_OG', 'YOUR_CLIENT_ID_OG_HERE'); //*/'48392486453-jhqnbns6trt7kthrc9sh6oe9kmb4eoov.apps.googleusercontent.com' );
        define('CLIENT_SECRET_OG', 'YOUR_CLIENT_SECRET_OG_HERE'); //*/'7Qla74_SVggV7W0Kk1OukoCL' );
        define('DEVELOPERKEY_OG', 'YOUR_DEVELOPERKEY_OG_HERE');
        define('SERVER_SECRET_OG', 'YOUR_SERVER_SECRET_OG_HERE');
        define('CLIENT_CALLBACK_OG', 'http://loc.syndium.vn/users/registergoogle'); //'http://localhost/users/registergoogle' );

        //define Instagram
        define('CLIENT_ID_INS', 'YOUR_CLIENT_ID_INS_HERE');
        define('CLIENT_SECRET_INS', 'YOUR_CLIENT_SECRET_INS_HERE');
        define('CLIENT_CALLBACK_INS', 'http://loc.syndium.vn/users/registerinstagram');

        // Pinterest
        define('KEY_PIN', 'YOUR_KEY_PIN_HERE');

        //yahoo
        define("DOMAIN_YAHOO", "localhost"); //127.0.0.1 su dung cho localhost

        //facebook
        define("ID_FB", 'YOUR_ID_FB_HERE');
        define("SECRET_FB", 'YOUR_SECRET_FB_HERE');

        // define Instagram
        //define('CLIENT_ID_INS', 'YOUR_CLIENT_ID_INS_HERE');
        //define('CLIENT_SECRET_INS', 'YOUR_CLIENT_SECRET_INS_HERE');
        //define('CLIENT_CALLBACK_INS' , 'http://localhost/users/registerinstagram');

        // define('ALGOLIA_APPID', 'YOUR_ALGOLIA_APPID_HERE');
        // define('ALGOLIA_SEARCH_KEY' , 'bf087e9b8db09d8dcd8372a98106153c');
        // define('ALGOLIA_UPDATE_KEY' , '22c47f98c8b6d65844bb5b061ae7922b');

        //debug User
        define('ALGOLIA_APPID', 'YOUR_ALGOLIA_APPID_HERE');
        define('ALGOLIA_SEARCH_KEY', 'YOUR_ALGOLIA_SEARCH_KEY_HERE');
        define('ALGOLIA_UPDATE_KEY', 'YOUR_ALGOLIA_UPDATE_KEY_HERE');

        define('ELASTIC_SEARCH_HOST', '127.0.0.1:9200');

        define('Show_Advance_Feature', false);
        define('Show_ADV', false);

        define("CHANGE_PROGRESS_NTF_EMAIL", false);
        define("CHANGE_PROGRESS_NTF_SMS", false);
        define("CHANGE_PROGRESS_NTF_IPN", false);
        define('ALLOW_SEND_OUT_EMAIL', env('ALLOW_SEND_OUT_EMAIL', false));

        /* ONEPAY */
        define("ONEPAY_URL", 'https://mtf.onepay.vn/paygate/vpcpay.op');
        define("ONEPAY_URL_VISA", 'https://mtf.onepay.vn/paygate/vpcpay.op');
        define('ONEPAY_URL_QUERYRD', 'https://mtf.onepay.vn/onecomm-pay/Vpcdps.op');
        define('ONEPAY_URL_QUERYRD_VISA', 'https://mtf.onepay.vn/vpcpay/Vpcdps.op');

        define("ONEPAY_SECURITY_CODE", 'YOUR_ONEPAY_SECURITY_CODE_HERE');
        define("ONEPAY_SECURITY_CODE_VISA", 'YOUR_ONEPAY_SECURITY_CODE_VISA_HERE');
        define("ONEPAY_ACCESS_CODE", 'YOUR_ONEPAY_ACCESS_CODE_HERE');
        define("ONEPAY_MERCHANT", 'ONEPAY');
        define("ONEPAY_ACCESS_CODE_VISA", 'YOUR_ONEPAY_ACCESS_CODE_VISA_HERE');
        define("ONEPAY_MERCHANT_VISA", 'TESTONEPAY20');

        define("ONEPAY_USER", 'YOUR_ONEPAY_USER_HERE');
        define("ONEPAY_PASSWORD", 'YOUR_ONEPAY_PASSWORD_HERE');

        // DEFINE VNPAY
        define("VNP_TMNCODE", 'YOUR_VNP_TMNCODE_HERE');
        define("VNP_HASHSECRET", 'YOUR_VNP_HASHSECRET_HERE');
        define("VNP_URL", "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html");
        define("VNP_RETURNURL", "http://loc.syndium.vn/checkout/vnpay_return");


        // Momo
        define("MOMO_ENDPOINT", "https://test-payment.momo.vn");
        define('MOMO_PARTNER_CODE', 'YOUR_MOMO_PARTNER_CODE_HERE');
        define('MOMO_ACCESS_KEY', 'YOUR_MOMO_ACCESS_KEY_HERE');
        define('MOMO_SECRECT_KEY', 'YOUR_MOMO_SECRECT_KEY_HERE');
        define('MOMO_RETURN_URL', URL_BASE . '/checkout/momo_complete');
        define('MOMO_NOTIFY_URL', URL_BASE . '/checkout/momo_notify');

        // ZALO PAY
        define("ZALOPAY_ENDPOINT", "https://sb-openapi.zalopay.vn");
        define("ZALOPAY_MAC_KEY", 'YOUR_ZALOPAY_MAC_KEY_HERE');
        define("ZALOPAY_CALLBACK_KEY", 'YOUR_ZALOPAY_CALLBACK_KEY_HERE');
        define("ZALOPAY_PUBLIC_KEY", 'YOUR_ZALOPAY_PUBLIC_KEY_HERE');

        // 0: ko hien thi debug
        // 1: hien thi debug code
        // 2: hien thi debug + sql log
        // Configure::write('debug', 0);

}

// Elastic
define('ELASTIC_DATABASE', Configure::read('Datasources.elastic.database'));


Configure::write(
    'theme.dependences',
    [
        'V01' => ['V01'],
        'M01' => ['V01', 'M01']
    ]
);

// Configure::write('Security.salt', env('SECURITY_SALT', 'DYhGyourdayWwvniR2G0FgaC9mi'));

// pr(Configure::read('Security.salt'));
// exit;


define('RF123_KEY', 'YOUR_RF123_KEY_HERE');
define('RF123_SECRET_KEY', 'YOUR_RF123_SECRET_KEY_HERE');
define('GMAP_KEY_API', 'YOUR_GMAP_KEY_API_HERE');
define('RECAPTCHA_SECRET_KEY', 'YOUR_RECAPTCHA_SECRET_KEY_HERE');
define('RECAPTCHA_SITE_KEY', 'YOUR_RECAPTCHA_SITE_KEY_HERE');

define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');



// ZALO OA
define("CHANGE_PROGRESS_NTF_ZALO", true);
const ZALO_ORDER_SUCCESS = '237046'; // '220623'; // Thông báo đặt hàng thành công
const ZALO_ORDER_UPDATE = '236608'; // '220624'; // Thông báo khi khách hàng thay đổi nội dung đơn hàng
const ZALO_REMIND_PAYMENT = '236658'; // '220633'; // Thông báo nhắc thanh toán đơn hàng cho khách hàng
const ZALO_ORDER_IMAGE = '236611'; // '220635'; // Thông báo có hình thành phẩm của sản phẩm
const ZALO_SEND_OTP = '236602'; // '220431'; // Thông báo mã OTP
const ZALO_ORDER_PAYMENT = '236612'; // '220637'; // Thông báo thanh toán đơn hàng
const ZALO_ORDER_COMPLETED = '224439'; // Thông báo khi giao hàng thành công // TODO
const ZALO_ORDER_CANCELLED = '236605'; // Thông báo hủy đơn hàng
const ZALO_ORDER_HAS_CHANGED = '236604'; // Thông báo thay đổi nội dung đơn hàng
const ZALO_ORDER_IN_DELIVERY_BUYER = '267249'; // Thông báo đang đi giao cho người đặt
const ZALO_ORDER_IN_DELIVERY_RECEIVER = '267251 '; // Thông báo đang đi giao cho người nhận


define('VKT_ENABLED', true);


define('PREPARED_LAUNCHER', 0);
define('FB_JS_LINK', 1);

define('PUSH_PATH', '/pub');
// define('THEME', 'V03');

// user mời tham gia hệ thống
define('INVITE_USER', 'INVITE_USER');

define("PASSWORD_SECRET_KEY", 'YOUR_PASSWORD_SECRET_KEY_HERE');

define('DEFAULT_THEME', 'V01');
define('THEMES', 'V01');

define('NOTIFY_PATH', '/notify');
define('LOCAL_TEST', false); // neu true : khong cho signup ma chi cho invite
define('INVITE_ACCEPT', 'invite_accept');

define('MAIL_PREFIX', '[B&B]');

define("DEFAUL_STATUS", 4);
define("DELETE_STATUS", 9);
define('RECORD_PERPAGE', 10);
define('RECORD_PERPAGE_LIST', 10);
define('CHOOSE_EMPTY', '---');
define('MAX_MODULE_LENGTH', 125);
define('MAX_MODULE_DESCRIPTION_LENGTH', 225);
define('SUPPORT_EMAIL', 'cs@mail.syndium.vn');
define('EMAIL_DOMAIN', 'syndium.vn');
define('REPLY_EMAIL', 'cs@mail.syndium.vn');
define('NOREPLY_EMAIL', 'no-reply@mail.syndium.vn');
define('SYSTEM_URL', '<a href="https://syndium.vn" target="_blank">syndium.vn</a>');
define('ACTIONPATH', 'controllers');

define("TIME_CACHED_ACTION", "5 minutes");
define('DEFAULT_LANGUAGE', 'vie');

define('MEMCACHE_HOST', '192.168.1.250');
define('MEMCACHE_PORT', '11212');
define("IMAGE_DEFAULT", URL_STATIC . "/img/noimage.jpg");
define("PATH_IMAGE_DEFAULT", URL_STATIC . "/img/noimage.jpg");
define('IMAGE_PLACEHOLDER_LOADING', URL_STATIC . "/img/placeholder.png");

define("CACHE_TIME", 5 * 60); // Comment dong nay de cho cache
define("ENCRYPTER_KEY", 'YOUR_ENCRYPTER_KEY_HERE');
/* CHARACTER  LINK */
define('CHARS_LIST', '0-9,A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z');
/* END */

define('TMP_IMAGE', TMP . 'image/');

// DEFINE FOLdER UPLOAD PUBLIC
define('UPLOAD_WEBROOT_FOLDER', ROOT . DS . 'webroot');

define('UPLOAD_FOLDER', UPLOAD_WEBROOT_FOLDER . DS . 'upload' . DS);
define('PUBLIC_IMAGE', UPLOAD_FOLDER . 'images' . DS);
define('PUBLIC_MUSIC', UPLOAD_FOLDER . 'musics' . DS);
define('PUBLIC_RECORDERS', UPLOAD_FOLDER . 'recorders' . DS);
define('PUBLIC_IMAGE_THUMB', UPLOAD_FOLDER . 'thumbs' . DS);
define('PUBLIC_IMAGE_THUMB_WEB', PUBLIC_IMAGE_THUMB . "web" . DS);
//define('PUBLIC_IMAGE_THUMB_WAP', PUBLIC_IMAGE_THUMB . "wap" . DS);
//define('PUBLIC_IMAGE_THUMB_MOBI', PUBLIC_IMAGE_THUMB . "mobi" . DS);
define('PUBLIC_THUMB_SHOW', "/upload/thumbs/");
define('WEBROOT_FOLDER', UPLOAD_WEBROOT_FOLDER . DS);

define('PUBLIC_SHOW_IMAGE_THUMB_WEB', PUBLIC_THUMB_SHOW . "web/");
define('PUBLIC_SHOW_IMAGE_THUMB_WAP', PUBLIC_THUMB_SHOW . "wap/");
define('PUBLIC_SHOW_IMAGE_THUMB_MOBI', PUBLIC_THUMB_SHOW . "mobi/");
define('PUBLIC_SHOW_IMAGE_AVATAR', URL_STATIC . PUBLIC_THUMB_SHOW . "avatar/");

// URL
define('URL_MINI_LOGO_IMAGE', URL_STATIC . "/img/mini_logo.png");
define('URL_USER_INFO', URL_BASE . "/users/info/%s");
define('URL_USER_BLOCK', URL_BASE . "/users/block/%s");
define('URL_SENT_MAIL_SETTING', URL_BASE . "/users/alert_setting");
define('URL_SUPPORT', URL_BASE . "/helps");
define('URL_STATIC_IMAGE', URL_STATIC . "/upload/images/");

define('LIMIT_UPLOAD_SIZE', '10485760');

define('NO_IMAGE_STATIC', URL_STATIC . "/img/avatar.png");

// BASE NAME
define('BASE_NAME', "Syndium");

// Thumbnail
define('WEB_WIDHT', 600);
define('WEB_HEIGHT', 600);

// Auto resize image
// 0 = auto
// feed
define('FEED_PAGE_WIDTH', 780);
define('FEED_IMAGE_WIDTH', 750);
define('FEED_IMAGE_HEIGHT', 0);
// small thumb quiz answer...
define('SMALL_THUMB_WIDTH', 198); // 3 answers
define('SMALL_THUMB_HEIGHT', 0);
define('SMALL_THUMB_MAX_WIDTH', 220); // css .img {width: 220px}
define('SMALL_THUMB_CSS_PADDING_1_COLS', (10 + 10) + (10 + 10));
define('SMALL_THUMB_CSS_PADDING_2_COLS', ((15 + 15) + (10 + 10 + 1) + (10 + 10)) + 1);
define('SMALL_THUMB_CSS_PADDING_3_COLS', ((10 + 10) + (10 + 10 + 1) + (10 + 10)) + 1);

define('FILE_PATH', TMP . 'files' . DS);

// home list
define('LIST_IMAGE_WIDTH', 350);
define('LIST_IMAGE_HEIGHT', 0);
define('LIST_SMALL_IMAGE_WIDTH', 220);
define('LIST_SMALL_IMAGE_HEIGHT', 0);
// cate list, my feed
define('LIST_CATE_IMAGE_WIDTH', 262);
define('LIST_CATE_IMAGE_HEIGHT', 0);
// sidebar
define('SIDEBAR_THUMB_WIDTH', 360);
define('SIDEBAR_THUMB_HEIGHT', 0);
// top popular
define('TOP_POPULAR_THUMB_WIDTH', 115);
define('TOP_POPULAR_THUMB_HEIGHT', 0);
// broadcast
define('TOP_VIDEO_THUMB_WIDTH', 150);
define('TOP_VIDEO_THUMB_HEIGHT', 0);


//define('WAP_WIDHT', 400);
//define('WAP_HEIGHT', 400);

//define('MOBI_WIDHT', 400);
//define('MOBI_HEIGHT', 400);

define('FULL_WIDTH', 1920);
define('FULL_HEIGHT', 1500);

//define('MEDIUM_WIDTH', 580);
//define('MEDIUM_HEIGHT', 480);

define('ALBUM_DISP_HEIGHT', '150px');
define('THUMB_DISP_HEIGHT', '75px');

define('ENTRY_IMAGE_MAX_WIDTH', 500);
define('MAIN_AVATAR_W', 110);

/** YAHOO API KEY * */
// Define constants to store your Consumer Key and Consumer Secret. twiter

define("TWITTER_CONSUMER_KEY", "XsK9nRK4bCB8DNQVosObjQ");


define("OAUTH_APP_ID", "EdkNGD6q");

// Invalid page
define('INVALID_PAGE', "/pages/invalid");

// Security code
define('SECURITY_CODE', 'oiw3urnwori2308*&^@*&YH *@#&( *N (*))');

define('EmotionPhoto', 'emotion_photos');
define('ProductPhoto', 'product_photos');
define('ProductThumb', 'product_thumbs');
define('Photo', 'photos');
define('SoundNotice', 'SoundNotice');
define('File', 'mfiles');
define('Image', 'images');
define('Video', 'videos');
define('Recorder', 'records');
define('Thumb', 'thumbs');
define('Large', 'larges');
//define('Medium', 'medium');
define('Avatar', 'avatar');
define('VIDEO_MAX_LENGTH', 300);

define('SPLIT_URL', '.html');
define('USER_BLOCK', 5);

/**
 * Basic defines for timing functions.
 */
define('SECOND', 1);
define('MINUTE', 60);
define('HOUR', 3600);
define('DAY', 86400);
define('WEEK', 604800);
define('MONTH', 2592000);
define('YEAR', 31536000);


function prx($v, int $die = 1)
{

    pr('--');
    pr($v);
    pr(Debugger::trace());
    if ($die) {

        exit;
    }
}



/**
 * Get id from params
 *
 * @param type  meta
 * @return type  meta
 * @access public
 */
function _getID($mixed)
{

    if ($mixed instanceof ObjectId) {
        return $mixed;
    }

    //  if (ctype_digit($mixed)) {
    //      pr( 1 . ' => ' . $mixed);
    //         return $mixed;
    //  }
    $mixed  = (string)$mixed;
    $_mixed = $mixed;
    if (!empty($mixed)) {

        if (is_array($mixed)) {
            return $mixed;
        }

        if (is_string($mixed) && strlen($mixed) !== 24) {
            return $mixed;
        }
        try {
            $_mixed = new MongoDB\BSON\ObjectId($mixed);
        } catch (Exception $e) {
            $_mixed = $mixed;
        }
    } else {
        $_mixed = new MongoDB\BSON\ObjectId();
    }

    return $_mixed;
}
// tuanna: End

if (!function_exists('array_replace_recursive')) {

    function array_replace_recursive($array, $array1)
    {
        if (!function_exists('recurse')) {

            function recurse($array, $array1)
            {
                foreach ($array1 as $key => $value) {
                    // create new key in $array, if it is empty or not an array
                    if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
                        $array[$key] = array();
                    }

                    // overwrite the value in the base array
                    if (is_array($value)) {
                        $value = recurse($array[$key], $value);
                    }
                    $array[$key] = $value;
                }
                return $array;
            }
        }

        // handle the arguments, merge one by one
        $args = func_get_args();

        $array = $args[0];
        if (!is_array($array)) {
            return $array;
        }



        for ($i = 1; $i < count($args); $i++) {
            if (is_array($args[$i])) {
                $array = recurse($array, $args[$i]);
            }
        }
        return $array;
    }
}

if (!function_exists('mb_trim')) {
    /**
     * Trims whitespace (or other characters) from the beginning and end of a string.
     *
     * @param string $string The input string.
     * @param string $charlist Optional characters to trim.
     * @return string The trimmed string.
     */
    function mb_trim($string, $charlist = " \t\n\r\0\x0B")
    {
        return preg_replace('/^[' . preg_quote($charlist, '/') . ']+|[' . preg_quote($charlist, '/') . ']+$/u', '', $string);
    }
}

/**
 * Create encrypt code for view image or static file in mongo
 *
 * @param string - id need view
 * @access public
 */
function ShowStaticContent($path)
{

    // return URL_STATIC . "/theme/" . THEME . $path;

    $main_path = WWW_ROOT;

    $theme = Configure::read('THEME');

    // $domain = Configure::read('DOMAIN');
    // $domain = '';

    if (!$theme) $theme = DEFAULT_THEME;


    $dependences = Configure::read('theme.dependences');
    if (!empty($dependences[$theme]))
        $themes = $dependences[$theme];
    else
        $themes = array();

    array_unshift($themes,  $theme);

    // pr($themes);

    $dev = (bool)(SERVER_RUN_MODE === 'DEV');

    // DEBUG
    if ($dev) {
        foreach ($themes as $theme) {
            $file = "/theme/" . $theme . $path;
            $ret = $main_path . $file;

            // var_dump($ret);

            if (file_exists($ret)) {
                return URL_STATIC . $file;
            }

            $file = "/theme/" . $theme . $path;
            $ret = $main_path  . $file;

            if (file_exists($ret)) {
                return URL_STATIC . $file;
            }
        }

        // return URL_STATIC . "/theme/" . THEME . $path;
    } else {
        foreach ($themes as $theme) {

            $file = "/theme/" . $theme . '-build' . DS  . $path;
            $ret = $main_path . $file;

            if (file_exists($ret)) {
                return URL_STATIC . $file;
            }

            $file = "/theme/" . $theme . DS . $path;
            $ret = $main_path . $file;


            if (file_exists($ret)) {
                return URL_STATIC . $file;
            }

            $file = "/theme/" . $theme . '-build' . $path;
            $ret = $main_path . $file;
            if (file_exists($ret))
                return URL_STATIC . $file;
        }

        // return URL_STATIC . $file;
    }

    return URL_STATIC . $path;
}


/**
 * Create encrypt code for view image or static file in mongo
 *
 * @param string - id need view
 * @access public
 */
function CreateEncrypterCode($id)
{
    return md5($id . SECURITY_CODE);
}

/**
 * Create encrypt code for view image or static file in mongo
 *
 * @param string - id need view
 * @access public
 */
function EncrypterForView($id, $model, $download = false)
{

    $dev = (bool)(SERVER_RUN_MODE === 'DEV');

    if ($dev) {
        if ($model == 'big') $model = 'photo';

        $url = $model . '=' . $id . '&e=' . CreateEncrypterCode($id);

        // for download
        if ($download) {
            $url .= "&m=download";
        }
    } else {
        if (is_string($id))  $id = str_pad($id, 24, '0');

        // for download
        $url = "photos/$model/$id.jpg";

        // for download
        if ($download) {
            // $url .= "&m=download";
            $url = "photos/download/$id.jpg";
        }
    }


    return $url;
}

/**
 * Convert from br to new
 *
 * @param type  meta
 * @return type  meta
 * @access public
 */
function br2nl($text)
{
    // return str_replace("<br />", "", $text );
    return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $text);
}


/**
 * Convert mongodate to string
 *
 * @param type  meta
 * @return type  meta
 * @access public
 */
function convertDate($date = null, &$obj_set = null)
{
    $str = '';

    // get timezone
    $opt = array('end' => '+1 week');



    if (is_object($date)) {
        $time = new DateTime($date->sec);
        $str = $time->timeAgoInWords($opt);

        if (!empty($obj_set)) {
            $obj_set['created_obj'] = $date;
            $obj_set['created_obj']->sec = $date->sec;
        }
    } else {
        if (is_object($date)) {
            $date = $date->sec;
        }

        $time = new DateTime($date);

        $str = $time->timeAgoInWords($opt);
    }

    return $str;
}

if (!class_exists("MongoDate")) {
    class MongoDate
    {
        var $v;
        function __construct($date = null)
        {
            // parent::__construct($date);
            if (!empty($date)) {

                if ($date instanceof MongoDate) {
                    $this->v = $date->v;
                } else {
                    if ($date < time() * 100) {
                        $date *= 1000;
                    }

                    $this->v = new MongoDB\BSON\UTCDateTime((int)$date);
                }
            } else {
                $this->v = new MongoDB\BSON\UTCDateTime();
            }
        }

        public function __get($name = 'sec')
        {
            $name = ucfirst($name);

            $func = "get$name";
            return $this->$func();
        }

        private function getSec()
        {
            return $this->v->toDateTime()->getTimestamp();
        }

        private function getValue()
        {
            return $this->v;
        }
    }
}

/**
 * Convert mongodate to string
 *
 * @param type  meta
 * @return type  meta
 * @access public
 */
function formatDate(string $format, mixed $date = null)
{

    $format = __($format);

    $str = '';



    if (is_object($date)) {

        $time = new DateTime($date->sec);
        $str = $time->format($format);
    } else {
        $time = new DateTime($date);
        $str = $time->format($format);
    }

    return $str;
}


function _SEOTV($str)
{
    $str = (string)$str;
    if (strlen($str) == 0)
        return $str;
    // $str = strtolower(trim($str));
    $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", 'a', $str);
    $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", 'e', $str);
    $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", 'i', $str);
    $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", 'o', $str);
    $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", 'u', $str);
    $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", 'y', $str);
    $str = preg_replace("/(đ)/", 'd', $str);
    $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", 'A', $str);
    $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", 'E', $str);
    $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", 'I', $str);
    $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", 'O', $str);
    $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", 'U', $str);
    $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", 'Y', $str);
    $str = preg_replace("/(Đ)/", 'D', $str);

    return $str;
}

/**
 * remove uft8 in string
 * @param string $str
 * @param string $split
 * @access Public
 * @author Ngo Anh Tuan <tuanngo.technical@gmail.com>
 * @return string
 */
function _SEO($str, $split = "-", $textonly = false)
{


    if (strlen($str) == 0)
        return $str;
    // $str = strtolower($str);
    $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", 'a', $str);
    $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", 'e', $str);
    $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", 'i', $str);
    $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", 'o', $str);
    $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", 'u', $str);
    $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", 'y', $str);
    $str = preg_replace("/(đ)/", 'd', $str);
    $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", 'A', $str);
    $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", 'E', $str);
    $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", 'I', $str);
    $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", 'O', $str);
    $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", 'U', $str);
    $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", 'Y', $str);
    $str = preg_replace("/(Đ)/", 'D', $str);

    if (!$textonly) {
        $str = preg_replace("/( )/", $split, $str);
        $str = preg_replace("/[^0-9a-zA-Z_\-\+\.\,]/", '', $str);
    }


    return $str;
}

function numberFormatHuman($num, $symbol = '', $decimals = 0)
{

    if (!empty($symbol)) {
        $num = $num / 1000;
    }

    if (empty($num)) {
        return "0" . $symbol;
    }
    $ret =  number_format($num, $decimals, ',', '.');
    if (!empty($symbol)) {
        return $ret . 'K';
    }

    return $ret . $symbol;
}

function formatNumberCrypto($number, $symbol = 'XRP', $num = 8)
{
    // Kiểm tra nếu số có phần thập phân
    if (floor($number) != $number) {
        // Nếu có phần thập phân, hiển thị tối đa 8 chữ số thập phân
        return rtrim(rtrim(number_format($number, $num, '.', ','), '0'), '.') . " " . $symbol;
    } else {
        // Nếu không có phần thập phân, chỉ hiển thị số nguyên
        return number_format($number, 0, '.', ',') . " " . $symbol;
    }
}


function numberFormat($num, $symbol = '', $decimals = 0, $dec_point = ',', $thousands_sep = '.', $space = ' ')
{

    if (empty($num)) {
        return "0" . $space . $symbol;
    }
    $ret =  number_format((float)$num, (int)$decimals, $dec_point, $thousands_sep);

    return  $ret . $space . $symbol;
}

/**
 * Convert DateTime String to server time
 */
function xtostrtotime($str, $datetime = null, $userOffset = null): int
{

    if (empty($datetime)) {
        $datetime = time();
    }

    $time = strtotime((string)$str, $datetime);

    if (empty($userOffset)) {
        $userOffset = @$_SESSION['timezone'];
    }
    if (empty($userOffset)) {
        $userOffset = 7;
    }

    $time = $time - ((int)$userOffset * 60 * 60);

    return $time;
}

/**
 * Convert DateTime String to user time
 */
function xto2usertime($time, $userOffset = null)
{
    if (empty($userOffset)) {
        $userOffset = @$_SESSION['timezone'];
    }
    $time = (int)$time + ($userOffset * 60 * 60);

    return $time;
}

/**
 * Chuyển ngữ on runtime từ db
 */
function __t($text, $lang = null)
{
    $curLang = Configure::read("Config.language");

    if (empty($lang)) {
        $lang = Configure::read("Config.language");

        $curLang = 'vie';
    }

    if ($curLang == $lang) {
        return $text;
    }

    $Translate = MyApp::uses("Translate", "Model");
    return $Translate->translateText($text, $lang);
}

/**
 * Hiển thị ngày giờ theo format từ giờ server
 */
function showDateFormat($format, $time, $timezone = null)
{

    if (empty($timezone)) {
        if (!empty($_SESSION["timezone"])) {
            $timezone = $_SESSION["timezone"];
        }
    }

    if (empty($timezone)) {
        $timezone = 7; // mặc định +7:00
    }

    $timezone = (int)$timezone;

    if ($time instanceof \MongoDB\BSON\UTCDateTime) {
        $newTime = $time->toDateTime()->getTimestamp();
    } elseif ($time instanceof \MongoDate) {
        $newTime = (int)$time->sec + $timezone * HOUR;
    } else {
        $newTime = (int)$time + $timezone * HOUR;
    }

    return date($format, $newTime);
}

function numberFormatDecimals($num, $symbol = '', $decimals = 2)
{
    return numberFormat($num, $symbol, $decimals);
}


function setImage($file_id, $collection = Photo, $size = Thumb, $wm_shop_id = null, $no_logo = false, $cate = '')
{

    if (is_array($file_id)) {
        return "";
    }

    $file_id = _getID($file_id);

    if ($no_logo) {
        $fileName = @(string)$file_id . $cate .  '.cl.syndium.webp';
    } else {
        $fileName = @(string)$file_id . $cate . '.syndium.webp';
    }

    $arr = [FILE_STATIC, 'photos/view', $collection, $size, trim($wm_shop_id ?? ""), trim($fileName ?? "")];
    $arr = array_filter($arr);

    return join('/', $arr);
    // return URL_STATIC . "/photos/view/" . $collection . '/' . $size . "/" . (string) $file_id . '.jpg';
}

function checkSetImage($created, $file_id, $collection = Photo, $size = Thumb, $wm_shop_id = null, $no_logo = false, $cate = '')
{
    return setImage($file_id, $collection, $size, $wm_shop_id, $no_logo, $cate);
}

/**
 * Hiển thị ở HTML
 */
function ShowImageSrcSizeSpace($w, $h)
{
    return 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 ' . $w . ' ' . $h . '\'%3E%3C/svg%3E';
}


if (!function_exists('am')) {

    /**
     * Merge a group of arrays
     *
     * Accepts variable arguments. Each argument will be converted into an array and then merged.
     *
     * @return array All array parameters merged into one
     * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#am
     */
    function am()
    {
        $r = array();
        $args = func_get_args();
        foreach ($args as $a) {
            if (!is_array($a)) {
                $a = array($a);
            }
            $r = array_merge($r, $a);
        }
        return $r;
    }
}
