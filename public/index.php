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

//$app = AppFactory::createFromContainer($container);
//$app->addErrorMiddleware(true, true, true);

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->add(MethodOverrideMiddleware::class);

//$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
//$id = 1;

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
})->setName('/');
/*$app->get('/users', function ($request, $response) {
    return $response->write('GET /users');
});*/

$app->get('/users/login', function ($request, $response) use ($router) {
    $this->get('flash')->addMessage('success', 'Добро пожаловать!');
    return $this->get('renderer')->render($response, "users/login.phtml");
})->setName('login');

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/users', function ($request, $response) {
    $usersSearch = $request->getQueryParam('usersSearch', '');
    $loginEmail = $request->getQueryParam('email', '');

    // Добавление нового товара
    if ($loginEmail == 'Logout') {
        $_SESSION['user'] = [];
        session_destroy();
        return $response->withRedirect('/users/login');
    } elseif (!empty($loginEmail)) {
        $_SESSION['user'][] = $loginEmail;
    }
    //$users = json_decode(file_get_contents('datausers.txt'), true);
    $dataUsers = json_decode($request->getCookieParam('usersCook', json_encode([])), true);
    //$uuu = $request->getCookieParams();
    //print_r($uuu);
    if (!isset($_SESSION['user'])) {
        //return $response->withRedirect($router->urlFor('login'));
        return $response->withRedirect('/users/login');
    }
    $ourUsers = array_filter(($dataUsers), function($user) use ($usersSearch) {
    	return str_contains($user['name'], $usersSearch);
    });
    $params = ['users' => $ourUsers, 'usersSearch' => $usersSearch];
    $encodedData = json_encode($dataUsers);
    setcookie("usersCook", "{$encodedData}", ['path' => '/users', 'domain' => 'localhost']);
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');
//->withHeader('Set-Cookie', "usersCook={$encodedData}", "Path='/'", "domain='localhost'")

$router = $app->getRouteCollector()->getRouteParser();

$app->post('/users', function ($request, $response) {
    $user = $request->getParsedBodyParam('user', '');
    $validator = new Validator();
    $errors = $validator->validate($user);
    
    if (empty($errors)) {
//    $dataUsers = json_decode(file_get_contents('datausers.txt'), true);
    $dataUsers = json_decode($request->getCookieParam('usersCook', json_encode([])), true);
//    $dataUsers[(count($dataUsers) + 1)] = ['name' => htmlspecialchars($user['name']),
//    'email' => htmlspecialchars($user['email'])];
    $dataUsers[] = [
        'name' => htmlspecialchars($user['name']),
        'email' => htmlspecialchars($user['email'])
    ];
//    file_put_contents('datausers.txt', json_encode($dataUsers));
    $encodedData = json_encode($dataUsers);
    $messages = $this->get('flash')->getMessages();
    echo $messages['success'][0]; // => ['success' => ['This is a message']]
    print_r($dataUsers);
    $params = ['users' => $dataUsers, 'user' => $user];
    //return $response->withRedirect('/users', 302);
    setcookie("usersCook", "{$encodedData}", ['path' => '/users', 'domain' => 'localhost']);
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
}

    $params = [
    	'user' => $user,
    	'errors' => $errors
    	];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
    });

$app->get('/users/new', function ($request, $response) use ($router) {
    $this->get('flash')->addMessage('success', 'Новый пользователь успешно добавлен!');
    $usersUrl = $router->urlFor('users');
    $params = [
        'user' => ['name' => '', 'email' => ''],
        'users' => $usersUrl
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('/users/new');

/*$app->post('/users', function ($request, $response) {
    return $response->withStatus(302);
});*/

/*$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('/courses/{id}');*/

$app->get('/users/{id}', function ($request, $response, $args) {
    $users = json_decode($request->getCookieParam('usersCook', json_encode([])), true);
    if (!array_key_exists($args['id'], $users)) {
    $response->getBody()->write('Нет такого ID');
    	return $response->withStatus(404);
    }
    $params = ['id' => $args['id'], 'nickname' => $users[$args['id']]['name'], 'email' => $users[$args['id']]['email']];

    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $users = json_decode($request->getCookieParam('usersCook', json_encode([])), true);
    $id = $args['id'];
    $user = $users[$id];
    $params = [
    	'id' => $id,
        'user' => [$id => $user],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router)  {
    $users = json_decode($request->getCookieParam('usersCook', json_encode([])), true);
    $id = $args['id'];
    $user = $users[$id];
    $data = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($data);

    if (empty($errors)) {
        // Ручное копирование данных из формы в нашу сущность
        $users[$id]['name'] = $data['name'];

        $this->get('flash')->addMessage('success', 'User has been updated');
//        file_put_contents('datausers.txt', json_encode($users));
	 $encodedData = json_encode($users);
        $url = $router->urlFor('editUser', ['id' => $id]);
        return $response->withHeader('Set-Cookie', "usersCook={$encodedData}")->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->get('/users/{id}/unset', function ($request, $response, array $args) {
//    $users = json_decode(file_get_contents('datausers.txt'), true);
    $id = $args['id'];
    $user = $users[$id];
    $params = [
    	'id' => $id,
        'user' => [$id => $user],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/unset.phtml', $params);
})->setName('unsetUser');

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $users = json_decode($request->getCookieParam('usersCook', json_encode([])), true);
    $id = $args['id'];
    unset($users[$id]);
//    file_put_contents('datausers.txt', json_encode($users));
    $encodedData = json_encode($users);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    $response->withHeader('Set-Cookie', "usersCook=''");
    return $response->withHeader('Set-Cookie', "usersCook={$encodedData}")->withRedirect($router->urlFor('users'));
});

$router = $app->getRouteCollector()->getRouteParser();
// в функцию передаётся имя маршрута, а она возвращает url
//    $router->urlFor('users'); // /users
//    $router->urlFor('user', ['id' => 4]); // /users/4

$app->run();

