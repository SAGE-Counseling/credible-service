<?php
/*
 *
 *  * Copyright © ${YEAR} SAGE Counseling, Inc.
 *  * All rights reserved.
 *  *
 *  * This file is part SAGE Counseling, Inc. internal software systems.
 *  * Unauthorized use, reproduction, or distribution is strictly prohibited.
 *
 *
 */

return [
    'url' => env('CREDIBLE_URL'),
    'post_url' => env('CREDIBLE_POST_URL'),
    'api_backoff_timing' => [30, 60, 120, 200, 300, 400, 500, 600, 1000, 1500],
    'db_backoff_timing' => [30, 60, 120, 200, 300],
    'base_uri' => env('CREDIBLE_BASE_URI'),
    'post_uri' => env('CREDIBLE_POST_URI'),
    'get_uri' => env('CREDIBLE_GET_URI'),
    'soap_wsdl' => env('CREDIBLE_SOAP_WSDL'),
];
