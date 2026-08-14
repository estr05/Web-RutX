<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Bootstrap de pruebas — entorno hermético de sesión
|--------------------------------------------------------------------------
|
| Laravel lee las variables de entorno con prioridad sobre $_SERVER, pero
| PHPUnit solo puede sobrescribir $_ENV y getenv (no $_SERVER). Cuando la
| máquina o la terminal inyectan variables SESSION_* al proceso (p. ej. Git
| Bash en Windows pone SESSION_PATH=C:/Program Files/Git/, o una terminal
| carga .env con SESSION_DRIVER=database), los valores de prueba de
| phpunit.xml quedan eclipsados y toda petición web devuelve 500 (driver de
| sesión "database" sin tabla sessions, o cookie con path inválido).
|
| Este bootstrap se ejecuta antes de que la aplicación arranque y fija el
| entorno de sesión de pruebas en $_SERVER/$_ENV/getenv. En sistemas sin el
| quirk replica exactamente lo que ya declara phpunit.xml (no-op).
*/
$_SERVER['SESSION_DRIVER'] = 'array';
$_ENV['SESSION_DRIVER'] = 'array';
putenv('SESSION_DRIVER=array');

$_SERVER['SESSION_PATH'] = '/';
$_ENV['SESSION_PATH'] = '/';
putenv('SESSION_PATH=/');
