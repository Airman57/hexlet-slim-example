<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
})->setName('index');

$app->get('/users', function ($request, $response) {
    $usersData = json_decode($request->getCookieParam('users', json_encode([])), true);

    $userName = $request->getQueryParam('user');

    $filterUsers = array_filter($usersData, function ($user) use ($userName) {
        return str_contains($user['nickname'], $userName);
    });

    $messages = $this->get('flash')->getMessages();

    $params = [
        'users' => $filterUsers,
        'userName' => $userName,
        'flash' => $messages,
        'currentUser' => $_SESSION['user'] ?? null
    ];
    return $this->get('renderer')->render($response, "users/index.phtml", $params);
})->setName('users.index');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['name' => '', 'email' => ''],
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('users.new');

$app->post('/users', function ($request, $response) {
    $validator = new Validator();
    $user = $request->getParsedBodyParam('user');
    $errors = $validator->validate($user);

    if (count($errors) > 0) {
        $params = [
            'user' => $user,
            'errors' => $errors
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
    }

    $id = uniqid();

    $newUser = [
        'id' => $id,
        'nickname' => $user['name'],
        'email' => $user["email"],
    ];

    $existingUsers = json_decode($request->getCookieParam('users', json_encode([])), true);

    $existingUsers[] = $newUser;

    $encodedUsers = json_encode($existingUsers);

    $this->get('flash')->addMessage('success', 'User was added successfully');

    return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect('/users', 302);
})->setName('users.store');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('courses.show');

$app->get('/users/{id}', function ($request, $response, $args) {
    $userId = $args['id'];

    $existingUsers = json_decode($request->getCookieParam('users', json_encode([])), true);

    $userExists = false;
    foreach ($existingUsers as $user) {
        if ($user['id'] === $userId) {
            $userExists = true;
            break;
        }
    }

    if (!$userExists) {
        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'text/html')
                        ->write('Page not found');
    }

    $params = ['id' => $userId, 'nickname' => 'user-' . $userId, 'email' => $user['email']];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');

$app->get('/users/{id}/edit', function ($request, $response, $args) {
    $userId = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);

    $user = null;
    foreach ($users as $existingUser) {
        if ($existingUser['id'] == $userId) {
            $user = $existingUser;
            break;
        }
    }

    $params = ['user' => $user, 'errors' => []];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('users.edit');

$app->patch('/users/{id}', function ($request, $response, array $args) {
    $userId = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = null;
    foreach ($users as $existingUser) {
        if ($existingUser['id'] == $userId) {
            $user = $existingUser;
            break;
        }
    }
    $data = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($data);

    if (count($errors) === 0) {
        $user['email'] = $data['email'];

        foreach ($users as &$existingUser) {
            if ($existingUser['id'] == $userId) {
                $existingUser = $user;
            }
        }

        $encodedUsers = json_encode($users);

        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect('/users/' . $userId, 302);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $index = array_search($id, array_column($users, 'id'));

    array_splice($users, $index, 1);

    $encodedUsers = json_encode($users);

    $this->get('flash')->addMessage('success', 'School has been deleted');

    return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect('/users', 302);
});

$app->get('/login', function ($request, $response) {
    $params = ['currentUser' => $_SESSION['user'] ?? null];
    return $this->get('renderer')->render($response, 'users/login.phtml', $params);
});

$app->post('/session', function ($request, $response) {
    $userData = $request->getParsedBodyParam('user');
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = null;
    foreach ($users as $userItem) {
        if ($userItem['name'] === $userData['name']) {
            $user = $userItem;
            break;
        }
    }

    if ($user) {
        $_SESSION['user'] = $user;
    } else {
        $this->get('flash')->addMessage('error', 'Wrong email');
    }
    return $response->withRedirect('/users');
});

$app->delete('/session', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect('/users');
});

$app->run();