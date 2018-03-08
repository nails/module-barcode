<?php

return [
    'services' => [
        'Generator' => function () {
            if (class_exists('\App\Barcode\Service\Generator')) {
                return new \App\Barcode\Service\Generator();
            } else {
                return new \Nails\Barcode\Service\Generator();
            }
        }
    ]
];
