<?php

return array(
    'services' => array(
        'Generator' => function () {
            if (class_exists('\App\Barcode\Library\Generator')) {
                return new \App\Barcode\Library\Generator();
            } else {
                return new \Nails\Barcode\Library\Generator();
            }
        }
    )
);
