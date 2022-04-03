<?php
require_once 'vendor/autoload.php';
require "src/Hotels.php";

$hotels = array(
    new Hotels('Salon De The', "3 Stars", "https://cdn.pixabay.com/photo/2012/11/21/10/24/building-66789_1280.jpg"),
    new Hotels('Hotel Dananas', "5 Stars", "https://cdn.pixabay.com/photo/2016/09/16/12/53/hotel-1673952_1280.jpg"),
    new Hotels('Pizza Hawai', "3 Stars", "https://cdn.pixabay.com/photo/2016/11/17/09/28/hotel-1831072_1280.jpg")
);


$view = new \TYPO3Fluid\Fluid\View\TemplateView();

$paths = $view->getTemplatePaths();
$paths->setTemplatePathAndFilename('src/template.html');


$view->assignMultiple(
    ['hotels' => $hotels]
);


$output = $view->render();
echo $output;
