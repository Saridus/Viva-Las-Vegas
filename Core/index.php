<?php

$my_index = file_get_contents('template.html');


$hotels = array(
    'Salon De The'  => 3,
    'Hotel Dananas' => 5,
    'Pizza Hawai' => 3,
);


foreach ($hotels as $name => $stars) {
    $my_index = preg_replace("/###HOTELNAME###/", $name, $my_index, 1);
    $my_index = preg_replace("/###STARS###/", $stars." Stars", $my_index, 1);
}


echo $my_index;