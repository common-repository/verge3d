<?php

const DEBUG_WP_MAIL = false;

function v3d_get_upload_url() {
    return wp_upload_dir()['baseurl'].'/verge3d/';
}

/**
 * Get/create plugin's upload directory
 */
function v3d_get_upload_dir() {
    $upload_dir = wp_upload_dir()['basedir'].'/verge3d/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    return $upload_dir;
}

function v3d_unique_filename() {
    return time().bin2hex(random_bytes(4));
}

function v3d_get_attachments_tmp_dir($attachments) {
    if (empty($attachments)) {
        $temp_dir = get_temp_dir().uniqid('v3d_email_att');
        mkdir($temp_dir, 0777, true);
        return $temp_dir.'/';
    } else {
        return dirname($attachments[0]).'/';
    }
}

function v3d_create_data_url($mime, $data) {
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

function v3d_is_data_url($data) {
    if (substr($data_url, 0, 5) === 'data:')
        return true;
    else
        return false;
}

/**
 * base64-encoded URLs only
 */
function v3d_parse_data_url($data_url) {
    if (substr($data_url, 0, 5) == 'data:') {
        $data_url = str_replace(' ', '+', $data_url);
        $mime_start = strpos($data_url, ':') + 1;
        $mime_length = strpos($data_url, ';') - $mime_start;
        return array(
            'mime' => substr($data_url, $mime_start, $mime_length),
            'data' => base64_decode(substr($data_url, strpos($data_url, ',') + 1))
        );
    } else {
        return null;
    }
}

/**
 * WordPress MIMEs + Verge3D MIMEs
 */
function v3d_get_allowed_mime_types() {
    $allowed_mimes = get_allowed_mime_types();

    $v3d_mimes = get_option('v3d_upload_mime_types');
    if (!empty($v3d_mimes)) {
        foreach (explode(PHP_EOL, $v3d_mimes) as $line) {
            $line = wp_strip_all_tags($line);
            $line_split = preg_split('/ +/', $line, null, PREG_SPLIT_NO_EMPTY);

            if (count($line_split) != 2)
                continue;

            $mime = trim($line_split[0]);
            $ext = trim($line_split[1]);

            if (!empty($mime) && !empty($ext))
                $allowed_mimes[$ext] = $mime;
        }
    }

    return $allowed_mimes;
}

function v3d_check_mime($mime) {
    $allowed_mimes = v3d_get_allowed_mime_types();
    return in_array(strtolower($mime), array_map('strtolower', $allowed_mimes));
}

function v3d_inline_image($path) {
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    return v3d_create_data_url('image/'. $type, $data);
}

if (DEBUG_WP_MAIL) {
function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
    $mail_dir = v3d_get_upload_dir() . 'mail-debug/';
    if (is_dir($mail_dir))
        v3d_rmdir($mail_dir, false);
    else
        mkdir($mail_dir, 0777, true);

    file_put_contents($mail_dir . 'message.html', $message);

    $info = sprintf("To: %s\nSubject: %s\n", $to, $subject);
    if ($headers)
        foreach ($headers as $h)
            $info = sprintf("%s%s\n", $info, $h);
    file_put_contents($mail_dir . 'info.txt', $info);

    foreach ($attachments as $a)
        copy($a, $mail_dir . basename($a));
    return true;
}
}
