<?php

/*
Plugin Name: Contact Form 7 - Pay.ir
Plugin URI: https://pay.ir
Description: اتصال فرم های Contact Form 7 به درگاه پرداخت Pay
Author: Pay.ir Team
Author URI: https://pay.ir
Version: 1.0
*/
function common($url, $params)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

    $response = curl_exec($ch);
    $error    = curl_errno($ch);

    curl_close($ch);

    $output = $error ? false : json_decode($response);

    return $output;
}
function payir_CF7_relative_time($ptime)
{
    date_default_timezone_set("Asia/Tehran");
    $etime = time() - $ptime;
    if ($etime < 1) {
        return '0 ثانیه';
    }
    $a = array(12 * 30 * 24 * 60 * 60 => 'سال',
        30 * 24 * 60 * 60 => 'ماه',
        24 * 60 * 60 => 'روز',
        60 * 60 => 'ساعت',
        60 => 'دقیقه',
        1 => 'ثانیه'
    );
    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? ' ' : '');
        }
    }
}


function result_payment_function($atts)
{
    global $wpdb;
    $status        = $_POST['status'];
    $transId      = $_POST['transId'];
    $emailmessage = '';

    $Theme_Message = get_option('cf7pp_theme_message', '');

    $theme_error_message = get_option('cf7pp_theme_error_message', '');

    $options = get_option('cf7pp_options');

    foreach ($options as $key => $val) {
        $value[$key] = $val;
    }

    $table_name = $wpdb->prefix . 'cf7_payir_transactions';
    $cf7_form = $wpdb->get_row("SELECT * FROM $table_name WHERE transId=" . $transId);
    if (null !== $cf7_form) {
        $amount = $cf7_form->cost;
    }
    $api = $value['gateway_apikey'];

        $params = array (
            'api'     => $api,
            'transId' => $transId
        );
        
        $result = common('https://pay.ir/payment/verify', $params);


        if ($result && isset($result->status) && $result->status == 1) {

            if($amount == $result->amount){

                $emailmessage = 'تراکنش شماره ' . $transId .' با موفقیت انجام شد. ';
                $wpdb->update($wpdb->prefix . 'cf7_payir_transactions', array('status' => 'success', 'transId' => $transId), array('transId' => $transId), array('%s', '%s'), array('%d'));
                $body = stripslashes(str_replace('[transaction_id]', $result->transId, $Theme_Message)).'<b/>';
                return MessageMaker("", $emailmessage, $body);

            }else{
                $emailmessage =  'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';
                $wpdb->update($wpdb->prefix . 'cf7_payir_transactions', array('status' => 'error'), array('transId' => $transId), array('%s'), array('%d'));
                $body = $theme_error_message.'<b/>';
                return MessageMaker("", $emailmessage, $body);
            }
        }else {
            $message = 'در ارتباط با وب سرویس Pay.ir و بررسی تراکنش خطایی رخ داده است';
            $wpdb->update($wpdb->prefix . 'cf7_payir_transactions', array('status' => 'error'), array('transId' => $transId), array('%s'), array('%d'));
            $body = $theme_error_message.'<b/>';
            return MessageMaker("", $message, $body);
        }

}

add_shortcode('result_payment', 'result_payment_function');


function MessageMaker($title, $body, $endstr = "")
{
    if ($endstr != "") {
        return $endstr;
    }
    $tmp = '<div style="border:#CCC 1px solid; width:90%;"> 
    ' . $title . '<br />' . $body . '</div>';
    return $tmp;
}


function PageMaker($title, $body)
{
    $tmp = '
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>' . $title . '</title>
	</head>
	<link rel="stylesheet"  media="all" type="text/css" href="' . plugins_url('style.css', __FILE__) . '">
	<body class="vipbody">	
	<div class="mrbox2" > 
	<h3><span>' . $title . '</span></h3>
	' . $body . '	
	</div>
	</body>
	</html>';
    return $tmp;
}

$dir = plugin_dir_path(__FILE__);

//  plugin functions
register_activation_hook(__FILE__, "cf7pp_activate");
register_deactivation_hook(__FILE__, "cf7pp_deactivate");
register_uninstall_hook(__FILE__, "cf7pp_uninstall");


function cf7pp_activate()
{
	
	global $wpdb;


    $table_name = $wpdb->prefix . "cf7_payir_transactions";
    if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE " . $table_name . " (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transId VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			user_mobile VARCHAR(11) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			PRIMARY KEY id (id)
		);";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    function wp_config_put($slash = '')
    {
        $config = file_get_contents(ABSPATH . "wp-config.php");
        $config = preg_replace("/^([\r\n\t ]*)(\<\?)(php)?/i", "<?php define('WPCF7_LOAD_JS', false);", $config);
        file_put_contents(ABSPATH . $slash . "wp-config.php", $config);
    }

    if (file_exists(ABSPATH . "wp-config.php") && is_writable(ABSPATH . "wp-config.php")) {
        wp_config_put();
    } else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && is_writable(dirname(ABSPATH) . "/wp-config.php")) {
        wp_config_put('/');
    } else {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been activated.', 'cf7pp'); ?></p>
        </div>
        <?php
        exit;
    }

    $cf7pp_options = array(
        'api' => '',
        'return' => '',
    );

    add_option("cf7pp_options", $cf7pp_options);


}


function cf7pp_deactivate()
{

    function wp_config_delete($slash = '')
    {
        $config = file_get_contents(ABSPATH . "wp-config.php");
        $config = preg_replace("/( ?)(define)( ?)(\()( ?)(['\"])WPCF7_LOAD_JS(['\"])( ?)(,)( ?)(0|1|true|false)( ?)(\))( ?);/i", "", $config);
        file_put_contents(ABSPATH . $slash . "wp-config.php", $config);
    }

    if (file_exists(ABSPATH . "wp-config.php") && is_writable(ABSPATH . "wp-config.php")) {
        wp_config_delete();
    } else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && is_writable(dirname(ABSPATH) . "/wp-config.php")) {
        wp_config_delete('/');
    } else if (file_exists(ABSPATH . "wp-config.php") && !is_writable(ABSPATH . "wp-config.php")) {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'cf7pp'); ?></p>
        </div>
        <button onclick="goBack()">Go Back and try again</button>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
        <?php
        exit;
    } else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && !is_writable(dirname(ABSPATH) . "/wp-config.php")) {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'cf7pp'); ?></p>
        </div>
        <button onclick="goBack()">Go Back and try again</button>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
        <?php
        exit;
    } else {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'cf7pp'); ?></p>
        </div>
        <button onclick="goBack()">Go Back and try again</button>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
        <?php
        exit;
    }
    
  
    delete_option("cf7pp_options");
    delete_option("cf7pp_my_plugin_notice_shown");

}


function cf7pp_uninstall()
{
}
add_action('admin_notices', 'cf7pp_my_plugin_admin_notices');

function cf7pp_my_plugin_admin_notices() {
    if (!get_option('cf7pp_my_plugin_notice_shown')) {
        echo "<div class='updated'><p><a href='admin.php?page=cf7pp_admin_table'>پیکربندی اطلاعات درگاه</a>.</p></div>";
        update_option("cf7pp_my_plugin_notice_shown", "true");
    }
}

include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {

    add_action('admin_menu', 'cf7pp_admin_menu', 20);
    function cf7pp_admin_menu()
    {
        $addnew = add_submenu_page('wpcf7',
        __('پیکربندی pay.ir', 'contact-form-7'),
        __('پیکربندی pay.ir', 'contact-form-7'),
        'wpcf7_edit_contact_forms', 'cf7pp_admin_table',
            'cf7pp_admin_table');

        $addnew = add_submenu_page('wpcf7',
            __('لیست تراکنش ها', 'contact-form-7'),
            __('لیست تراکنش ها', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'cf7pp_admin_list_trans',
            'cf7pp_admin_list_trans');

    }


    // hook into contact form 7 - before send
    add_action('wpcf7_before_send_mail', 'cf7pp_before_send_mail');
    function cf7pp_before_send_mail($cf7)
    {
    }


    // hook into contact form 7 - after send
    add_action('wpcf7_mail_sent', 'cf7pp_after_send_mail');
    function cf7pp_after_send_mail($cf7)
    {
        global $wpdb;
        global $postid;
        $postid = $cf7->id();
        

        $enable = get_post_meta($postid, "_cf7pp_enable", true);
       // $email = get_post_meta($postid, "_cf7pp_email", true);

        if ($enable == "1") {
					include_once ('redirect.php');
                exit;

        }

    } // End Function


    // hook into contact form 7 form
    add_action('wpcf7_admin_after_additional_settings', 'cf7pp_admin_after_additional_settings');
    function cf7pp_editor_panels($panels)
    {

        $new_page = array(
            'PricePay' => array(
                'title' => __('اطلاعات پرداخت', 'contact-form-7'),
                'callback' => 'cf7pp_admin_after_additional_settings'
            )
        );

        $panels = array_merge($panels, $new_page);

        return $panels;

    }

    add_filter('wpcf7_editor_panels', 'cf7pp_editor_panels');


    function cf7pp_admin_after_additional_settings($cf7)
    {

        $post_id = sanitize_text_field($_GET['post']);
        $enable = get_post_meta($post_id, "_cf7pp_enable", true);
        $amount = get_post_meta($post_id, "_cf7pp_price", true);
        $email = get_post_meta($post_id, "_cf7pp_email", true);
        $user_mobile = get_post_meta($post_id, "_cf7pp_mobile", true);
        $description = get_post_meta($post_id, "_cf7pp_description", true);

        if ($enable == "1") {
            $checked = "CHECKED";
        } else {
            $checked = "";
        }

        if ($email == "1") {
            $before = "SELECTED";
            $after = "";
        } elseif ($email == "2") {
            $after = "SELECTED";
            $before = "";
        } else {
            $before = "";
            $after = "";
        }

        $admin_table_output = "";
        $admin_table_output .= "<form>";
        $admin_table_output .= "<div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'><div id='additionalsettingsdiv' class='postbox'>";
        $admin_table_output .= "<div class='handlediv' title='Click to toggle'><br></div><h3 class='hndle ui-sortable-handle'> <span>اطلاعات پرداخت فرم</span></h3>";
        $admin_table_output .= "<div class='inside'>";

        $admin_table_output .= "<div class='mail-field'>";
        $admin_table_output .= "<input name='enable' id='cf71' value='1' type='checkbox' $checked>";
        $admin_table_output .= "<label for='cf71'>فعال سازی درگاه پرداخت پی دات آی آر</label>";
        $admin_table_output .= "</div>";

        //input -name
        $admin_table_output .= "<table>";
        $admin_table_output .= "<tr><td>مبلغ: </td><td><input type='text' name='price' style='text-align:left;direction:ltr;' value='$amount'></td><td>(مبلغ به تومان)</td></tr>";

        $admin_table_output .= "</table>";


        //input -id
        $admin_table_output .= "<br> **** در صورت خالی بودن فیلد مبلغ در بالا، کاربر امکان وارد کردن مبلغ را دارد ****  ";
        $admin_table_output .= "<input type='hidden' name='email' value='2'>";
        $admin_table_output .= "<input type='hidden' name='post' value='$post_id'>";
        $admin_table_output .= "</td></tr></table></form>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        echo $admin_table_output;

    }


    // hook into contact form 7 admin form save
    add_action('wpcf7_save_contact_form', 'cf7pp_save_contact_form');
    function cf7pp_save_contact_form($cf7)
    {

        $post_id = sanitize_text_field($_POST['post']);

        if (!empty($_POST['enable'])) {
            $enable = sanitize_text_field($_POST['enable']);
            update_post_meta($post_id, "_cf7pp_enable", $enable);
        } else {
            update_post_meta($post_id, "_cf7pp_enable", 0);
        }

        $amount = sanitize_text_field($_POST['price']);
        update_post_meta($post_id, "_cf7pp_price", $amount);

        $email = sanitize_text_field($_POST['email']);
        update_post_meta($post_id, "_cf7pp_email", $email);
    }


    function cf7pp_admin_list_trans()
    {
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        global $wpdb;

        $pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
        $limit = 6;
        $offset = ($pagenum - 1) * $limit;
        $table_name = $wpdb->prefix . "cf7_payir_transactions";

        $transactions = $wpdb->get_results("SELECT * FROM $table_name where (status NOT like 'none') ORDER BY $table_name.id DESC LIMIT $offset, $limit", ARRAY_A);
        $total = $wpdb->get_var("SELECT COUNT($table_name.id) FROM $table_name where (status NOT like 'none') ");
        $num_of_pages = ceil($total / $limit);
        $cntx = 0;

        echo '<div class="wrap">
		<h2>تراکنش فرم ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">کد تراکنش</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">کد تراکنش</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</tfoot>
			<tbody>';


        if (count($transactions) == 0) {

            echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="6">تراکنشی یافت نشد.</td>
				</tr>';

        } else {
            foreach ($transactions as $transaction) {

                echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="">' . get_the_title($transaction['idform']) . '</td>';
                echo '<td class="">' . strftime("%a, %B %e, %Y %r", $transaction['created_at']);
                echo '<br />(';
                echo payir_CF7_relative_time($transaction["created_at"]);
                echo ' قبل)</td>';

                echo '<td class="">' . $transaction['email'] . '</td>';
                echo '<td class="">' . $transaction['cost'] . ' تومان</td>';
                echo '<td class="">' . $transaction['transId'] . '</td>';
                echo '<td class="">';

                if ($transaction['status'] == "success") {
                    echo '<b style="color:#0C9F55">موفقیت آمیز</b>';
                } else {
                    echo '<b style="color:#f00">انجام نشده</b>';
                }
                echo '</td></tr>';

            }
        }
        echo '</tbody>
		</table>
        <br>';


        $page_links = paginate_links(array(
            'base' => add_query_arg('pagenum', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;', 'aag'),
            'next_text' => __('&raquo;', 'aag'),
            'total' => $num_of_pages,
            'current' => $pagenum
        ));

        if ($page_links) {
            echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div>
		</center>';
        }

        echo '<br>
		<hr>
	</div>';
    }


    function cf7pp_admin_table()
    {
        global $wpdb;
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        echo '<form method="post" action=' . $_SERVER["REQUEST_URI"] . ' enctype="multipart/form-data">';

        // save and update options
        if (isset($_POST['update'])) {

            $options['gateway_apikey'] = sanitize_text_field($_POST['gateway_apikey']);
            $options['return'] = sanitize_text_field($_POST['return']);

            update_option("cf7pp_options", $options);

            update_option('cf7pp_theme_message', wp_filter_post_kses($_POST['theme_message']));
            update_option('cf7pp_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
            
            echo "<br /><div class='updated'><p><strong>";
            _e("Settings Updated.");
            echo "</strong></p></div>";

        }

        $options = get_option('cf7pp_options');
        foreach ($options as $key => $val) {
            $value[$key] = $val;
        }
        

        $theme_message = get_option('cf7pp_theme_message', '');
        $theme_error_message = get_option('cf7pp_theme_error_message', '');
		
        
        echo "<div class='wrap'><h2>Contact Form 7 - Gateway Settings</h2></div><br />
		<table width='90%'><tr><td>";

        echo '<div style="background-color:#333333;padding:10px;color:#e4e4e4;font-size:14pt;font-weight:bold;">
		&nbsp; درگاه پرداخت payir برای فرم های Contact Form 7</div>
		<div style="background-color:#fff;border: 1px solid #E5E5E5;padding:5px;"><br />
		<q1>
    برای استفاده از درگاه پرداخت pay.ir، می توانید به بخش فرمهای تماس -> افزودن جدید رفته، سپس از کد های زیر استفاده نمایید: 
    <br><br>
    user_email : برای دریافت ایمیل کاربر   
    <br>
    user_mobile : برای دریافت موبایل کاربر   
    <br>
    description : برای دریافت توضیحات  
    <br>
    user_price : برای دریافت مبلغ از کاربر
    <br><br>
    مثال :
      <br>
    فیلد اختیاری : [text user_mobile]
    <br>
    فیلد اجباری : [text* user_mobile]
    </q1>
    <q1 style="color:#ff0008;">
<br><br>
**** در صورت وجود نداشتن کد زیر در فایل wp-config.php، آن را به فایل اضافه نمایید****

<br>

<pre style="direction: ltr; float: right;color: #212121">define("WPCF7_LOAD_JS",false);</pre>
</q1>
<br><br><br>
</div><br /><br />
		<div style="background-color:#333333;padding:10px;color:#e4e4e4;font-size:14pt;font-weight:bold;">
		&nbsp; اطلاعات درگاه پرداخت
		</div>
		<div style="background-color:white;border: 1px solid #E5E5E5;padding:20px;">	
		<table>
          <tr>
            <td>API Key:</td>';
            echo '
            <td><input type="text" style="width:450px;text-align:left;direction:ltr;margin-right: 90px;" name="gateway_apikey" value="' . $value['gateway_apikey'] . '"> الزامی </td>
          </tr>
        </table> 
        <table> 
        <br />
          <tr>
            <td>لینک بازگشت از تراکنش :</td>
            <td><input type="text" name="return" style="width:450px;text-align:left;direction:ltr; " value="' . $value['return'] . '"> الزامی</td>
          </tr>
        </table>
        <table>
            ****یک برگه برای بازگشت از تراکنش ایجاد کنید و کد [result_payment] را در آنجا قرار دهید **** 
        <br /><br />
		  <tr>
            <td>قالب تراکنش موفق :</td>
            <td><textarea name="theme_message" style="width:450px;text-align:right;direction:rtl;margin-right: 30px;">' . $theme_message . '</textarea></td>
          </tr>
        </table>
        <table>
			**** متنی که بعد از <b style="color: #398f14">موفق</b> بودن تراکنش به نمایش در می آید. همچنین می توانید برای نشان دادن شماره تراکنش از کد [transaction_id] استفاده کنید ****
		 <br /><br />
          <tr>
            <td>قالب تراکنش ناموفق :</td>
            <td><textarea name="theme_error_message" style="width:450px;text-align:right;direction:rtl;margin-right: 20px;">' . $theme_error_message . '</textarea></td>
          </tr>
        </table>
        <table>
			****متنی که بعد از <b style="color: #aa0000">ناموفق</b> بودن تراکنش به نمایش در می آید****
		<br /><br />
		   <tr>
             <td><input type="submit" name="btn2" class="button-primary" style="font-size: 15px;" value="ذخیره پیکربندی"></td>
          </tr>
        </table>
        </div>
        <br /><br />';
        echo "
		<br />		
		<input type='hidden' name='update'>
		</form>		
		</td></tr></table>";
    }
} else {
    // give warning if contact form 7 is not active
    function cf7pp_my_admin_notice()
    {
        echo '<div class="error">
			<p>' . _e('<b> افزونه درگاه پرداخت برای افزونه Contact Form 7 :</b> Contact Form 7 فعال نیست. ', 'my-text-domain') . '</p>
		</div>
		';
    }
    add_action('admin_notices', 'cf7pp_my_admin_notice');
}
?>