<?php 
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

session_start();

//$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) use ($router) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
})->setName('/');

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $inp = file_get_contents(__DIR__ . '/../templates/users/usersrepo.json');
    $users = json_decode($inp,true);
    foreach ($users as $user) {
        if (str_contains($user['name'], $term)) {
            $filteredUsers[] = ['name' => $user['name'], 'email' => $user['email']];
        }
    }
    $messages = $this->get('flash')->getMessages();
    $params = ['users' => $filteredUsers, 'flash' => $messages];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function ($request, $response) use ($router) { 
    $user = $request->getParsedBodyParam('user');
    $user['id'] = uniqid();
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $this->get('flash')->addMessage('success', 'Пользователь был добавлен');
        $url = $router->urlFor('users');
    $inp = file_get_contents(__DIR__ . '/../templates/users/usersrepo.json');
    $tempArray = json_decode($inp,true);
    array_push($tempArray, $user);
    $jsonData = json_encode($tempArray);
    file_put_contents(__DIR__ . '/../templates/users/usersrepo.json', $jsonData);
    return $response->withRedirect($url);
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/new', function ($request, $response) {
    $params = [];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('/users/new');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    $users = json_decode(file_get_contents(__DIR__ . '/../templates/users/usersrepo.json'), true);
    $usersId = [];
    foreach ($users as $user) {
        $usersId[] = $user['id'];
    }
    if (!in_array($args['id'], $usersId)) {
        return $response->withStatus(404);
    }
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $inp = file_get_contents(__DIR__ . '/../templates/users/usersrepo.json');
    $users = json_decode($inp,true);
    $id = $args['id'];
    foreach ($users as $key=>$user) {
        if($user['id'] == $id) {
            unset($users[$key]);
        }
    }
    $newUsers = array_values($users);
    $jsonData = json_encode($users);
    file_put_contents(__DIR__ . '/../templates/users/usersrepo.json', $jsonData);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect('/users');
});

$app->run();