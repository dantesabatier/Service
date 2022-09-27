<?php

namespace Sabatier\Service;

use Sabatier\Foundation\URLScheme;

use function Sabatier\Foundation\string_has_prefix;

function requested_url(): string
{
    return sprintf("%s://%s%s", empty($_SERVER['HTTPS']) ? URLScheme::http : URLScheme::https, $_SERVER['HTTP_HOST'] ?? '', $_SERVER['REQUEST_URI'] ?? '');
}

if (!function_exists('getallheaders')) :
    /**
     * @return array<string, string>
     */
    function getallheaders(): array
    {
        $headers = [];
        $copy_server = [
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5' => 'Content-Md5',
        ];
        foreach ($_SERVER as $key => $value) {
            if (string_has_prefix($key, 'HTTP_')) {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } else {
                if (isset($copy_server[$key])) {
                    $headers[$copy_server[$key]] = $value;
                }
            }
        }
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } else {
                if (isset($_SERVER['PHP_AUTH_USER'])) {
                    $headers['Authorization'] = "Basic " . base64_encode(sprintf("%s:%s", $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? ''));
                } else {
                    if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                        $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
                    }
                }
            }
        }
        return $headers;
    }
endif;