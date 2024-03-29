<?php
/*
  Plugin name: OptimizeCustomize
  Plugin URI:   https://github.com/Webanet-Australia/optimize-customize
  Description:  Custom membership forms for OptimizeMember
  Version:      1.0.0
  Author:       sniper@openmail.cc
  Author URI:   https://github.com/orgs/Webanet-Australia/
  Text Domain:  optimize-customize
  Domain Path:  /lang/
  License:      GPL
*/
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;

//require dependencies
require_once(__DIR__ . '/vendor/autoload.php');

//ensure page is included
defined( 'ABSPATH' ) or die;

if(!class_exists('OptimizeCustomize')) {

    class OptimizeCustomize
    {
        //plugin directory name
        const PLUGIN_DIR = '/optimizeCustomize';

        //plugin version
        const VERSION = '1.0.0';

        //plugin text domain
        const TEXT_DOMAIN = 'optimize-customize';

        //enqueue admin scripts
        public function init()
        {
            add_action( 'admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

            //menu in admin
            add_action('admin_menu', [$this, 'adminMenu']);

            //shortcodes
            add_shortcode('optimize_customize', [$this, 'shortcodes']);

            //admin init settings
            add_action('admin_init', [$this, 'adminInit']);

            //Frontend form submit
            add_action('rest_api_init', function () {

              register_rest_route( '/' . self::TEXT_DOMAIN, '/signup', [
                'methods' => 'POST',
                'callback' => [$this, 'signup']
              ]);

            });

            add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        }

        //show admin page, include backend page
        function adminPage()
        {
            include( 'inc/backend/page.php' );
        }

        //Add Admin Menu Item
        function adminMenu()
        {
            add_menu_page(
              __('OPMCustomize', self::TEXT_DOMAIN ),
              __( 'OPMCustomize', self::TEXT_DOMAIN),
              'manage_options',
              self::TEXT_DOMAIN,
              [$this, 'adminPage'],
              plugins_url() . self::PLUGIN_DIR . '/assets/img/icon.png'
            );
        }

        //Signup form submit
        public function signup()
        {
            $payment = self::payment($_POST);

            if($payment['result'] !== true) {
              return $payment;
            }

            return array_merge([
              'payment' => $payment,
              'addUser' => self::addUser($_POST)
            ], $_POST);
        }

        //Complete payment
        public static function payment($post)
        {
            $rv = [];

            Stripe::setApiKey(get_option('optimize_customize_setting_payment_key'));
            //Stripe::setVerifySslCerts(false);

            try
            {
              $customer = Customer::create([
                'email' => $post['stripeEmail'],
                'source'  => $post['stripeToken'],
              ]);

              $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [['plan' => $post['plan']]],
              ]);

              if ($subscription->status != 'incomplete') {

                $rv['result'] = true;

              } else {

                $rv['result'] = 'Payment failed';

              }

            } catch(Exception $e) {

              $rv['result'] = 'Error: ' . $e->getMessage();
            }

            return $rv;
        }

        //add user to optimizePress
        public static function addUser($post)
        {
            $rv = [];
            $op = [];

            $op["op"] = "create_user";

            $op["api_key"] = get_option('optimize_customize_setting_key');

            $usr = $post['optimizemember_pro_stripe_checkout'];

            $op['data'] = [
                'user_login' => $usr['username'],
                'user_email' => $usr['email'],
                'modify_if_login_exists' => '1',
                'first_name' => $usr['first_name'],
                'last_name' => $usr['last_name'],
                'optimizemember_level' => $post['level'],
                'optimizemember_subscr_gateway' => 'stripe',
                'optimizemember_subscr_id' => $post['plan'],
                'opt_in' => '1',
                'notification' => '1',
            ];

            $post_data = stream_context_create ([
               'http' => [
                 'method' => 'POST',
                 'header' => 'Content-type: application/x-www-form-urlencoded',
                 'content' => 'optimizemember_pro_remote_op=' . urlencode (serialize ($op))
               ]
             ]);

            $result = trim (file_get_contents (site_url() . '/?optimizemember_pro_remote_op=1', false, $post_data));

            return $result;
        }

        //D shortcode
        function shortcodes($atts)
        {
          //define shortcode atts
          $atts = shortcode_atts(['level' => '1', 'currency' => '', 'plan' => '0'], $atts, 'optimize_customize');

          //start page output
          ob_start();

          //include frontend page
          include( __DIR__ . '/inc/frontend/form.php');

          //get page output
          $html = ob_get_contents();

          //cleanup
          ob_get_clean();

          //return page output
          return $html;
        }

        //Admin Initialize settings fields
        function adminInit()
        {
            // add section
           	add_settings_section(
          		'optimize_customize_section',
          		'',
          		[$this, 'adminSettingsSection'],
          		self::TEXT_DOMAIN
          	);

           	//api key - Add setting field
           	add_settings_field(
          		'optimize_customize_setting_key',
          		'OpimizePress API Key',
          		[$this, 'adminSettingsKey'],
          		self::TEXT_DOMAIN,
          		'optimize_customize_section'
          	);

           	//api key - register
            register_setting(self::TEXT_DOMAIN, 'optimize_customize_setting_key');

            //payments - Add setting field
            add_settings_field(
              'optimize_customize_setting_payments',
              'Payment Provider',
              [$this, 'adminSettingsPayments'],
              self::TEXT_DOMAIN,
              'optimize_customize_section'
            );

            //payments - register
            register_setting(self::TEXT_DOMAIN, 'optimize_customize_setting_payments');

            //payment gateway api key - Add setting field
            add_settings_field(
              'optimize_customize_setting_payment_key',
              'Payment Gateway API Key',
              [$this, 'adminSettingsPaymentKey'],
              self::TEXT_DOMAIN,
              'optimize_customize_section'
            );

            //payment gateway api key
            register_setting(self::TEXT_DOMAIN, 'optimize_customize_setting_payment_key');

            //form code - add setting field
            add_settings_field(
              'optimize_customize_setting_code',
              'Signup Form',
              [$this, 'adminSettingsCode'],
              self::TEXT_DOMAIN,
              'optimize_customize_section'
            );
            //form code - register
            register_setting(self::TEXT_DOMAIN, 'optimize_customize_setting_code');

            //css field - for frontend
            add_settings_field(
              'optimize_customize_setting_css',

              'CSS',
              [$this, 'adminSettingsCss'],
              self::TEXT_DOMAIN,
              'optimize_customize_section'
            );

            //form code - register
            register_setting(self::TEXT_DOMAIN, 'optimize_customize_setting_css');
        }

        //admin settings options section heading
        function adminSettingsSection()
        {
            print '<h1 class="title">
              <img src="' . plugins_url() . OptimizeCustomize::PLUGIN_DIR . '/assets/img/icon.png" title="logo" class="logo">
              Optimize Customize
            </h1>
            <h2>OptimizePress custom membership forms</h2>
            <hr/>';
          }

        //admin settings key field
        function adminSettingsKey()
        {
            print '
            <input type="text" name="optimize_customize_setting_key"
              id="optimize_customize_api_key"
              value="' . get_option('optimize_customize_setting_key') . '"><br/>
            <a href="' . admin_url() . '/admin.php?page=ws-plugin--optimizemember-scripting"
              target="_blank">Get OptimizePress Memeber Remote API Key.</a><br/>
            <i>Scroll down to the "Pro API For Remote Operations" section and retrieve the value from the <br/>
            "Remote Operations API: Your secret API Key" textbox, paste that value in the textbox above.</i>';
        }

        //admin settings payment gateway key field
        function adminSettingsPaymentKey()
        {
            print '
            <input type="text" name="optimize_customize_setting_payment_key"
              id="optimize_customize_payment_key"
              value="' . get_option('optimize_customize_setting_payment_key') . '">';
        }

        //admin settings signup form field
        public function adminSettingsCode()
        {
            print '
            <textarea name="optimize_customize_setting_code" id="' .
              self::TEXT_DOMAIN . '-code-editor">' .
              self::getSignupForm() .
            '</textarea>';
        }

        //get signup form code value
        public static function getSignupForm()
        {
            return get_option('optimize_customize_setting_code');
        }

        //admin settings signup form field
        public function adminSettingsCss()
        {
            print '
            <textarea name="optimize_customize_setting_css" id="' .
              self::TEXT_DOMAIN . '-css-editor">' .
              self::getCss() .
            '</textarea>';
        }

        //css -get setting option value, save out css to file
        public static function getCss()
        {
            $css = get_option('optimize_customize_setting_css');

            file_put_contents(__DIR__  . self::getFrontendScriptPath(), $css);

            return $css;
        }

        //Admin settings payment provider select options
        function adminSettingsPayments()
        {
            print '
            <select name="optimize_customize_setting_payments">
                <option value="1">Stripe</option>
            </select>';
        }

        //Get admin setting payment provider (always stripe for now)
        public static function getPaymentProvider()
        {
            return 'stripe';
        }

        //enqueue scripts for admin page
        function adminEnqueueScripts($hook)
        {
          $url = plugins_url() . self::PLUGIN_DIR;

          //codemirror css
          wp_enqueue_style(
            self::TEXT_DOMAIN . '-codem-style',
            $url . '/assets/vendor/codemirror/css/codemirror.css',
            [],
            self::VERSION
          );

          //codemirror js
          wp_enqueue_script(
            self::TEXT_DOMAIN . '-cm',
            $url . '/assets/vendor/codemirror/js/codemirror.js',
            ['jquery'],
            self::VERSION,
            true
          );

          //codemirror xml mode
          wp_enqueue_script(
            self::TEXT_DOMAIN . '-cm-xml',
            $url . '/assets/vendor/codemirror/js/xml.js',
            ['jquery'],
            self::VERSION,
            true
          );

          //codemirror javascript mode
          wp_enqueue_script(
            self::TEXT_DOMAIN . '-cm-javascript',
            $url . '/assets/vendor/codemirror/js/javascript.js',
            ['jquery'],
            self::VERSION,
            true
          );

          //codemirror css mode
          wp_enqueue_script(
            self::TEXT_DOMAIN . '-cm-css',
            $url . '/assets/vendor/codemirror/js/css.js',
            ['jquery'],
            self::VERSION,
            true
          );

          //codemirror html mixed mode
          wp_enqueue_script(
            self::TEXT_DOMAIN . '-cm-htmlmixed',
            $url . '/assets/vendor/codemirror/js/htmlmixed.js',
            ['jquery'],
            self::VERSION,
            true
          );
        }

        //enqueue frontend custom css
        function enqueueScripts()
        {
            $pd  = plugins_url() . self::PLUGIN_DIR;
            wp_enqueue_style(
              self::TEXT_DOMAIN . '-custom-style',
              $pd . self::getFrontendScriptPath(),
              [],
              self::VERSION
            );
        }

        function getFrontendScriptPath()
        {
            return '/assets/css/' . self::TEXT_DOMAIN . '-custom.css';
        }
    }

    //create plugin
    $opmCustomize = new OptimizeCustomize();
    $opmCustomize->init();
}
?>
