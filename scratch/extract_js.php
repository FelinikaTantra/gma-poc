<?php
$html = file_get_contents(__DIR__ . '/rendered_dashboard.html');
preg_match('/<script type="text\/babel"[^>]*>(.*?)<\/script>/s', $html, $matches);

if (isset($matches[1])) {
    file_put_contents(__DIR__ . '/extracted.js', $matches[1]);
    echo "Successfully extracted JS script to scratch/extracted.js\n";
} else {
    echo "Could not find <script type=\"text/babel\"> in HTML\n";
}
