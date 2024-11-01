<?php

require_once plugin_dir_path(__FILE__) . 'utils.php';

const FILES_SUBDIR = 'files/';

function v3d_api_upload_file(WP_REST_Request $request) {
    $response = new WP_REST_Response();
    $error_msg = '';

    $data = $request->get_body();

    if (!empty($data)) {
        $upload_dir = v3d_get_upload_dir() . FILES_SUBDIR;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $id = v3d_unique_filename();
        $filename = $upload_dir.$id;
        $mime = $request->get_content_type()['value'];

        if (v3d_check_mime($mime)) {
            $success = file_put_contents($filename, v3d_create_data_url($mime, $data));
            if ($success)
                $response->set_data(array(
                    'status' => 'ok',
                    'statusText' => 'Yay! File uploaded successfully.',
                    'id' => $id,
                    'link' => rest_url('verge3d/v1/get_file/'.$id),
                    'size' => strlen($data)
                ));
            else
                $error_msg = 'Oh no! Could not save file on the server.';
        } else
            $error_msg = 'Upload error, MIME type is not allowed: ' . $mime;

    } else {
        $error_msg = 'Upload error, file is empty';
    }

    if ($error_msg !== '') {
        $response->set_data(array(
            'status' => 'error',
            'statusText' => $error_msg
        ));
        $response->set_status(400);
    }

    if (get_option('v3d_cross_domain'))
        $response->header('Access-Control-Allow-Origin', '*');

    return $response;
}

function v3d_api_get_file(WP_REST_Request $request) {

    $response = new WP_REST_Response();
    $error_msg = '';

    $id = $request->get_param('id');

    if (!empty($id)) {
        $upload_dir = v3d_get_upload_dir() . FILES_SUBDIR;

        $file = $upload_dir.$id;

        // COMPAT: < 4.7
        $compat_json_storage = false;
        if (!is_file($file)) {
            $file = $file.'.json';
            $compat_json_storage = true;
        }

        if (is_file($file)) {
            $data = file_get_contents($file);

            if (!$compat_json_storage) {
                $parsed = v3d_parse_data_url($data);
                $mime = $parsed['mime'];

                if (v3d_check_mime($mime)) {
                    $response->header('Content-Type', $mime);
                    $response->set_data($parsed['data']);
                } else
                    $error_msg = 'MIME type is not allowed: ' . $mime;
            } else {
                // hack to prevent JSON decoding of base64-encoded strings
                $response->header('Content-Type', 'text/plain');
                $response->set_data($data);
            }

            if ($error_msg === '')
                add_filter('rest_pre_serve_request', 'v3d_api_get_file_response', 20, 2);

        } else
            $error_msg = 'File not found';

    } else {
        $error_msg = 'Bad request';
    }

    if ($error_msg !== '') {
        $response->set_data(array(
            'status' => 'error',
            'statusText' => $error_msg
        ));
        $response->set_status(400);
    }

    if (get_option('v3d_cross_domain'))
        $response->header('Access-Control-Allow-Origin', '*');

    return $response;
}

function v3d_api_get_file_response($served, $result) {
    $data = $result->get_data();

    if (is_string($data)) {
        echo $data;
        return true;
    }

    return $served;
}

add_action('rest_api_init', function () {
    if (get_option('v3d_file_api')) {

        register_rest_route('verge3d/v1', '/upload_file', array(
            'methods' => 'POST',
            'callback' => 'v3d_api_upload_file',
            'permission_callback' => '__return_true',
        ));

        register_rest_route('verge3d/v1', '/get_file/(?P<id>\w+)', array(
            'methods' => 'GET',
            'callback' => 'v3d_api_get_file',
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        // allow hex numbers
                        return (trim($param, '0..9A..Fa..f') === '');
                    }
                ),
            ),
            'permission_callback' => '__return_true',
        ));
    }
});
