<?php

  global $wpdb;
  global $postid;
        
    $wpcf7 = WPCF7_ContactForm::get_current();
        $submission = WPCF7_Submission::get_instance();
        $user_email = '';
        $user_mobile = '';
        $description = '';
        $user_price = '';

        if ($submission) {
            $data = $submission->get_posted_data();
            $user_email = isset($data['user_email']) ? $data['user_email'] : "";
            $user_mobile = isset($data['user_mobile']) ? $data['user_mobile'] : "";
            $description = isset($data['description']) ? $data['description'] : "";
            $user_price = isset($data['user_price']) ? $data['user_price'] : "";
        }
        
        
        
        $amount = get_post_meta($postid, "_cf7pp_price", true);
                if ($amount == "") {
                    $amount = $user_price;
                }
                $options = get_option('cf7pp_options');
                foreach ($options as $key => $val) {
                    $value[$key] = $val;
                }
                $active_gateway = 'payir';
                $api = $value['gateway_apikey'];
                $callback = $value['return'];


                $table_name = $wpdb->prefix . "cf7_payir_transactions";
                $_x = array();
                $_x['idform'] = $postid;
                $_x['transId'] = ''; // create dynamic or id_get
                $_x['gateway'] = $active_gateway; // name gateway
                $_x['cost'] = $amount;
                $_x['created_at'] = time();
                $_x['email'] = $user_email;
                $_x['user_mobile'] = $user_mobile;
                $_x['description'] = $description;
                $_x['status'] = 'none';
                $_y = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s');

                if ($active_gateway == 'payir') {


                    if (extension_loaded('curl') ) {

                         
                            $APIkey = $api; //Required
                            $Amount = $amount; //Amount will be based on Toman - Required
                            $Description = $description; // Required
                            $Email = $user_email; // Optional
                            $Mobile = $user_mobile; // Optional
                            $CallbackURL = get_site_url().'/'.$callback; // Required
                        
                        
                            $params = array(
                                'api'          => $APIkey,
                                'amount'       => $Amount,
                                'redirect'     => $CallbackURL,
                                'mobile'       => $Mobile,
                                'email'        => $Email,
                                'description'  => $Description
                            );

                            $result = common('https://pay.ir/payment/send', $params);


                            if ($result && isset($result->status) && $result->status == 1) {
                                $_x['transId'] = $result->transId;
                                $s = $wpdb->insert($table_name, $_x, $_y);
                                $gateway_url = 'https://pay.ir/payment/gateway/' . $result->transId;
                                wp_redirect($gateway_url);
                                exit;
                            } else {
                                $message = 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است';
                                $message = isset($result->errorMessage) ? $result->errorMessage : $message;
                                echo PageMaker('خطا در عملیات پرداخت', $message);
                            }
                
                        } else {
                            $message = 'تابع cURL در سرور فعال نمی باشد';
                            $message = isset($result->errorMessage) ? $result->errorMessage : $message;
                            echo PageMaker('خطا در عملیات پرداخت', $message);
                        }
                    }


                   
                

?>


        