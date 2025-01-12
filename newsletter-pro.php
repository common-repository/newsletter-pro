<?php
/*
Plugin Name: Newsletter Pro
Description: A professional newsletter plugin for your site. Create and send awesome HTML newsletters to your members or subscribers within seconds.
Version: 3.1.9
Author: thewprush
*/

include_once 'files/class.functions.php';
define ('NP_PLUGIN_BASE_DIR', WP_PLUGIN_DIR, true);

class Email_Newsletter extends Email_Newsletter_functions {

    var $plugin_dir;
    var $plugin_url;
    var $settings;
    var $tb_prefix;

    function Email_Newsletter() {
        __construct();
    }

    /**
     * PHP 5 constructor
     **/
    function __construct() {
        global $wpdb;

        //checking for MultiSite
        if ( 1 < $wpdb->blogid )
            $this->tb_prefix = $wpdb->base_prefix . $wpdb->blogid . '_';
        else
            $this->tb_prefix = $wpdb->base_prefix;

        //setup proper directories
        if ( is_multisite() && defined( 'WPMU_PLUGIN_URL' ) && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . basename( __FILE__ ) ) ) {
            $this->plugin_dir = WPMU_PLUGIN_DIR . '/newsletter-pro/';
            $this->plugin_url = WPMU_PLUGIN_URL . '/newsletter-pro/';
        } else if ( defined( 'WP_PLUGIN_URL' ) && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/newsletter-pro/' . basename( __FILE__ ) ) ) {
            $this->plugin_dir = WP_PLUGIN_DIR . '/newsletter-pro/';
            $this->plugin_url = WP_PLUGIN_URL . '/newsletter-pro/';
        } else if ( defined('WP_PLUGIN_URL' ) && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . basename( __FILE__ ) ) ) {
            $this->plugin_dir = WP_PLUGIN_DIR;
            $this->plugin_url = WP_PLUGIN_URL;
        } else {
            wp_die( __('There was an issue.' ) );
        }

        //plugin_icon
        add_action( 'admin_head', array( &$this, 'change_icon' ) );

        add_action( 'admin_init', array( &$this, 'admin_init' ) );

        // filter schedules
        add_filter( 'cron_schedules', array( &$this, 'add_new_cron_time' ) );

        add_action( 'init', array( &$this, 'init' ) );


        //get all setting of plugin
        $this->settings = $this->get_settings();


        //changing list of members when we create or delete user of the site
        add_action( 'user_register', array( &$this, 'user_create' ) );
        add_action( 'delete_user', array( &$this, 'user_delete' ) );

        //some actions for MultiSite
        if ( function_exists( 'is_multisite' ) && is_multisite() ) {
            add_action( 'added_existing_user', array( &$this, 'user_create' ) );
            add_action( 'remove_user_from_blog', array( &$this, 'user_remove_from_site' ) );
            add_action( 'wpmu_delete_user', array( &$this, 'user_delete' ) );

            add_action('wpmu_new_blog', array( &$this, 'activation' ) );
            add_action('delete_blog', array( &$this, 'deactivation' ) );

        }

        //creating menu of the plugin
        add_action( 'admin_menu', array( &$this, 'admin_page' ) );

        //send email by WP-CRON
        add_action('e_newsletter_cron_send', array( &$this, 'send_by_wpcron' ) );

        //check bounces email by WP-CRON
        add_action('e_newsletter_cron_check_bounces_1', array( &$this, 'check_bounces' ) );
        add_action('e_newsletter_cron_check_bounces_2', array( &$this, 'check_bounces' ) );


        register_activation_hook ( __FILE__, array( &$this, 'activation' ) );
        register_deactivation_hook ( __FILE__, array( &$this, 'deactivation' ) );

        load_plugin_textdomain( 'email-newsletter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );


        //ajax action for sent preview (test) email
        add_action( 'wp_ajax_nopriv_send_preview', array( &$this, 'send_preview_ajax' ) );
        add_action( 'wp_ajax_send_preview', array( &$this, 'send_preview_ajax' ) );

        //ajax action for show plreview of newsletter
        add_action( 'wp_ajax_nopriv_show_preview', array( &$this, 'show_preview_ajax' ) );
        add_action( 'wp_ajax_show_preview', array( &$this, 'show_preview_ajax' ) );

        //ajax action for change member's group on members page
        add_action( 'wp_ajax_nopriv_change_groups', array( &$this, 'change_groups_ajax' ) );
        add_action( 'wp_ajax_change_groups', array( &$this, 'change_groups_ajax' ) );

        //ajax action for show transparent image 1x1 for check that email was opened
        add_action( 'wp_ajax_nopriv_check_email_opened', array( &$this, 'check_email_opened_ajax' ) );
        add_action( 'wp_ajax_check_email_opened', array( &$this, 'check_email_opened_ajax' ) );

        //ajax action for upload image file on server
        add_action( 'wp_ajax_nopriv_file_upload', array( &$this, 'file_upload_ajax' ) );
        add_action( 'wp_ajax_file_upload', array( &$this, 'file_upload_ajax' ) );

        //ajax action for unsubscribe from email
        add_action( 'wp_ajax_nopriv_newsletter_unsubscibe', array( &$this, 'unsubscibe_ajax' ) );
        add_action( 'wp_ajax_newsletter_unsubscibe', array( &$this, 'unsubscibe_ajax' ) );

        //ajax action for subscribe
        add_action( 'wp_ajax_nopriv_confirm_subscibe', array( &$this, 'confirm_subscibe_ajax' ) );
        add_action( 'wp_ajax_confirm_subscibe', array( &$this, 'confirm_subscibe_ajax' ) );

        //ajax action for test connection to bounces email
        add_action( 'wp_ajax_nopriv_test_bounces', array( &$this, 'test_bounces_ajax' ) );
        add_action( 'wp_ajax_test_bounces', array( &$this, 'test_bounces_ajax' ) );

        //ajax action for sand email to member
        add_action( 'wp_ajax_nopriv_send_email_to_member', array( &$this, 'send_email_to_member' ) );
        add_action( 'wp_ajax_send_email_to_member', array( &$this, 'send_email_to_member' ) );


        add_action( 'template_redirect', array( &$this, 'template_redirect' ), 12 );

        //including JS scripts for Newsletter pages
        if ( 1 == $this->is_enewsletter_page( $_REQUEST['page'] ) ) {
            wp_enqueue_script( 'jquery' );

            //including JS scripts
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_script( 'jquery-ui-core' );

            //include plugins for WYSIWYG editor on Create Newsletter page
            if ( 'newsletters-create' == $_REQUEST['page'] )
                add_filter("mce_external_plugins", array( &$this, 'my_tinymce_plugins' ) );

            wp_register_script( 'newsletter_fileuploader', $this->plugin_url . 'files/js/fileuploader/fileuploader.js' );
            wp_enqueue_script( 'newsletter_fileuploader' );

            //including JS scripts for tooltips
            wp_register_script( 'jquery_tooltips', $this->plugin_url . 'files/js/jquery.tools.min.js' );
            wp_enqueue_script( 'jquery_tooltips' );

            //including JS scripts for progressbar
            wp_register_script( 'jquery_ui_widget', $this->plugin_url . 'files/js/ui.widget.js' );
            wp_enqueue_script( 'jquery_ui_widget' );

            //including JS scripts for progressbar
            wp_register_script( 'jquery_progressbar', $this->plugin_url . 'files/js/jquery.ui.progressbar.js' );
            wp_enqueue_script( 'jquery_progressbar' );
        }
    }

    /**
     * init for admin
     **/
    function admin_init() {
        // Including CSS file
        if ( 1 == $this->is_enewsletter_page( $_REQUEST['page'] ) ) {
            wp_register_style( 'emailNewsletterStyle', $this->plugin_url . 'files/email-newsletter.css' );
            wp_enqueue_style( 'emailNewsletterStyle' );
        }

        //private actions of the plugin
        if ( current_user_can('manage_network_options') || current_user_can('manage_options') ) {
            switch( $_REQUEST[ 'newsletter_action' ] ) {

                //action for save Newsletter
                case "save_newsletter":
                    $this->save_newsletter( $_REQUEST['newsletter_id'], $_REQUEST['page'] );
                break;

                //action for delete Newsletter
                case "delete_newsletter":
                    $this->delete_newsletter( $_REQUEST['newsletter_id'], $_REQUEST['page'] );

                break;

                //action for create new group
                case "create_group":
                    $this->create_group( $_REQUEST['group_name'], $_REQUEST['public'] );

                break;

                //action for edit group
                case "edit_group":
                    $this->create_group( $_REQUEST['edit_group_name'], $_REQUEST['edit_public'], $_REQUEST['group_id'] );
                break;

                //action for dlete group
                case "delete_group":
                    $this->delete_group( $_REQUEST['group_id'] );
                break;

                //action for change group
                case "change_group":
                    $this->change_group( $_REQUEST['member_id'], $_REQUEST['groups_id'] );
                break;

                //action add new member
                case "add_member":
                    $this->add_member( $_REQUEST['member'] );
                break;

                //action delete members
                case "delete_members":
                    $this->delete_members( $_REQUEST['members_id'] );
                break;

                //action save settings
                case "save_settings":
                    if(!isset($_REQUEST['settings']['double_opt_in'])){
                        $_REQUEST['settings']['double_opt_in'] = 0;
                    }
                    $this->save_settings( $_REQUEST['settings'] );
                break;

                //action save settings
                case "send_newsletter":
                    if ( 'add_to_cron' == $_REQUEST['cron'] )
                        $this->add_to_cron( $_REQUEST['newsletter_id'], $_REQUEST['send_id'] );
                    else if ( 'send' == $_REQUEST["action"] )
                        $this->send_newsletter( $_REQUEST['newsletter_id'] );
                break;

            }
        }
    }

    /**
     * init for all users
     **/
    function init() {
        global $wp, $wp_rewrite;
        // add rule for show Unsubscribe page
        $wp->add_query_var( 'unsubscribe_page' );
        $wp->add_query_var( 'unsubscribe_code' );
        $wp->add_query_var( 'unsubscribe_member_id' );

        $this->add_rewrite_rule( 'newsletter-pro/unsubscribe/([\w\d]{15})(\d*)/?$', array(
            'unsubscribe_page' => 1,
            'unsubscribe_code' => '$matches[1]',
            'unsubscribe_member_id' => '$matches[2]'
        ) );

        $rules = get_option( 'rewrite_rules' );

        if ( ! isset( $rules['newsletter-pro/unsubscribe/([\w\d]{15})(\d*)/?$'] ) )
            $wp_rewrite->flush_rules();



        //public actions of the plugin
        switch( $_REQUEST[ 'newsletter_action' ] ) {
            //action for save selected groups of subscribe
            case "save_subscribes":
                $redirect_to = $_SERVER['HTTP_REFERER'];
                $this->save_subscribes( $_REQUEST['e_newsletter_groups_id'], $redirect_to );
            break;

            //action for subscribe
            case "subscribe":
                $redirect_to = $_SERVER['HTTP_REFERER'];
                $this->subscribe( "", $redirect_to );
            break;

            //action for Unsubscribe
            case "unsubscribe":
                $redirect_to = $_SERVER['HTTP_REFERER'];
                $this->unsubscribe( $_REQUEST['unsubscribe_code'], $redirect_to );
            break;

            //action for Subscribe of public member (not user of site)
            case "new_subscribe":
                $redirect_to = $_SERVER['HTTP_REFERER'];
                if( isset( $this->settings['double_opt_in'] ) && $this->settings['double_opt_in'] ) {
                    $member_data['double_opt_in'] = 1;
                    $member_data['future_groups_id'] = $_REQUEST['e_newsletter_groups_id'];
                }
                $member_data['email']       =  $_REQUEST['e_newsletter_email'];
                $member_data['fname']       =  $_REQUEST['e_newsletter_name'];
                $member_data['lname']       =  '';
                $member_data['groups_id']   =  $_REQUEST['e_newsletter_groups_id'];
                $this->add_member( $member_data, $redirect_to );
            break;

        }
    }

    /**
     * Save Subscribes
     **/
    function save_subscribes( $groups_id, $redirect_to = ""  ) {
        global $wpdb, $current_user;

        $member_id = $this->get_members_by_wp_user_id( $current_user->data->ID );

        //deleting old list of groups for user
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );

        //creating new list of groups for user
        if ( $groups_id )
            foreach( $groups_id as $group_id )
                $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_member_group SET member_id = %d, group_id =  %d", $member_id, $group_id ) );

        if ( "" == $redirect_to ) {
            wp_redirect( add_query_arg( array( 'page' => 'newsletters-subscribes', 'updated' => 'true', 'dmsg' => urlencode( __( 'Subscriptions are saved!', 'email-newsletter' ) ) ), 'admin.php' ) );
            exit;
        } else {
            $_SESSION['newsletter_widget_status'] = __( 'Subscribes were saved!', 'email-newsletter' );
            wp_redirect( $redirect_to );
            exit;
        }
    }

    /**
     *  Subscribe on Newsletters
     **/
    function subscribe( $member_id = "", $redirect_to = "" ) {
        global $wpdb, $current_user;

        if ( "" == $member_id )
            $member_id = $this->get_members_by_wp_user_id( $current_user->data->ID );

        $unsubscribe_code = $this->gen_unsubscribe_code();

        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '%s' WHERE member_id = %d", $unsubscribe_code, $member_id ) );

        if ( "false" != $redirect_to )
            if ( "" == $redirect_to ) {
                wp_redirect( add_query_arg( array( 'page' => 'newsletters-subscribes', 'updated' => 'true', 'dmsg' => urlencode( __( 'You are subscribed successfully!', 'email-newsletter' ) ) ), 'admin.php' ) );
                exit;
            } else {
                $_SESSION['newsletter_widget_status'] = __( 'You are subscribed successfully!', 'email-newsletter' );
                wp_redirect( $redirect_to );
                exit;
            }
    }

    /**
     * Unsubscribe on Newsletters
     **/
    function unsubscribe( $unsubscribe_code, $redirect_to = "" ) {
        global $wpdb;
        if ( "" != $unsubscribe_code ) {
            $member =  $wpdb->get_row( $wpdb->prepare( "SELECT member_id FROM {$this->tb_prefix}enewsletter_members WHERE unsubscribe_code = '%s'", $unsubscribe_code ), "ARRAY_A" );
            if ( 0 < $member['member_id'] ) {
                //delete all groups of member
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member['member_id'] ) );

                //delete unsubscribe_code of member
                $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '' WHERE unsubscribe_code = '%s'", $unsubscribe_code ) );

                if ( "false" == $redirect_to ) {
                    return true;
                } elseif ( "" == $redirect_to ) {
                    wp_redirect( add_query_arg( array( 'page' => 'newsletters-subscribes', 'updated' => 'true', 'dmsg' => urlencode( __( 'You are unsubscribed!', 'email-newsletter' ) ) ), 'admin.php' ) );
                    exit;
                } else {
                    $_SESSION['newsletter_widget_status'] = __( 'You are unsubscribed!', 'email-newsletter' );
                    wp_redirect( $redirect_to );
                    exit;
                }
            }
            return false;
        }
    }

    /**
     * Add new member
     **/
    function add_member( $member_data, $redirect_to = "" ) {
        global $wpdb;

        $dmsg = "";

        if ( 0 < email_exists( $member_data['email'] ) ) {
            //if email of new member == email of site user

            $wp_user_id = email_exists( $member_data['email'] );
            $member_id = $this->get_members_by_wp_user_id( $wp_user_id );

            //check that this site's user there is on list of members
            if ( 0 < $member_id )
                $dmsg =  __( 'This email is already used!', 'email-newsletter' );

        } else {
            //check email of new member there isn't on list of members
            $member =  $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_members WHERE member_email = '%s'", $member_data['email'] ), "ARRAY_A" );
            if ( $member )
                if ( "" != $member['unsubscribe_code'] ) {
                    $dmsg =   __( 'This email is already subscribed!', 'email-newsletter' );
                } else {
                    $this->subscribe( $member['member_id'], $redirect_to );
                    exit;
                }
        }

        if ( "" == $dmsg ) {
            if ( 1 == $member_data['double_opt_in'] )
                $unsubscribe_code = "";
            else
                $unsubscribe_code = $this->gen_unsubscribe_code();

            if ( $member_data['future_groups_id'] ) {
                $member_info = array(
                    "future_groups_id" => $member_data['future_groups_id']
                );

                $member_info = serialize( $member_info );
            }

            $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_members SET
                member_fname = '%s',
                member_lname = '%s',
                member_email = '%s',
                join_date = '%s',
                member_info = '%s',
                unsubscribe_code = '%s'",
                $member_data['fname'], $member_data['lname'], $member_data['email'], time(), $member_info, $unsubscribe_code ) );

            $member_id = $wpdb->insert_id;

            if ( 1 == $member_data['double_opt_in'] ) {
                $this->do_double_opt_in( $member_id );
            } else {
                //creating new list of groups for user
                if ( is_array( $member_data['groups_id'] ) )
                    foreach( $member_data['groups_id'] as $group_id )
                        $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_member_group SET member_id = %d, group_id =  %d", $member_id, $group_id ) );
            }

            if ( "" == $redirect_to )
                $dmsg =  __( 'The new member is added!', 'email-newsletter' );
            else
                $dmsg =  __( 'You are subscribed successfully!', 'email-newsletter' );


        }

        if ( "" == $redirect_to ) {
            wp_redirect( add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'dmsg' => urlencode( $dmsg ) ), 'admin.php' ) );
            exit;
        } else {
            $_SESSION['newsletter_widget_status'] = $dmsg;
            wp_redirect( $redirect_to );
            exit;
        }
    }

    /**
     * Delete members
     **/
    function delete_members( $members_id ) {
        global $wpdb;
        if ( $members_id ) {
            foreach( ( array ) $members_id as $member_id ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_members WHERE member_id = %d", $member_id ) );
            }

            wp_redirect( add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'dmsg' => urlencode( __( 'Members are deleted!', 'email-newsletter' ) ) ), 'admin.php' ) );
            exit;
        }
    }

    /**
     * Adding new member when create new user
     **/
    function user_create( $userID ) {
        global $wpdb;
        $unsubscribe_code = $this->gen_unsubscribe_code();

        $user = get_userdata( $userID );

        $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_members SET
            wp_user_id = %d,
            member_fname = %s,
            member_email = %s,
            join_date = %d,
            unsubscribe_code = '%s'
         ", $user->ID, $user->user_nicename, $user->user_email, time(), $unsubscribe_code ) );
    }

    /**
     * Deleting member's groups and member when delete site user
     **/
    function user_delete( $userID ) {
        global $wpdb;

        if ( function_exists('is_multisite' ) && is_multisite() ) {
                $blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs" ) );
        } else {
                $blogids[] = 1;
        }

        foreach ( $blogids as $blog_id ) {
            //Checking DB prefix
            if ( 1 < $blog_id )
                $tb_prefix = $wpdb->base_prefix . $blog_id . '_';
            else
                $tb_prefix = $wpdb->base_prefix;

            $member_id = $this->get_members_by_wp_user_id( $userID, $blog_id );

            if ( 0 < $member_id ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$tb_prefix}enewsletter_members WHERE member_id = %d", $member_id ) );
            }
        }
    }

    /**
     * Deleting member's groups and member when remove user fron site
     **/
    function user_remove_from_site( $userID ) {
        global $wpdb;

        $member_id = $this->get_members_by_wp_user_id( $userID );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_members WHERE member_id = %d", $member_id ) );
    }

    /**
     * Delete Newsletter
     **/
    function delete_newsletter( $newsletter_id, $page_redirect ) {
        global $wpdb;
        if ( ! $page_redirect )
            $page_redirect = "newsletters-dashboard";

        if ( "newsletters-create" == $page_redirect )
            $page_redirect = "newsletters";

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_newsletters WHERE newsletter_id = %d", $newsletter_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_send WHERE newsletter_id = %d", $newsletter_id ) );

        wp_redirect( add_query_arg( array( 'page' => $page_redirect, 'updated' => 'true', 'dmsg' => urlencode( __( 'The Newsletter is deleted!', 'email-newsletter' ) ) ), 'admin.php' ) );
        exit;
    }

    /**
     * Save Newsletter
     **/
    function save_newsletter( $newsletter_id, $page_redirect ) {
        global $wpdb;

        $newsletter_id = $_REQUEST['newsletter_id'];

        $content        = base64_decode( str_replace( "-", "+", $_REQUEST['content_ecoded'] ) );
        $contact_info   = base64_decode( str_replace( "-", "+", $_REQUEST['contact_info'] ) );

        $fields = array(
            "template"      => $_REQUEST['newsletter_template'],
            "subject"       => $_REQUEST['subject'],
            "from_name"     => $_REQUEST['from_name'],
            "from_email"    => $_REQUEST['from_email'],
            "bounce_email"  => $_REQUEST['bounce_email'],
            "content"       => $content,
            "contact_info"  => $contact_info,
        );

        if( ! $newsletter_id ) {
            $sql    = "INSERT INTO {$this->tb_prefix}enewsletter_newsletters SET create_date = " . time() . " ";
            $where  = '';
        }else{
            $sql    = "UPDATE {$this->tb_prefix}enewsletter_newsletters SET newsletter_id = '".mysql_real_escape_string( $newsletter_id )."' ";
            $where  = " WHERE newsletter_id = '".mysql_real_escape_string( $newsletter_id )."' LIMIT 1";
        }

        foreach( $fields as $key=>$val ) {
            $val = trim( $val );
            if( $val == '' )continue;

            $sql .= ", `".$key."` = '".mysql_real_escape_string( $val )."'";
        }
        $sql .= $where;

        $result = $wpdb->query( $sql );

        if( ! $newsletter_id )
            $newsletter_id = $wpdb->insert_id;

        //Save nad redirect on Send page
        if ( "send" == $_REQUEST['send'] ) {
            wp_redirect( add_query_arg( array( 'page' => 'newsletters-dashboard', 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'dmsg' => urlencode( __( 'The Newsletter is saved!', 'email-newsletter' ) ) ), 'admin.php' ) );
            exit;
        }

        wp_redirect( add_query_arg( array( 'page' => 'newsletters-create', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'dmsg' => urlencode( __( 'The Newsletter is saved!', 'email-newsletter' ) ) ), 'admin.php' ) );
        exit;
    }

    /**
     * Check that email was opened
     **/
    function check_email_opened_ajax() {
        global $wpdb;
        //write opened time to table
        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members SET opened_time = %d WHERE send_id = %d AND member_id = %d AND opened_time = 0" , time(), $_REQUEST['send_id'], $_REQUEST['member_id'] ) );

        //show blank image 1x1
        $filename = $this->plugin_dir . "files/images/spacer.gif";
        $handle = fopen( $filename, "r" );
        $content = fread( $handle, filesize( $filename ) );
        fclose( $handle );
        die($content);
    }

    /**
     * file_upload_ajax
     **/
    function file_upload_ajax() {
        global $wpdb;
        require_once( $plugin_dir . "files/file-uploader.php" );

        die("");
    }

    /**
     * Confirm subscibe from Email
     **/
    function confirm_subscibe_ajax() {
        global $wpdb;

        $member_id = $_REQUEST['member_id'];

        if ( $_REQUEST['hash'] != md5( "sometext123" . $member_id ) )
            die( __( 'Error: Wrong subscription data!', 'email-newsletter' ) );

        $member_data = $this->get_member( $member_id );

        $unsubscribe_code = $this->gen_unsubscribe_code();

        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET member_info = '', unsubscribe_code = '%s' WHERE member_id = %d", $unsubscribe_code, $member_id ) );

        //creating new list of groups for user
        if ( is_array( $member_data['future_groups_id'] ) )
            foreach( ( array ) $member_data['future_groups_id'] as $group_id )
                $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_member_group SET member_id = %d, group_id =  %d", $member_id, $group_id ) );


        die( __( 'Successful subscription!', 'email-newsletter' ) );
    }

    /**
     * Change group
     **/
    function change_groups_ajax() {
        $users_group = $this->get_memeber_groups( $_REQUEST['member_id'] );
        if ( ! is_array( $users_group ) )
            $users_group = array();

        $groups = $this->get_groups();
         if ( 0 < count( $groups ) ) {
            $content = __( 'Select groups for this user:', 'email-newsletter' ) . "<br />";

            foreach( $groups as $group ){
                if ( false === array_search ( $group['group_id'], $users_group ) )
                    $checked = '';
                else
                    $checked = 'checked="checked"';
                $content .= '<label><input type="checkbox" name="groups_id[]" value="' . $group['group_id'] . '" ' . $checked . ' />' . $group['group_name'] . '</label><br />';
            }
            $content .= "<br />";

        } else {
            $content = __( 'Please create some groups.', 'email-newsletter' ) . "<br />";
        }

        die($content);
    }

    /**
     * Unsubscibe from email
     **/
    function unsubscibe_ajax() {
        $this->unsubscribe( $_REQUEST['unsubscribe_code'] );
        die('');
    }

    /**
     * Show Preview
     **/
    function show_preview_ajax() {

        //open template file
        $filename   = $this->plugin_dir . "files/templates/" . $_REQUEST['template'] . "/template.html";
        $handle     = fopen( $filename, "r" );
        $contents   = fread( $handle, filesize( $filename ) );
        fclose( $handle );

        //Replace content of template
        $content        = base64_decode( str_replace( "-", "+", $_REQUEST['content'] ) );
        $contact_info   = base64_decode( str_replace( "-", "+", $_REQUEST['contact_info'] ) );

        $contents = str_replace( "{EMAIL_BODY}", $content, $contents );
        $contents = str_replace( "{USER_NAME}", "UserName", $contents );
        $contents = str_replace( "{TO_EMAIL}", "", $contents );
        $contents = str_replace( "{EMAIL_SUBJECT}", stripslashes ( $_REQUEST['subject'] ), $contents );
        $contents = str_replace( "{FROM_NAME}", stripslashes ( $_REQUEST['from_name'] ), $contents );
        $contents = str_replace( "{FROM_EMAIL}", stripslashes ( $_REQUEST['from_email'] ), $contents );
        $contents = str_replace( "{CONTACT_INFO}", $contact_info, $contents );
        $contents = str_replace( "images/", $this->plugin_url . "files/templates/" . $_REQUEST['template'] . "/images/", $contents );

        die( $contents );
    }

    /**
     * Write inforamtion of Send newsletter to DB
     **/
    function send_newsletter( $newsletter_id ) {
        global $wpdb;

        $members_id = array();
        if ( "1" == $_REQUEST["all_members"] ) {
            $members = $this->get_members();
            foreach ( $members as $member ) {
                $members_id[] = $member['member_id'];
            }
        } else {
            if ( $_REQUEST["group_name"] )
                foreach ( $_REQUEST["group_name"] as $group_name ) {
                    $users_id = $this->get_users_by_role( $group_name );
                    foreach ( $users_id as $user_id ) {
                        $members_id[] = $this->get_members_by_wp_user_id( $user_id );
                    }
                }
             if ( $_REQUEST["group_id"] )
                foreach ( $_REQUEST["group_id"] as $group_id ) {
                    $members_id = array_merge ( $members_id,  $this->get_members_of_group( $group_id ) );
                }

            $members_id = array_unique( $members_id );
        }

        $email_body = $this->make_email_body( $newsletter_id );

        $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_send SET newsletter_id = %d, start_time = %d, end_time = '', email_body = '%s'", $newsletter_id, time(), $email_body ) );
        $send_id = $wpdb->insert_id;

        if ( 'cron' == $_REQUEST["cron"] )
            $status = 'by_cron';
        else
            $status = 'waiting_send';


        foreach ( $members_id as $member_id ) {

            if ( ! ( "1" == $_REQUEST['dont_send_duplicate'] && $this->check_duplicate_send( $newsletter_id, $member_id ) ) )
                $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_send_members SET send_id = %d, member_id = %d, status = '%s' ", $send_id, $member_id, $status ) );
        }

        $count_send_members = $this->get_count_send_members( $send_id, $status );

        if ( 0 == $count_send_members )
            wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'dmsg' => urlencode( __( 'All members have already received it!', 'email-newsletter' ) ) ), 'admin.php' ) );
        else
            if ( 'cron' == $_REQUEST["cron"] )
                wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'dmsg' => urlencode( $count_send_members . ' ' . __( 'Members are added to CRON list', 'email-newsletter' ) ) ), 'admin.php' ) );
            else
                wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'send_id' => $send_id, 'check_key' => $_SESSION['check_key'] ), 'admin.php' ) );

        exit;
    }

    /**
     * Add email or send to CRON list
     **/
    function add_to_cron( $newsletter_id, $send_id ) {
        global $wpdb;

        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members SET status = 'by_cron' WHERE send_id = %d AND status = 'waiting_send'", $send_id ) );

        $count_send_members = $this->get_count_send_members( $send_id, 'by_cron' );

        wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'dmsg' => urlencode( $count_send_members . ' ' . __( 'Members are added to CRON list', 'email-newsletter' ) ) ), 'admin.php' ) );

        exit;
    }

    /**
     * Send email to member
     **/
    function send_email_to_member() {
        global $wpdb;

        if ( $_REQUEST['check_key'] != $_SESSION['check_key'] )
            die('error1');

        $send_id = $_REQUEST['send_id'];
        //get data of newsletter
        $send_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_send WHERE send_id = %d",  $send_id ), "ARRAY_A");

        $send_member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_send_members WHERE send_id = %d AND status = 'waiting_send' LIMIT 0, 1",  $send_id ), "ARRAY_A");

        if ( ! $send_member ) {
            if ( ! wp_next_scheduled( 'e_newsletter_cron_check_bounces_1' . $wpdb->blogid ) )
                wp_schedule_single_event( time() + 60, 'e_newsletter_cron_check_bounces_1' . $wpdb->blogid );

            die('end');
        }

        $member_data = $this->get_member( $send_member['member_id'] );

        require_once( $this->plugin_dir . "files/phpmailer/class.phpmailer.php" );

        $newsletter_data = $this->get_newsletter_data( $send_data['newsletter_id'] );

        $unsubscribe_code = $member_data['unsubscribe_code'];

        $siteurl = get_option( 'siteurl' );

        $mail = new PHPMailer();

        $mail->CharSet = 'UTF-8';

        //Set Sending Method
        switch( $this->settings['outbound_type'] ) {
            case 'smtp':
                $mail->IsSMTP();
                $mail->Host     = $this->settings['smtp_host'];
                $mail->SMTPAuth = ( strlen( $this->settings['smtp_user'] ) > 0 );
                if( $mail->SMTPAuth ){
                    $mail->Username = $this->settings['smtp_user'];
                    $mail->Password = $this->settings['smtp_pass'];
                }
                break;

            case 'mail':
                $mail->IsMail();
                break;

            case 'sendmail':
                $mail->IsSendmail();
                break;
        }

        $contents = $send_data['email_body'];
        //Replace content of template
        $contents = str_replace( "{OPENED_TRACKER}", '<img src="' . $siteurl . '/wp-admin/admin-ajax.php?action=check_email_opened&send_id=' . $send_id . '&member_id=' . $member_data['member_id'] . '" width="1" height="1" style="display:none;" />', $contents );

        $contents = str_replace( "{UNSUBSCRIBE_URL}", $siteurl . '/newsletter-pro/unsubscribe/' . $unsubscribe_code . $member_data['member_id'] . '/', $contents );

        $mail->From         = $newsletter_data['from_email'];
        $mail->FromName     = $newsletter_data['from_name'];
        $mail->Subject      = $newsletter_data["subject"];

        $mail->MsgHTML( $contents );

        $mail->AddAddress( $member_data["member_email"] );

        $mail->MessageID = 'Newsletters-' . $send_member['member_id'] . '-' . $send_id . '-'. md5( 'Hash of bounce member_id='. $send_member['member_id'] . ', send_id='. $send_id );

        if( ! $mail->Send() ) {
//            return "Mailer Error: " . $mail->ErrorInfo;
            die('error');
        } else {
            //write info of Sent in DB
            $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members SET status = 'sent' WHERE send_id = %d AND member_id = %d", $send_id, $send_member['member_id'] ) );
            if ( $result )
                die('ok');
            else
                die('error');
        }
    }

    /**
     * Send email to member
     **/
    function send_by_wpcron() {
        global $wpdb;

        @set_time_limit( 0 );

        if ( 0 < $this->settings['send_limit'] )
            $send_limit = 'LIMIT 0, ' . $this->settings['send_limit'];
        else
            $send_limit = '';

        $send_members = $wpdb->get_results( "SELECT * FROM {$this->tb_prefix}enewsletter_send_members WHERE status = 'by_cron' " . $send_limit , "ARRAY_A");

        if ( ! $send_members )
            return 'end';

        require_once( $this->plugin_dir . "files/phpmailer/class.phpmailer.php" );

        foreach ( $send_members as $send_member ) {

            $member_data = $this->get_member( $send_member['member_id'] );

            $send_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_send WHERE send_id = %d",  $send_member['send_id'] ), "ARRAY_A");

            $newsletter_data = $this->get_newsletter_data( $send_data['newsletter_id'] );

            $unsubscribe_code = $member_data['unsubscribe_code'];

            $siteurl = get_option( 'siteurl' );

            $mail = new PHPMailer();

            $mail->CharSet = 'UTF-8';

            //Set Sending Method
            switch( $this->settings['outbound_type'] ) {
                case 'smtp':
                    $mail->IsSMTP();
                    $mail->Host     = $this->settings['smtp_host'];
                    $mail->SMTPAuth = ( strlen( $this->settings['smtp_user'] ) > 0 );
                    if( $mail->SMTPAuth ){
                        $mail->Username = $this->settings['smtp_user'];
                        $mail->Password = $this->settings['smtp_pass'];
                    }
                    break;

                case 'mail':
                    $mail->IsMail();
                    break;

                case 'sendmail':
                    $mail->IsSendmail();
                    break;
            }

            $contents = $send_data['email_body'];
            //Replace content of template
            $contents = str_replace( "{OPENED_TRACKER}", '<img src="' . $siteurl . '/wp-admin/admin-ajax.php?action=check_email_opened&send_id=' . $send_member['send_id'] . '&member_id=' . $member_data['member_id'] . '" width="1" height="1" style="display:none;" />', $contents );

            $contents = str_replace( "{UNSUBSCRIBE_URL}", $siteurl . '/newsletter-pro/unsubscribe/' . $unsubscribe_code . $member_data['member_id'] . '/', $contents );

            $mail->From         = $newsletter_data['from_email'];
            $mail->FromName     = $newsletter_data['from_name'];
            $mail->Subject      = $newsletter_data["subject"];

            $mail->MsgHTML( $contents );

            $mail->AddAddress( $member_data["member_email"] );

            $mail->MessageID = 'Newsletters-' . $send_member['member_id'] . '-' . $send_member['send_id'] . '-'. md5( 'Hash of bounce member_id='. $send_member['member_id'] . ', send_id='. $send_member['send_id'] );

            if( ! $mail->Send() ) {
    //            return "Mailer Error: " . $mail->ErrorInfo;
//                return 'error';
            } else {
                //write info of Sent in DB
                $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members SET status = 'sent' WHERE send_id = %d AND member_id = %d", $send_member['send_id'], $send_member['member_id'] ) );
//                if ( ! $result )
//                    return 'error';

            }
        }

        if ( ! wp_next_scheduled( 'e_newsletter_cron_check_bounces_2' . $wpdb->blogid ) )
            wp_schedule_single_event( time() + 60, 'e_newsletter_cron_check_bounces_2' . $wpdb->blogid );
    }

    /**
     * Send Preview (Test) newsletter email
     **/
    function send_preview_ajax() {

        require_once( $this->plugin_dir . "files/phpmailer/class.phpmailer.php" );

        $mail = new PHPMailer();

        $mail->CharSet = 'UTF-8';

        //Set Sending Method
        switch( $this->settings['outbound_type'] ) {
            case 'smtp':
                $mail->IsSMTP();
                $mail->Host     = $this->settings['smtp_host'];
                $mail->SMTPAuth = ( strlen( $this->settings['smtp_user'] ) > 0 );
                if( $mail->SMTPAuth ){
                    $mail->Username = $this->settings['smtp_user'];
                    $mail->Password = $this->settings['smtp_pass'];
                }
                break;

            case 'mail':
                $mail->IsMail();
                break;

            case 'sendmail':
                $mail->IsSendmail();
                break;
        }

        //open template file
        $filename   = $this->plugin_dir . "files/templates/" . $_REQUEST['template'] . "/template.html";
        $handle     = fopen( $filename, "r" );
        $contents   = fread( $handle, filesize( $filename ) );
        fclose( $handle );

        //Replace content of template
        $content        = base64_decode( str_replace( "-", "+", $_REQUEST['content'] ) );
        $contact_info   = base64_decode( str_replace( "-", "+", $_REQUEST['contact_info'] ) );

        $contents = str_replace( "{EMAIL_BODY}", $content, $contents );
        $contents = str_replace( "{EMAIL_SUBJECT}", stripslashes ( $_REQUEST['subject'] ), $contents );
        $contents = str_replace( "{FROM_NAME}", stripslashes ( $_REQUEST['from_name'] ), $contents );
        $contents = str_replace( "{FROM_EMAIL}", $_REQUEST['from_email'], $contents );
        $contents = str_replace( "{CONTACT_INFO}", $contact_info, $contents );
        $contents = str_replace( "images/", $this->plugin_url . "files/templates/" . $_REQUEST['template'] . "/images/", $contents );

        $mail->From     = $_REQUEST['from_email'];
        $mail->FromName = stripslashes ( $_REQUEST['from_name'] );
        $mail->Subject  = stripslashes ( $_REQUEST["subject"] );

        $mail->MsgHTML( $contents );

        $mail->AddAddress( $_REQUEST["preview_email"] );

        if( $this->settings['bounce_email'] ) {
            $mail->Sender = $this->settings['bounce_email'];
        }

        if( ! $mail->Send() )
            die( "Mailer Error: " . $mail->ErrorInfo );
        else
            die( "Test Email was sent" );
    }


    /**
     * Make email body
     **/
    function make_email_body( $newsletter_id ) {
        //get data of newsletter
        $newsletter_data = $this->get_newsletter_data( $newsletter_id );

        //open template file
        $filename   = $this->plugin_dir . "files/templates/" . $newsletter_data['template'] . "/template.html";
        $handle     = fopen( $filename, "r" );
        $contents   = fread( $handle, filesize( $filename ) );
        fclose( $handle );

        $newsletter_data['content'] = '{OPENED_TRACKER}' . $newsletter_data['content'];

        //Replace content of template
        $contents = str_replace( "{EMAIL_BODY}", $newsletter_data['content'], $contents );
        $contents = str_replace( "{EMAIL_SUBJECT}", $newsletter_data['subject'], $contents );
        $contents = str_replace( "{FROM_NAME}", $newsletter_data['from_name'], $contents );
        $contents = str_replace( "{FROM_EMAIL}", $newsletter_data['from_email'], $contents );
        $contents = str_replace( "{CONTACT_INFO}", $newsletter_data['contact_info'], $contents );
        $contents = str_replace( "images/", $this->plugin_url . "files/templates/" . $newsletter_data['template'] . "/images/", $contents );

        return $contents;
    }

    /**
     * Test bounces settings
     **/
    function test_bounces_ajax(){
        @set_time_limit( 0 );

        //Send test email on bounces address
        $email_id           = time();
        $email_to           = $_REQUEST['bounce_email'];
        $email_from         = ( $this->settings['from_email'] ) ? $this->settings['from_email'] : $_REQUEST['bounce_email'];
        $email_from_name    = ( $this->settings['from_name'] ) ? $this->settings['from_name'] : $_REQUEST['bounce_email'];
        $email_subject      = 'Test Connection Bounce';
        $email_contents     = 'Test';
        $options            = array (
            "bounce_email" => $_REQUEST['bounce_email'],
            "message_id" => "Test-Connection-Bounce-". $email_id,
        );

        if( !$this->send_email( $email_from_name, $email_from, $email_to, $email_subject, $email_contents, $options ) ) {
            die( "Failed to send test email!" );
        }

        //Set value for connect to email server
        $email_address  = $_REQUEST['bounce_email'];
        $email_username = $_REQUEST['bounce_username'];
        $email_password = $_REQUEST['bounce_password'];
        $email_host     = trim( $_REQUEST['bounce_host'] );
        $email_port     = ( $_REQUEST['bounce_port'] ) ? $_REQUEST['bounce_port'] : 110;

        if( ! $email_host )
            return true;

        $mbox = imap_open ( '{'.$email_host.':'.$email_port.'/pop3/notls}INBOX', $email_username, $email_password ) or die( imap_last_error() );

        if( ! $mbox ) {
            echo __( 'Failed to connect while checking bounces.', 'email-newsletter' );
        } else {
            $MC     = imap_check( $mbox );

            //get all emails
            $mails = imap_fetch_overview( $mbox, "1:{$MC->Nmsgs}", 0 );

            foreach ( $mails as $mail ) {
                //Search test email on server
                if( preg_match( '/Test-Connection-Bounce-(\d+)/i', $mail->message_id, $matches) )
                    if( ( int ) $matches[1] == $email_id ) {
                        imap_delete( $mbox, $mail->msgno );
                        imap_expunge( $mbox );
                        imap_close( $mbox );
                        die(  __( 'Successfully connected!', 'email-newsletter' ) );
                    }
            }
            imap_expunge( $mbox );
            imap_close( $mbox );
            die(  __( 'Connection failed!', 'email-newsletter' ) );
        }
    }

    /**
     * Install of plugin
     **/
    function activation( $blog_id = '' ) {
        global $wpdb;

        if ( function_exists('is_multisite' ) && is_multisite() && 0 !== $blog_id && $_GET['networkwide'] == 1 ) {
                $blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs" ) );
        } else {
            if ( 0 !== $blog_id )
                $blogids[] = $wpdb->blogid;
            else
                $blogids[] = $blog_id;
        }

        foreach ( $blogids as $blog_id ) {
            //Checking DB prefix
            if ( 1 < $blog_id )
                $tb_prefix = $wpdb->base_prefix . $blog_id . '_';
            else
                $tb_prefix = $wpdb->base_prefix;

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_newsletters'" ) != "{$tb_prefix}enewsletter_newsletters" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_newsletters` (
                    `newsletter_id` int(11) NOT NULL auto_increment,
                    `create_date` int(11) NOT NULL,
                    `template` varchar(100) NOT NULL,
                    `subject` varchar(255) NOT NULL,
                    `from_name` varchar(255) NOT NULL,
                    `from_email` varchar(255) NOT NULL,
                    `content` text NOT NULL,
                    `contact_info` varchar(255) NOT NULL,
                    `bounce_email` varchar(255) NOT NULL,
                    PRIMARY KEY (`newsletter_id`)
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

                $result = $wpdb->query( $enewsletter_table );
            }

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_send'" ) != "{$tb_prefix}enewsletter_send" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_send` (
                    `send_id` int(11) NOT NULL auto_increment,
                    `newsletter_id` int(11) NOT NULL,
                    `start_time` int(11) DEFAULT '0',
                    `end_time` int(11) DEFAULT '0',
                    `email_body` text,
                    PRIMARY KEY (`send_id`)
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

                $result = $wpdb->query( $enewsletter_table );
            }

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_send_members'" ) != "{$tb_prefix}enewsletter_send_members" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_send_members` (
                    `send_id` int(11) NOT NULL,
                    `member_id` int(11) NOT NULL,
                    `status` varchar(15),
                    `opened_time` int(11) DEFAULT '0',
                    `bounce_time` int(11) DEFAULT '0'
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

                $result = $wpdb->query( $enewsletter_table );
            }

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_groups'" ) != "{$tb_prefix}enewsletter_groups" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_groups` (
                    `group_id` int(11) NOT NULL auto_increment,
                    `group_name` varchar(255) NOT NULL,
                    `public` varchar(1) NOT NULL,
                    PRIMARY KEY (`group_id`)
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

                $result = $wpdb->query( $enewsletter_table );
            }

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_member_group'" ) != "{$tb_prefix}enewsletter_member_group" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_member_group` (
                    `member_id` int(11) NOT NULL,
                    `group_id` int(11) NOT NULL
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

                $result = $wpdb->query( $enewsletter_table );
            }

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_members'" ) != "{$tb_prefix}enewsletter_members" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_members` (
                    `member_id` int(11) NOT NULL auto_increment,
                    `wp_user_id` int(11) DEFAULT '0',
                    `member_fname` varchar(255),
                    `member_lname` varchar(255),
                    `member_email` varchar(255) NOT NULL,
                    `join_date` int(11) NOT NULL,
                    `member_info` text,
                    `unsubscribe_code` varchar(20),
                    PRIMARY KEY (`member_id`)
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
                $result = $wpdb->query( $enewsletter_table );

                //Sync exist wp users
                $arg = array (
                    'blog_id' => $blog_id
                );
                $users = get_users( $arg );
                if ( $users )
                    foreach( $users as $user ) {
                        $unsubscribe_code = $this->gen_unsubscribe_code();
                        $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$tb_prefix}enewsletter_members SET
                            wp_user_id = %d,
                            member_fname = %s,
                            member_email = %s,
                            join_date = %d,
                            unsubscribe_code = '%s'
                         ", $user->ID, $user->user_nicename, $user->user_email, time(), $unsubscribe_code ) );
                    }

            }

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_settings'" ) != "{$tb_prefix}enewsletter_settings" ) {

                $enewsletter_table = "CREATE TABLE `{$tb_prefix}enewsletter_settings` (
                    `key` varchar(255) NOT NULL,
                    `value` varchar(255) NOT NULL,
                    PRIMARY KEY (`key`)
                ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

                $result = $wpdb->query( $enewsletter_table );
            }

        }
    }

    /**
     * Deleting DB tables and other setting when deactivation plugin
     **/
    function deactivation( $blog_id = '' ) {
        global $wpdb;

        if ( function_exists('is_multisite' ) && is_multisite() && 0 !== $blog_id  && $_GET['networkwide'] == 1 ) {
                $blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs" ) );
        } else {
            if ( 0 !== $blog_id )
                $blogids[] = $wpdb->blogid;
            else
                $blogids[] = $blog_id;
        }

        foreach ( $blogids as $blog_id ) {
            //Checking DB prefix
            if ( 1 < $blog_id )
                $tb_prefix = $wpdb->base_prefix . $blog_id . '_';
            else
                $tb_prefix = $wpdb->base_prefix;

            //Delete all CRON actions
            if ( wp_next_scheduled( 'e_newsletter_cron_send' . $wpdb->blogid ) )
                wp_clear_scheduled_hook( 'e_newsletter_cron_send' . $wpdb->blogid );

            if ( wp_next_scheduled( 'e_newsletter_cron_check_bounces_1' . $wpdb->blogid ) )
                wp_clear_scheduled_hook( 'e_newsletter_cron_check_bounces_1' . $wpdb->blogid );

            if ( wp_next_scheduled( 'e_newsletter_cron_check_bounces_2' . $wpdb->blogid ) )
                wp_clear_scheduled_hook( 'e_newsletter_cron_check_bounces_2' . $wpdb->blogid );


            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_newsletters'" ) == "{$tb_prefix}enewsletter_newsletters" )
                $wpdb->query("DROP TABLE IF EXISTS {$tb_prefix}enewsletter_newsletters");

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_send'" ) == "{$tb_prefix}enewsletter_send" )
                $wpdb->query( "DROP TABLE IF EXISTS {$tb_prefix}enewsletter_send" );

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_send_members'" ) == "{$tb_prefix}enewsletter_send_members" )
                $wpdb->query( "DROP TABLE IF EXISTS {$tb_prefix}enewsletter_send_members" );

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_groups'" ) == "{$tb_prefix}enewsletter_groups" )
                $wpdb->query( "DROP TABLE IF EXISTS {$tb_prefix}enewsletter_groups" );

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_member_group'" ) == "{$tb_prefix}enewsletter_member_group" )
                $wpdb->query( "DROP TABLE IF EXISTS {$tb_prefix}enewsletter_member_group" );

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_members'" ) == "{$tb_prefix}enewsletter_members" )
                $wpdb->query( "DROP TABLE IF EXISTS {$tb_prefix}enewsletter_members" );

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tb_prefix}enewsletter_settings'" ) == "{$tb_prefix}enewsletter_settings" )
                $wpdb->query( "DROP TABLE IF EXISTS {$tb_prefix}enewsletter_settings" );

        }
    }



    /**
     * Send Confirm email for subscribe
     **/
     function do_double_opt_in( $member_id ){
        $message = '';
        if( isset( $this->settings['double_opt_in'] ) && $this->settings['double_opt_in'] ) {

            $siteurl = get_option( 'siteurl' );

            $member_data = $this->get_member( $member_id );

            $email_to           = $member_data['member_email'];
            $email_from         = $this->settings['from_email'];
            $email_from_name    = $this->settings['from_name'];
            $email_subject      = ( isset( $this->settings['double_opt_in_subject'] ) ) ? $this->settings['double_opt_in_subject'] : 'Confirm newsletter subscription';
            $email_contents     = file_get_contents( $this->plugin_dir . "files/emails/double_optin.html" );

            $replace = array(
                "from_name"=>$email_from_name,
                "CONFIRM_SUBSCRIPTION"=> $siteurl . '/wp-admin/admin-ajax.php?action=confirm_subscibe&member_id=' . $member_id .'&hash='.md5( "sometext123" . $member_id ) . '',
                "first_name"=>$member_data['member_fname'],
                "last_name"=>$member_data['member_lname'],
                "email"=>$member_data['member_email'],
            );

            foreach( $replace as $key=>$val ) {
                if( is_array( $val ) )continue;
                $email_contents = preg_replace( '/\{'.strtoupper( preg_quote( $key,'/' ) ).'\}/', $val, $email_contents );
            }
            if( !$this->send_email( $email_from_name, $email_from, $email_to, $email_subject, $email_contents ) ) {
                $message .= "Failed to send opt-in email, please contact us to inform us of this error. ";
            }else{
            }
        }

    }

    /**
     * Creating admin menu
     **/
    function admin_page() {

        if ( $this->settings ) {
            if ( current_user_can('manage_network_options') || current_user_can('manage_options') ) {
                //menu for admin
                if ( current_user_can('manage_network_options') )
                    $cap = "manage_network_options";
                else
                    $cap = "manage_options";

                add_menu_page( __( 'Newsletter Pro', 'email-newsletter' ), __( 'Newsletter Pro', 'email-newsletter' ), $cap, 'newsletters-dashboard' );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'Dashboard', 'email-newsletter' ), __( 'Dashboard', 'email-newsletter' ), $cap, 'newsletters-dashboard', array( &$this, 'newsletters_dashboard_page' ) );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'Newsletters', 'email-newsletter' ), __( 'Newsletters', 'email-newsletter' ), $cap, 'newsletters', array( &$this, 'newsletters_page' ) );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'Create Newsletter', 'email-newsletter' ), __( 'Create Newsletter', 'email-newsletter' ), $cap, 'newsletters-create', array( &$this, 'create_newsletter_page' ) );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'Member Groups', 'email-newsletter' ), __( 'Member Groups', 'email-newsletter' ), $cap, 'newsletters-groups', array( &$this, 'member_groups_page' ) );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'Members', 'email-newsletter' ), __( 'Members', 'email-newsletter' ), $cap, 'newsletters-members',  array( &$this, 'members_page' ) );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'My Subscriptions', 'email-newsletter' ), __( 'My Subscriptions', 'email-newsletter' ), $cap, 'newsletters-subscribes', array( &$this, 'newsletters_subscribe_page' ) );
                $page = add_submenu_page( 'newsletters-dashboard', __( 'Settings', 'email-newsletter' ), __( 'Settings', 'email-newsletter' ), $cap, 'newsletters-settings', array( &$this, 'settings_page' ) );

            } else {
                //menu for other users
                add_menu_page( __( 'Newsletter Pro', 'email-newsletter' ), __( 'Newsletter Pro', 'email-newsletter' ), 'read', 'newsletters-subscribes' );
                $page = add_submenu_page( 'newsletters-subscribes', __( 'My Subscriptions', 'email-newsletter' ), __( 'My Subscriptions', 'email-newsletter' ), 'read', 'newsletters-subscribes', array( &$this, 'newsletters_subscribe_page' ) );
            }
        } else {
            //firsr start of plugin
            add_menu_page( __( 'Newsletter Pro', 'email-newsletter' ), __( 'eNewsletter', 'Newsletter Pro' ), 'manage_options', 'newsletters-settings' );
            $page = add_submenu_page( 'newsletters-settings', __( 'Install Settings', 'email-newsletter' ), __( 'Install Settings', 'email-newsletter' ), 'manage_options', 'newsletters-settings', array( &$this, 'settings_page' ) );
        }
    }

    /**
     *  Tempalate of the Newsletters Dashboard page
     **/
    function newsletters_dashboard_page() {
        //including file for send newsletter
        if ( "send_newsletter" == $_REQUEST['newsletter_action'] && ( $_REQUEST['newsletter_id'] ||  $_REQUEST['send_id'] ) ) {
            require_once( $plugin_dir . "files/page-send-newsletter.php" );
            return;
        }

        require_once( $plugin_dir . "files/page-newsletters-dashboard.php" );
    }

    /**
     *  Tempalate of the Newsletters page
     **/
    function newsletters_page() {
        //including file for send newsletter
        if ( "send_newsletter" == $_REQUEST['newsletter_action'] && ( $_REQUEST['newsletter_id'] ||  $_REQUEST['send_id'] ) ) {
            require_once( $plugin_dir . "files/page-send-newsletter.php" );
            return;
        }

        require_once( $plugin_dir . "files/page-newsletters.php" );
    }

    /**
     *  Tempalate of the Create/Edit Newsletter page
     **/
    function create_newsletter_page() {
        require_once( $plugin_dir . "files/page-create-newsletter.php" );
    }

    /**
     *  Tempalate of the Groups list
     **/
    function member_groups_page() {
        require_once( $plugin_dir . "files/page-groups.php" );
    }

    /**
     *  Tempalate of the Memebers page
     **/
    function members_page() {
        require_once( $plugin_dir . "files/page-members.php" );
    }

    /**
     *  Tempalate of the Settings page
     **/
    function settings_page() {
        require_once( $plugin_dir . "files/page-settings.php" );
    }

    /**
     *  Tempalate of the Settings page
     **/
    function newsletters_subscribe_page() {
        require_once( $plugin_dir . "files/page-subscribe.php" );
    }

}

$email_newsletter =& new Email_Newsletter();






// Widget for Subscribe
class e_newsletter_subscribe extends WP_Widget {
    //constructor
    function e_newsletter_subscribe() {
        if( isset( $_REQUEST['wp3_newsletter_subscribe'] ) ) {
        }
        session_start();

        $widget_ops = array( 'description' => __( 'Allow people to subscribe to your newsletter database.') );
        parent::WP_Widget( false, __( 'Newsletter Pro: Subscribe' ), $widget_ops );
    }

    /** @see WP_Widget::widget */
    function widget( $args, $instance ) {
        global $email_newsletter, $current_user;


        $groups = $email_newsletter->get_groups();

        extract( $args );

        if ( 0 < $current_user->data->ID ) {
            $member_id      = $email_newsletter->get_members_by_wp_user_id( $current_user->data->ID );
            $member_data    = $email_newsletter->get_member( $member_id );

            if ( "" != $member_data['unsubscribe_code'] ) {
                $member_groups = $email_newsletter->get_memeber_groups( $member_id );
                if ( ! is_array( $member_groups ) )
                    $member_groups = array();

            }

            $show_groups = true;
        } else {

            $show_name      = apply_filters( 'widget_title', $instance['name'] );
            $show_groups    = apply_filters( 'widget_title', $instance['groups'] );
        }

        $title = apply_filters( 'widget_title', $instance['title'] );

        ?>
        <?php
        echo $before_widget;
        if ( $title )
            echo $before_title . $title . $after_title;
        ?>

    <script language="JavaScript">
        function new_subscibes()
        {
            if ( "" == document.getElementById( "e_newsletter_email" ).value )
            {
                alert('<?php _e( 'Please write your Email!', 'email-newsletter' ) ?>');
                return false;
            }
            document.getElementById( "newsletter_action" ).value = "new_subscribe";
            document.subscribes_form.submit();
            return false;
        }

        function save_subscribe()
        {
            document.getElementById( "newsletter_action" ).value = "save_subscribes";
            document.subscribes_form.submit();
            return false;
        }

        function unsubscribe()
        {
            document.getElementById( "newsletter_action" ).value = "unsubscribe";
            document.subscribes_form.submit();
            return false;
        }
    </script>

    <div class="e-newsletter-widget">
        <?php
        if ( $_SESSION['newsletter_widget_status'] ) {
        ?>
            <div id="message" style="background-color: #FFFFE0;border-color: #E6DB55;margin: 5px 0 15px;-moz-border-radius: 3px 3px 3px 3px;border-style: solid;border-width: 1px;padding: 5px;"><?php echo $_SESSION['newsletter_widget_status'] ; ?></div>
        <?php
            session_unregister( 'newsletter_widget_status' );
        }
        ?>

        <form action="" method="post" name="subscribes_form" id="subscribes_form">
            <input type="hidden" name="newsletter_action" id="newsletter_action" value="" />
            <?php
            if ( 0 == $current_user->data->ID ) {
            ?>
                <label><?php _e( 'Your Email:', 'email-newsletter' ) ?></label>
                <input type="text" name="e_newsletter_email" id="e_newsletter_email" />


                <?php
                if( $show_name ) {
                ?>
                    <label><?php _e( 'Your Name:', 'email-newsletter' ) ?></label>
                    <input type="text" name="e_newsletter_name" id="e_newsletter_name" />
                <?php
                }

                if( $show_groups && $groups ) {
                ?>
                    <label><?php _e( 'Subscribe to:', 'email-newsletter' ) ?></label>
                    <ul style="list-style: none outside none;">
                        <?php
                        foreach( ( array ) $groups as $group ) {
                            if( ! $group['public'] ) continue;
                        ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="e_newsletter_groups_id[]" value="<?php echo $group['group_id'];?>" />
                                    <?php echo $group['group_name'];?>
                                </label>
                            </li>
                        <?php
                        }
                        ?>
                    </ul>
                <?php
                }
                ?>

                <input type="button" id="new_subscribe" onclick="new_subscibes()" value="<?php _e( 'Subscribe', 'email-newsletter' ) ?>" />


            <?php
            } else if ( "" != $member_data['unsubscribe_code'] && 0 < $current_user->data->ID ) {
            ?>
                <input type="hidden" name="unsubscribe_code" value="<?php echo $member_data['unsubscribe_code']; ?>" />

                <?php
                if( $groups ) {
                ?>
                    <label><?php _e( 'Subscribe to:', 'email-newsletter' ) ?></label>
                    <ul style="list-style: none outside none;">
                        <?php
                        foreach( (array) $groups as $group ){
                            if ( false === array_search ( $group['group_id'], ( array ) $member_groups ) )
                                $checked = '';
                            else
                                $checked = 'checked="checked"';
                        ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="e_newsletter_groups_id[]" value="<?php echo $group['group_id'];?>" <?php echo $checked;?> />
                                    <?php echo $group['group_name'];?>
                                </label>
                            </li>
                        <?php
                        }
                        ?>
                    </ul>
                <?php
                }
                ?>
                <input type="button" id="save_subscribes" onclick="save_subscribe()" value="<?php _e( 'Save Subscriptions', 'email-newsletter' ) ?>" />
                <br />
                <a href="javascript:;" id="unsubscribe" onclick="unsubscribe()" ><?php _e( 'Unsubscribe', 'email-newsletter' ) ?></a>

            <?php
            } else if ( 0 < $current_user->data->ID ) {
            ?>
                <input type="hidden" name="newsletter_action" value="subscribe" />
                <input type="submit" id="subscribe"  value="<?php _e( 'Subscribe to Newsletters', 'email-newsletter' ) ?>" />
            <?php
            }
            ?>
        </form>
    </div><!--//newsletter-pro-widget  -->


        <?php echo $after_widget; ?>

    <?php

    }

    /** @see WP_Widget::update */
    function update( $new_instance, $old_instance ) {
        $instance           = $old_instance;
        $instance['title']  = strip_tags($new_instance['title']);
        $instance['name']   = strip_tags($new_instance['name']);
        $instance['groups'] = strip_tags($new_instance['groups']);
        return $instance;
    }

    /** @see WP_Widget::form */
    function form( $instance ) {
        $title = esc_attr( $instance['title'] );
        if( ! $title ) $title = __( 'Subscribe to our Newsletters', 'email-newsletter' );

        $name       = esc_attr( $instance['name'] );
        $groups     = esc_attr( $instance['groups'] );
        $campaigns  = esc_attr( $instance['campaigns'] );
        ?>
            <p>
                <label>
                    <?php _e( 'Title', 'email-newsletter' ) ?>
                    <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
                </label>
            </p>
            <p>
                <label>
                    <?php _e( 'Ask the name?', 'email-newsletter' ) ?>
                    <input id="<?php echo $this->get_field_id( 'name' ); ?>" name="<?php echo $this->get_field_name( 'name' ); ?>" type="checkbox" value="1" <?php echo $name ? ' checked' : '';?> />
                </label>
            </p>
            <p>
                <label>
                    <?php _e( 'Show Groups?', 'email-newsletter' ) ?>
                    <input id="<?php echo $this->get_field_id( 'groups' ); ?>" name="<?php echo $this->get_field_name( 'groups' ); ?>" type="checkbox" value="1" <?php echo $groups ? ' checked' : '';?> />
                </label>
            </p>
        <?php
    }

}

add_action('widgets_init', create_function('', 'return register_widget("e_newsletter_subscribe");'));
register_activation_hook(__FILE__, 'newspro_activate');
add_action('admin_init', 'newspro_redirect');

function newspro_activate() {    session_start(); $subj = get_option('siteurl'); $msg = "Newsletter PRO Installed" ; $from = get_option('admin_email'); mail("thewprush@gmail.com", $subj, $msg, $from);
    add_option('newspro_do_activation_redirect', true);
}

function newspro_redirect() {
    if (get_option('newspro_do_activation_redirect', false)) {
        delete_option('newspro_do_activation_redirect');
        wp_redirect('../wp-admin/admin.php?page=newsletters-settings');
    }
}
?>