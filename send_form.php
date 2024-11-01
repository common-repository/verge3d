<?php

require_once plugin_dir_path(__FILE__) . 'utils.php';

function v3d_sanitize_form_fields($array_in) {
    $array_out = array();

    foreach ($array_in as $key => $value) {
        if (is_array($value))
            $value = v3d_sanitize_form_fields($value);
        else
            $value = sanitize_textarea_field($value);

        $array_out[sanitize_textarea_field($key)] = $value;
    }

    return $array_out;
}

function v3d_api_send_form(WP_REST_Request $request) {
    $response = new WP_REST_Response(
        array(
            'status' => 'ok',
            'statusText' => 'Email sent'
        )
    );
    $error_msg = '';

    $form_fields = v3d_sanitize_form_fields($request->get_body_params());

    if (!empty($form_fields)) {
        $to = get_option('v3d_order_email');
        $order_from_name = get_option('v3d_order_email_from_name');
        $order_from_email = get_option('v3d_order_email_from_email');

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        if (!empty($order_from_name) || !empty($order_from_email)) {
            $headers[] = 'From: "'.$order_from_name.'" <'.$order_from_email.'>';
        }

        $subject = get_option('v3d_send_form_email_subject');

        ob_start();
        include v3d_get_template('send_form_email_body.php');
        $message = ob_get_clean();

        $attachments = array();

        if (get_option('v3d_send_form_email_attachments')) {
            foreach ($request->get_file_params() as $key => $file) {
                if ($error_msg !== '')
                    break;

                if (is_array($file['name'])) {
                    $names = $file['name'];
                    $tmp_names = $file['tmp_name'];
                    $errors = $file['error'];
                } else {
                    $names = [$file['name']];
                    $tmp_names = [$file['tmp_name']];
                    $errors = [$file['error']];
                }

                for ($i = 0; $i < count($names); $i++) {
                    if ($errors[$i] !== UPLOAD_ERR_OK) {
                        $error_msg = 'File upload error';
                        break;
                    }

                    $name = sanitize_file_name($names[$i]);
                    $att_path_tmp = v3d_get_attachments_tmp_dir($attachments).$name;

                    if (!move_uploaded_file($tmp_names[$i], $att_path_tmp)) {
                        $error_msg = 'File upload error';
                        break;
                    }

                    $validate = wp_check_filetype($att_path_tmp, v3d_get_allowed_mime_types());
                    if ($validate['type'] === false) {
                        $error_msg = 'Forbidden MIME type for '.$name;
                        break;
                    }

                    $attachments[] = $att_path_tmp;
                }
            }
        }
    } else {
        $error_msg = 'Form is empty';
    }

    if ($error_msg === '') {
        $to = apply_filters('v3d_send_form_to', $to, $form_fields);
        $subject = apply_filters('v3d_send_form_subject', $subject, $form_fields);
        $message = apply_filters('v3d_send_form_message', $message, $form_fields);
        $headers = apply_filters('v3d_send_form_headers', $headers, $form_fields);
        $attachments = apply_filters('v3d_send_form_attachments', $attachments, $form_fields);

        if (!wp_mail($to, $subject, $message, $headers, $attachments)) {
            $error_msg = 'Unable to send email';
        }
    }

    if ($error_msg !== '') {
        $response->set_data(array(
            'status' => 'error',
            'statusText' => $error_msg
        ));
        $response->set_status(400);
    }

    foreach ($attachments as $a) {
        @unlink($a);
    }

    rmdir(v3d_get_attachments_tmp_dir($attachments));

    if (get_option('v3d_cross_domain'))
        $response->header('Access-Control-Allow-Origin', '*');

    return $response;
}

add_action('rest_api_init', function() {
    if (get_option('v3d_send_form_api')) {
        register_rest_route('verge3d/v1', '/send_form', array(
            'methods' => 'POST',
            'callback' => 'v3d_api_send_form',
            'permission_callback' => '__return_true'
        ));
    }
});
