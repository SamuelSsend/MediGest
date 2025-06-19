<?php
require_once __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;

$factory = (new Factory)->withServiceAccount(__DIR__ . '/firebase_config.json');
$firestore = $factory->createFirestore()->database();
$auth = $factory->createAuth();
?>
