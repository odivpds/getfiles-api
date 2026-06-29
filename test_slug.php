<?php
$path = '/api/video/nqiiU.mp4';
if (preg_match('#(/api)?/video/([\w-\.]+)$#', $path, $matches)) {
    $slug = $matches[2];
    $slug = str_ireplace('.mp4', '', $slug);
    $short_id = substr($slug, -5);
    echo "SUCCESS: slug=$slug, short_id=$short_id\n";
} else {
    echo "FAIL REGEX\n";
}
