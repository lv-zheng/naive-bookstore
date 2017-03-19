<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once 'vendor/autoload.php';

require_once 'config.php';

// set up autoloader
spl_autoload_register(function ($classname) {
	global $config;
	require ($config['class_dir'] . $classname . '.php');
});

$app = new \Slim\App(['settings' => $config]);

$container = $app->getContainer();

// set up error handler
$container['errorHandler'] = function ($c) {
	return function ($request, $response, $exception) {
		$code = $exception->getCode();
		if ($code >= 100 && $code < 600)
			$response = $response->withStatus($code);
		else
			$response = $response->withStatus(500);
		return $response->withJson(['message' => $exception->getMessage()]);
	};
};

// function for database error
function database_error() {
	throw new Exception('database error', 500);
}

// function for argument error
function argument_error() {
	throw new Exception('argument error', 400);
}

// function for loading id argument
function arg2id($s) {
	if (!ctype_digit($s))
		argument_error();
	$i = intval($s);
	if ($i <= 0 || (string) $i != $s)
		argument_error();
	return $i;
}

// argument parsing functions

function arg_req($b, $key, $req) {
	if (!isset($b[$key])) {
		if ($req)
			argument_error();
		return false;
	}
	return true;
}

function arg_string(&$a, $b, $key, $req = false) {
	if (!arg_req($b, $key, $req)) return;
	$a[$key] = $b[$key];
}

function arg_float(&$a, $b, $key, $req = false) {
	if (!arg_req($b, $key, $req)) return;
	if (!is_numeric($b[$key]))
		argument_error();
	$a[$key] = floatval($b[$key]);
}

// set up database
$container['db'] = function ($c) {
	$db = $c['settings']['db'];
	$mysql = new mysqli($db['host'], $db['user'], $db['pass'],
		$db['dbname'], $db['port'], $db['socket']);
	if ($mysql->connect_error)
		database_error();
	return $mysql;
};

// set up bookstore
$container['bookstore'] = function ($c) {
	return new Bookstore($c->db);
};

// expand json
function expand_json($result) {
	return [
		$result['content'],
		$result['status'],
	];
}

// routers
//
$app->get('/', function (Request $request, Response $response) {
	Header('Location: /index.html');
	die();
});


$app->get('/book', function (Request $request, Response $response) {
	$bookstore = $this->bookstore;
	$result = $bookstore->read_books();
	return $response->withJson(...expand_json($result));
});

$app->post('/book', function (Request $request, Response $response) {
	$args = $request->getParsedBody();
	$a = array();
	arg_string($a, $args, 'title', true);
	arg_float($a, $args, 'price', true);
	$bookstore = $this->bookstore;
	$result = $bookstore->create_book($a);
	return $response->withJson(...expand_json($result));
});

$app->delete('/book/{id}', function (Request $request, Response $response, $uriargs) {
	$id = arg2id($uriargs['id']);
	$bookstore = $this->bookstore;
	$result = $bookstore->delete_book($id);
	return $response->withJson(...expand_json($result));
});

$app->post('/book/{id}', function (Request $request, Response $response, $uriargs) {
	$id = arg2id($uriargs['id']);
	$args = $request->getParsedBody();
	$a = array();
	arg_string($a, $args, 'title', true);
	arg_float($a, $args, 'price', true);
	$bookstore = $this->bookstore;
	$result = $bookstore->update_book($id, $a);
	return $response->withJson(...expand_json($result));
});

$app->post('/crud/book/r', function (Request $request, Response $response, $uriargs) {
	$bookstore = $this->bookstore;
	$result = $bookstore->read_books();
	if ($result['status'] != 200) {
		return $response->withJson([
			'message' => $response['content']['message'],
		], $result['status']);
	}
	return $response->withJson([
		'total' => count($result['content']['books']),
		'rows' => $result['content']['books'],
	]);
});

$app->post('/crud/book/c', function (Request $request, Response $response, $uriargs) {
	$args = $request->getParsedBody();
	$a = array();
	arg_string($a, $args, 'title', true);
	arg_float($a, $args, 'price', true);
	$bookstore = $this->bookstore;
	$result = $bookstore->create_book($a);
	if ($result['status'] != 200) {
		return $response->withJson([
			'message' => $response['content']['message'],
		], $result['status']);
	}

	return $response->withJson($a + [
		'id' => $result['content']['id'],
	]);
});

$app->post('/crud/book/d/{id}', function (Request $request, Response $response, $uriargs) {
	$id = arg2id($uriargs['id']);
	$bookstore = $this->bookstore;
	$result = $bookstore->delete_book($id);
	if ($result['status'] != 200) {
		return $response->withJson([
			'message' => $response['content']['message'],
		], $result['status']);
	}

	return $response->withJson([
		'success' => true,
	]);
});

$app->post('/crud/book/u/{id}', function (Request $request, Response $response, $uriargs) {
	$id = arg2id($uriargs['id']);
	$args = $request->getParsedBody();
	$a = array();
	arg_string($a, $args, 'title', true);
	arg_float($a, $args, 'price', true);
	$bookstore = $this->bookstore;
	$result = $bookstore->update_book($id, $a);

	if ($result['status'] != 200) {
		return $response->withJson([
			'message' => $response['content']['message'],
		], $result['status']);
	}

	return $response->withJson($a + [
		'id' => $id,
	]);
});

$app->get('/hello/{title}', function (Request $request, Response $response) {
	$title = $request->getAttribute('title');
	$response->getBody()->write("Hello, $title");

	$db = $this->db;
	$stmt = $db->prepare("
INSERT INTO visit (name) values (?)
	");
	$stmt->bind_param('s', $title);
	$stmt->execute();

	if ($stmt->error)
		database_error();

	return $response->withJson([
		'message' => 'ok',
		'id' => $stmt->insert_id,
		'title' => $title,
	]);
});

// run!
$app->run();
